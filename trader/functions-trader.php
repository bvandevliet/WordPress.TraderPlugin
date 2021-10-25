<?php

namespace Trader;

defined( 'ABSPATH' ) || exit;


/**
 * Update an existing balance with actual values from an exchange balance.
 *
 * @param \Trader\Exchanges\Balance $balance          Existing balance.
 * @param \Trader\Exchanges\Balance $balance_exchange Updated balance.
 * @param array                     $args {.
 *   @type float|string               $takeout        Amount in quote currency to keep out / not re-invest. Default is 0.
 * }
 *
 * @return \Trader\Exchanges\Balance $balance_merged Merged balance.
 */
function merge_balance( $balance, $balance_exchange = null, array $args = array() ) : \Trader\Exchanges\Balance
{
  if ( is_wp_error( $balance ) || ! $balance instanceof \Trader\Exchanges\Balance ) {
    $balance = new \Trader\Exchanges\Balance();
  }
  if ( is_wp_error( $balance_exchange ) || ! $balance_exchange instanceof \Trader\Exchanges\Balance ) {
    $balance_exchange = new \Trader\Exchanges\Balance();
  }

  $args['takeout'] = ! empty( $args['takeout'] ) ? trader_max( 0, trader_min( $balance_exchange->amount_quote_total, $args['takeout'] ) ) : 0;
  $takeout_alloc   = $args['takeout'] > 0 ? trader_get_allocation( $args['takeout'], $balance_exchange->amount_quote_total ) : 0;

  /**
   * Get current allocations.
   */
  foreach ( $balance->assets as $asset ) { // pass by ref not required since var is object
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
      foreach ( $balance->assets as $asset ) {
        if ( $asset_exchange->symbol === $asset->symbol ) {
          continue 2;
        }
      }

      $balance->assets[] = $asset_exchange;
    }
  }

  /**
   * Set total amount of quote currency and return $balance.
   */
  $balance->amount_quote_total = $balance_exchange->amount_quote_total ?? 0;
  return $balance;
}


/**
 * Retrieve allocation indicators.
 *
 * Subject to change: more indicators may be added in later versions.
 *
 * @param string       $symbol        Asset symbol.
 * @param float        $interval_days Rebalance period.
 * @param array[]|null $market_cap    Out. Historical price, free-float, current and realized market cap data.
 */
function retrieve_allocation_indicators(
  string $symbol,
  float $interval_days = 7,
  &$market_cap = null )
{
  /**
   * Market Cap.
   *
   * DISABLED FOR NOW TO SPEED UP PAGE LOAD,
   * BECAUSE HISTORICAL MARKET CAP DATA IS NOT USED AT THE MOMENT.
   */
  // $market_cap = Metrics\CoinMetrics::market_cap( $symbol );
}


/**
 * Sets absolute asset allocation values into $asset.
 *
 * Subject to change: more indicators may be added in later versions.
 *
 * @param mixed                   $weighting  User defined adjusted weighting factor, usually 1.
 * @param \Trader\Exchanges\Asset $asset      The asset object.
 * @param array                   $market_cap Historical price, free-float, current and realized market cap data.
 * @param int                     $sqrt       The square root of market cap to use.
 */
function set_asset_allocations(
  $weighting,
  \Trader\Exchanges\Asset $asset, // pass by ref not required since var is object
  array $market_cap,
  int $sqrt = 5 )
{
  $cap_ff = $market_cap[0]['CapMrktFFUSD'] ?? 0;

  $asset->allocation_rebl['default']  = trader_max( 0, bcmul( $weighting, pow( $cap_ff, 1 / $sqrt ) ) );
  $asset->allocation_rebl['absolute'] = trader_max( 0, $weighting );
}


/**
 * Construct a ranked $balance with rebalanced allocation data.
 *
 * @param array $assets_weightings     User defined adjusted weighting factors per asset.
 * @param array $args {.
 *   @type float        $interval_days Rebalance period.
 *   @type int          $top_count     Amount of assets from the top market cap ranking.
 *   @type int          $sqrt          The square root of market cap to use in allocation calculation.
 *   @type float|string $alloc_quote   Allocation to keep in quote currency. Default is 0.
 * }
 *
 * @return \Trader\Exchanges\Balance|WP_Error
 */
