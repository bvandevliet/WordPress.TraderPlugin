<?php

namespace Trader;

defined( 'ABSPATH' ) || exit;


class Balance
{
  /**
   * Array of assets. First item must be quote asset.
   *
   * @var Asset[]
   */
  public array $assets = array();

  /**
   * Total value of balance in quote currency.
   *
   * @var string
   */
  public ?string $amount_quote_total = null;

  /**
   * Update an existing balance with actual values from an exchange balance.
   *
   * MAKE NON-STATIC !!
   *
   * @param Balance|null|\WP_Error $balance          Existing balance.
   * @param Balance|null|\WP_Error $balance_exchange Updated balance.
   * @param Configuration          $configuration    Rebalance configuration.
   *
   * @return Balance $balance_merged Merged balance.
   */
  public static function merge_balance( $balance, $balance_exchange, Configuration $configuration ) : Balance
  {
    if ( is_wp_error( $balance ) || ! $balance instanceof Balance ) {
      $balance_merged = new Balance();
    } else {
      // Clone the object to prevent making changes to the original.
      $balance_merged = clone $balance;
    }
    if ( is_wp_error( $balance_exchange ) || ! $balance_exchange instanceof Balance ) {
      $balance_exchange = new Balance();
    }

    $configuration->takeout = ! empty( $configuration->takeout ) ? trader_max( 0, trader_min( $balance_exchange->amount_quote_total, $configuration->takeout ) ) : 0;
    $takeout_alloc          = $configuration->takeout > 0 ? trader_get_allocation( $configuration->takeout, $balance_exchange->amount_quote_total ) : 0;

    /**
     * Get current allocations.
     */
    foreach ( $balance_merged->assets as $asset ) {
      if ( ! empty( $balance_exchange->assets ) ) {
        foreach ( $balance_exchange->assets as $asset_exchange ) {
          if ( $asset_exchange->symbol === $asset->symbol ) {
            // we cannot use wp_parse_args() as we have to re-assign $asset which breaks the reference to the original object
            // $asset = (object) wp_parse_args( $asset_exchange, (array) $asset );
            foreach ( (array) $asset_exchange as $key => $value ) {
              // don't override value of rebalance allocations
              if ( $key !== 'allocation_rebl' ) {
                $asset->$key = $value;
              }
            }
            // only modify rebalance allocations if a takeout value is set
            if ( $takeout_alloc > 0 ) {
              foreach ( $asset->allocation_rebl as $mode => $allocation ) {
                $asset->allocation_rebl[ $mode ] = bcmul( $allocation, bcsub( 1, $takeout_alloc ) );
                if ( $asset->symbol === \Trader\Exchanges\Bitvavo::QUOTE_CURRENCY ) {
                  $asset->allocation_rebl[ $mode ] = bcadd( $asset->allocation_rebl[ $mode ], $takeout_alloc );
                }
              }
            }
            break;
          }
        }
      }
    }

    /**
     * Append missing allocations.
     */
    if ( ! empty( $balance_exchange->assets ) ) {
      foreach ( $balance_exchange->assets as $asset_exchange ) {
        foreach ( $balance_merged->assets as $asset ) {
          if ( $asset_exchange->symbol === $asset->symbol ) {
            continue 2;
          }
        }

        $balance_merged->assets[] = clone $asset_exchange;
      }
    }

    /**
     * Set total amount of quote currency and return $balance_merged.
     */
    $balance_merged->amount_quote_total = $balance_exchange->amount_quote_total ?? 0;
    return $balance_merged;
  }
}