function get_asset_allocations(
  array $assets_weightings = array(),
  array $args = array() )
{
  $args = wp_parse_args(
    $args,
    array(
      'interval_days' => 7,
      'top_count'     => 30,
      'sqrt'          => 5,
    )
  );

  $alloc_quote = ! empty( $args['alloc_quote'] ) ? bcdiv( trader_max( 0, trader_min( 100, $args['alloc_quote'] ) ), 100 ) : '0';

  /**
   * Initiate balance object and quote asset.
   */
  $balance             = new \Trader\Exchanges\Balance();
  $asset_quote         = new \Trader\Exchanges\Asset();
  $asset_quote->symbol = \Trader\Exchanges\Bitvavo::QUOTE_CURRENCY;

  /**
   * List latest based on market cap.
   */
  $cmc_latest = Metrics\CoinMarketCap::list_latest(
    array(
      'limit'   => $args['top_count'],
      'convert' => \Trader\Exchanges\Bitvavo::QUOTE_CURRENCY,
    )
  );

  /**
   * Bail if the API request may have failed.
   */
  if ( is_wp_error( $cmc_latest ) ) {
    return $cmc_latest;
  }

  /**
   * Loop through the asset ranking and retrieve candlesticks and indicators.
   */
  foreach ( $cmc_latest as $asset_cmc ) {
    /**
     * Skip if is stablecoin or weighting is set to zero.
     */
    // phpcs:ignore WordPress.PHP.StrictComparisons
    if ( in_array( 'stablecoin', $asset_cmc->tags, true ) || ( array_key_exists( $asset_cmc->symbol, $assets_weightings ) && $assets_weightings[ $asset_cmc->symbol ] == 0 ) ) {
      continue;
    }

    /**
     * Define market.
     *
     * ONLY BITVAVO EXCHANGE IS SUPPORTED YET !!
     */
    $market = $asset_cmc->symbol . '-' . \Trader\Exchanges\Bitvavo::QUOTE_CURRENCY;

    /**
     * Get candlesticks from exchange.
     */
    $candles = \Trader\Exchanges\Bitvavo::candles(
      $market,
      '4h',
      array(
        'limit' => \Trader\Exchanges\Bitvavo::CANDLES_LIMIT,
        'end'   => time() * 1000,
      )
    );

    /**
     * Skip if market doesn't exist on the exchange.
     */
    if ( empty( $candles ) || ! empty( $candles['error'] ) || ! is_array( $candles ) || count( $candles ) === 0 ) {
      /**
       * ERROR HANDLING ? !!
       */
      continue;
    }

    /**
     * Store indicator data.
     */
    $asset_cmc->indicators = new \stdClass();
    retrieve_allocation_indicators(
      $asset_cmc->symbol,
      $args['interval_days'],
      $asset_cmc->indicators->market_cap
    );

    /**
     * Append to global array for next loop(s).
     */
    $balance->assets[] = new \Trader\Exchanges\Asset( $asset_cmc );
  }

  /**
   * Loop to retrieve absolute asset allocations.
   */
  $total_allocations = array();
  foreach ( $balance->assets as $index => $asset ) { // pass by ref not required since var is object
    /**
     * Retrieve weighted asset allocation.
     */
    set_asset_allocations(
      $assets_weightings[ $asset->symbol ] ?? 1,
      $asset,
      array( 0 => array( 'CapMrktFFUSD' => ( (array) $asset->quote )[ \Trader\Exchanges\Bitvavo::QUOTE_CURRENCY ]->market_cap ) ),
      $args['sqrt']
    );

    /**
     * Add up coin allocations.
     */
    foreach ( $asset->allocation_rebl as $mode => $allocation ) {
      if ( empty( $total_allocations[ $mode ] ) ) {
        $total_allocations[ $mode ] = 0;
      }
      $total_allocations[ $mode ] = bcadd( $total_allocations[ $mode ], $allocation );
    }
  }

  /**
   * Scale for quote allocation.
   */
  foreach ( $total_allocations as $mode => $total_allocation ) {
    $asset_quote->allocation_rebl[ $mode ] = $alloc_quote;
    $total_allocations[ $mode ]            = bcmul( bcdiv( '1', bcsub( 1, $alloc_quote ) ), $total_allocation );
  }

  /**
   * Loop to calculate relative asset allocations.
   */
  foreach ( $balance->assets as $asset ) { // pass by ref not required since var is object
    foreach ( $asset->allocation_rebl as $mode => &$allocation ) {
      $allocation = trader_get_allocation( $allocation, $total_allocations[ $mode ] );
    }
  }

  /**
   * Sort based on allocation.
   */
  usort(
    $balance->assets,
    function ( $a, $b )
    {
      return reset( $b->allocation_rebl ) <=> reset( $a->allocation_rebl );
    }
  );

  /**
   * Finally, return $balance.
   */
  array_unshift( $balance->assets, $asset_quote );
  return $balance;
}


/**
 * Perform a portfolio rebalance.
 *
 * @param \Trader\Exchanges\Balance $balance      Portfolio.
 * @param string                    $mode         Rebalance mode as defined by allocation in $balance->assets[$i]->allocation_rebl[$mode]
 * @param array                     $args {.
 *   @type int                        $dust_limit Minimum required allocation difference in quote currency. Default is 2.
 * }
 * @param bool                      $simulate     Perform a fake rebalance, e.g. to determine expected fee amount.
 *
 * @return array Order details.
 */
function rebalance( \Trader\Exchanges\Balance $balance, string $mode = 'default', array $args = array(), bool $simulate = false ) : array
{
  $args = wp_parse_args(
    $args,
    array(
      'dust_limit' => 2,
    )
  );

  /**
   * Initiate array $result containing order data.
   *
   * ONLY BITVAVO EXCHANGE IS SUPPORTED YET !!
   */
  $result = ! $simulate ? \Trader\Exchanges\Bitvavo::cancel_all_orders() : array();

  /**
   * Portfolio rebalancing: first loop placing sell orders.
   */
  foreach ( $balance->assets as $asset ) {
    /**
     * Skip if is quote currency.
     */
    if ( $asset->symbol === \Trader\Exchanges\Bitvavo::QUOTE_CURRENCY ) {
      continue;
    }

    $amount_quote = bcmul( $balance->amount_quote_total, $asset->allocation_rebl[ $mode ] ?? 0 );

    $diff = bcsub( $amount_quote, $asset->amount_quote );

    $amount_quote_to_sell = 0;

    /**
     * RECUDE allocation ..
     */
    if ( floatval( $diff ) <= -$args['dust_limit'] ) {
      if ( floatval( bcabs( $diff ) ) < \Trader\Exchanges\Bitvavo::MIN_QUOTE ) {
        // REBUY REQUIRED ..
        $amount_quote_to_sell = bcadd( bcabs( $diff ), \Trader\Exchanges\Bitvavo::MIN_QUOTE + 1 );

        $amount_quote_to_sell = bcdiv( $amount_quote_to_sell, bcsub( 1, \Trader\Exchanges\Bitvavo::TAKER_FEE ) ); // COMPENSATE FOR FEE IN SELL ORDER ..
        // $amount_quote_to_sell = bcmul( $amount_quote_to_sell, bcadd( 1, \Trader\Exchanges\Bitvavo::TAKER_FEE ) ); // COMPENSATE FOR FEE IN REBUY ORDER ..
      } else {
        // NOTHING TO REBUY ..
        $amount_quote_to_sell = bcabs( $diff );
      }
    }

    /**
     * INCREASE allocation ..
     */
    elseif ( floatval( $diff ) >= $args['dust_limit'] && floatval( $diff ) < \Trader\Exchanges\Bitvavo::MIN_QUOTE + 1 ) {
      // REBUY REQUIRED ..
      $amount_quote_to_sell = \Trader\Exchanges\Bitvavo::MIN_QUOTE;

      // $amount_quote_to_sell = bcdiv( $amount_quote_to_sell, bcsub( 1, \Trader\Exchanges\Bitvavo::TAKER_FEE ) ); // COMPENSATE FOR FEE IN SELL ORDER ..
      // $amount_quote_to_sell = bcmul( $amount_quote_to_sell, bcadd( 1, \Trader\Exchanges\Bitvavo::TAKER_FEE ) ); // COMPENSATE FOR FEE IN REBUY ORDER ..
    }
    // else // ONLY BUYING MAY BE REQUIRED ..

    if ( floatval( $amount_quote_to_sell ) > 0 ) {
      $result[] = $asset->rebl_sell_order = \Trader\Exchanges\Bitvavo::sell_asset( $asset->symbol, $amount_quote_to_sell, $simulate );
    }
  }

  /**
   * Portfolio rebalancing: second loop to verify all sell orders are filled.
   * OPTIMIZALBLE IF USING ordersOpen() ENDPOINT INSTEAD => LESS API CALLS => BUT HAS A REQUEST RATE LIMITING WEIGHT OF 25 ..
   */
  $all_filled  = false;
  $fill_checks = 60; // multiply by sleep seconds ..
  while ( ! $simulate && ! $all_filled && $fill_checks > 0 ) {
    sleep( 1 ); // multiply by $fill_checks ..

    $all_filled = true;
    foreach ( $balance->assets as $asset ) {
      // Only (re)request non-filled orders.
      if (
        empty( $asset->rebl_sell_order['orderId'] ) ||
        substr( $asset->rebl_sell_order['status'], 0, 6 ) === 'cancel' || in_array( $asset->rebl_sell_order['status'], array( 'filled', 'expired', 'rejected' ) )
      ) {
        continue;
      }

      $all_filled = false;

      $market = $asset->symbol . '-' . \Trader\Exchanges\Bitvavo::QUOTE_CURRENCY;

      if ( $fill_checks <= 1 ) { // QUEUE THIS ASSET REBL INSTEAD OF ORDER CANCEL !!
        \Trader\Exchanges\Bitvavo::cancel_order( $market, $asset->rebl_sell_order['orderId'] );
      }

      $asset->rebl_sell_order = \Trader\Exchanges\Bitvavo::get_order( $market, $asset->rebl_sell_order['orderId'] );
    }

    $fill_checks--;
  }

  /**
   * Portfolio rebalancing: third loop adding amounts to scale to available balance.
   * No need to pass takeout value as it is already applied to the passed $balance.
   */
  $balance      = ! $simulate ? merge_balance( $balance, \Trader\Exchanges\Bitvavo::get_balance() ) : $balance;
  $to_buy_total = 0;
  foreach ( $balance->assets as $asset ) {

    $amount_quote = bcmul( $balance->amount_quote_total, $asset->allocation_rebl[ $mode ] ?? 0 );

    /**
     * Only append absolute allocation to total buy value if is quote currency.
     */
    if ( $asset->symbol === \Trader\Exchanges\Bitvavo::QUOTE_CURRENCY ) {
      $to_buy_total = bcadd( $to_buy_total, $amount_quote );
      continue;
    }

    $asset->amount_quote_to_buy = bcsub( $amount_quote, ! $simulate ? $asset->amount_quote : bcsub( $asset->amount_quote, $asset->rebl_sell_order->amountQuote ?? 0 ) );

    /**
     * Skip if amount is below dust threshold.
     */
    if ( floatval( $asset->amount_quote_to_buy ) >= $args['dust_limit'] ) {
      $to_buy_total = bcadd( $to_buy_total, $asset->amount_quote_to_buy );
    }
  }

  /**
   * Portfolio rebalancing: fourth loop (re)buying assets.
   */
  foreach ( $balance->assets as $asset ) {
    /**
     * Skip if is quote currency as it is the currency we buy with, not we can buy.
     */
    if ( $asset->symbol === \Trader\Exchanges\Bitvavo::QUOTE_CURRENCY ) {
      continue;
    }

    /**
     * Skip if amount is below dust threshold.
     */
    if ( floatval( $asset->amount_quote_to_buy ) < $args['dust_limit'] ) {
      continue;
    }

    $amount_quote_to_buy = ! $simulate ? bcmul( $balance->assets[0]->available, trader_get_allocation( $asset->amount_quote_to_buy, $to_buy_total ) ) : $asset->amount_quote_to_buy;

    if ( floatval( $amount_quote_to_buy ) >= \Trader\Exchanges\Bitvavo::MIN_QUOTE ) {
      $result[] = $asset->rebl_buy_order = \Trader\Exchanges\Bitvavo::buy_asset( $asset->symbol, $amount_quote_to_buy, $simulate );
    }
  }

  return $result;
}
