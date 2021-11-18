<?php

namespace Trader;

defined( 'ABSPATH' ) || exit;


/**
 * Update an existing balance with actual values from an exchange balance.
 *
 * @param \Trader\Exchanges\Balance|null|\WP_Error $balance          Existing balance.
 * @param \Trader\Exchanges\Balance|null|\WP_Error $balance_exchange Updated balance.
 * @param Configuration                            $configuration    Rebalance configuration.
 *
 * @return \Trader\Exchanges\Balance $balance_merged Merged balance.
 */
function merge_balance( $balance, $balance_exchange = null, ?Configuration $configuration = null ) : \Trader\Exchanges\Balance
{
  $configuration = $configuration ?? Configuration::get();

  if ( is_wp_error( $balance ) || ! $balance instanceof \Trader\Exchanges\Balance ) {
    $balance_merged = new \Trader\Exchanges\Balance();
  } else {
    // Clone the object to prevent making changes to the original.
    $balance_merged = clone $balance;
  }
  if ( is_wp_error( $balance_exchange ) || ! $balance_exchange instanceof \Trader\Exchanges\Balance ) {
    $balance_exchange = new \Trader\Exchanges\Balance();
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


/**
 * Retrieve Market Cap EMA.
 *
 * Subject to change: more indicators may be added in later versions.
 *
 * @param array $asset_cmc_arr  Array of historical cmc asset objects of a single asset.
 * @param array $market_cap_ema Out. Smoothed Market Cap values.
 * @param int   $smoothing      The period to use for smoothing Market Cap.
 */
function retrieve_market_cap_ema( array $asset_cmc_arr, &$market_cap_ema, int $smoothing = 14 )
{
  /**
   * Calculate Exponential Moving Average of Market Cap.
   */
  $market_cap_arr = array();

  foreach ( $asset_cmc_arr as $index => $asset_cmc ) {
    $quote = ( (array) $asset_cmc->quote )[ \Trader\Exchanges\Bitvavo::QUOTE_CURRENCY ];

    $market_cap_arr[] = $quote->market_cap ?? 0;

    /**
     * Break if required amount for smoothing period is reached OR if next iteration is of more than 1 days offset.
     */
    if ( empty( $quote->market_cap ) ||
      $index + 1 >= $smoothing || $index + 1 <= trader_offset_days( $quote->last_updated )
    ) {
      break;
    }
  }

  $real   = array_reverse( $market_cap_arr );
  $period = count( $market_cap_arr );

  // calculate EMA
  $market_cap_ema = $period > 1 ? \LupeCode\phpTraderNative\Trader::ema( $real, $period ) : /*array_reverse( */$market_cap_arr;/* )*/
}


/**
 * Sets absolute asset allocation values into $asset.
 *
 * Subject to change: more indicators may be added in later versions.
 *
 * @param mixed                   $weighting  User defined adjusted weighting factor, usually 1.
 * @param \Trader\Exchanges\Asset $asset      The asset object.
 * @param mixed                   $market_cap Smoothed Market Cap value.
 * @param int                     $nth_root   The nth root of Market Cap to use for allocation.
 */
function set_asset_allocations( $weighting, \Trader\Exchanges\Asset $asset, $market_cap, int $nth_root = 4 )
{
  $asset->allocation_rebl['default']  = trader_max( 0, bcmul( $weighting, pow( $market_cap, 1 / $nth_root ) ) );
  $asset->allocation_rebl['absolute'] = trader_max( 0, $weighting );
}


/**
 * Construct a ranked $balance with rebalanced allocation data.
 *
 * @param Configuration $configuration Rebalance configuration.
 *
 * @return \Trader\Exchanges\Balance|\WP_Error
 */
function get_asset_allocations( ?Configuration $configuration = null )
{
  $configuration = $configuration ?? Configuration::get();

  $alloc_quote = ! empty( $configuration->alloc_quote ) ? bcdiv( trader_max( 0, trader_min( 100, $configuration->alloc_quote ) ), 100 ) : '0';
  $alloc_quote = $configuration->alloc_quote_fag_multiply ? bcmul( $alloc_quote, bcdiv( \Trader\Metrics\Alternative_Me::fag_index_current(), 100 ) ) : $alloc_quote;

  /**
   * List latest based on market cap.
   */
  $cmc_latest = Metrics\CoinMarketCap::list_latest(
    array(
      'sort'    => 'market_cap',
      'convert' => \Trader\Exchanges\Bitvavo::QUOTE_CURRENCY,
    ),
    $configuration->smoothing
  );

  /**
   * Bail if the API request may have failed.
   */
  if ( is_wp_error( $cmc_latest ) ) {
    return $cmc_latest;
  }

  /**
   * Initiate balance object and quote asset.
   */
  $balance             = new \Trader\Exchanges\Balance();
  $asset_quote         = new \Trader\Exchanges\Asset();
  $asset_quote->symbol = \Trader\Exchanges\Bitvavo::QUOTE_CURRENCY;

  /**
   * Loop through the asset ranking and retrieve Market Cap EMA.
   */
  foreach ( $cmc_latest as $asset_cmc_arr ) {
    /**
     * Get Market Cap EMA.
     */
    retrieve_market_cap_ema( $asset_cmc_arr, $market_cap_ema, $configuration->smoothing );
    $asset_cmc_arr[0]->indicators                 = new \stdClass();
    $asset_cmc_arr[0]->indicators->market_cap_ema = end( $market_cap_ema );
  }

  /**
   * Sort again based on EMA value then handle top count.
   */
  usort(
    $cmc_latest,
    function ( $a, $b )
    {
      return $b[0]->indicators->market_cap_ema <=> $a[0]->indicators->market_cap_ema;
    }
  );
  $cmc_latest = array_slice( $cmc_latest, 0, $configuration->top_count );

  /**
   * Loop to leave out certain non-relevant assets then retrieve candlesticks and indicators.
   */
  foreach ( $cmc_latest as $asset_cmc_arr ) {
    /**
     * Skip if is stablecoin or weighting is set to zero.
     */
    if (
      in_array( 'stablecoin', $asset_cmc_arr[0]->tags, true ) ||
      ( array_key_exists( $asset_cmc_arr[0]->symbol, $configuration->asset_weightings ) && $configuration->asset_weightings[ $asset_cmc_arr[0]->symbol ] <= 0 )
    ) {
      continue;
    }

    /**
     * Define market.
     */
    $market = $asset_cmc_arr[0]->symbol . '-' . \Trader\Exchanges\Bitvavo::QUOTE_CURRENCY;

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
     * Get additional price action indicator data, TO BE DEVELOPED !!
     */
    // retrieve_allocation_indicators( $asset_cmc_arr[0], $candles, $configuration->interval_hours );

    /**
     * Append to global array for next loop(s).
     */
    $balance->assets[] = new \Trader\Exchanges\Asset( $asset_cmc_arr[0] );
  }

  /**
   * Loop to retrieve absolute asset allocations.
   */
  $total_allocations = array();
  foreach ( $balance->assets as $index => $asset ) {
    /**
     * Retrieve weighted asset allocation.
     */
    set_asset_allocations(
      $configuration->asset_weightings[ $asset->symbol ] ?? 1,
      $asset,
      $asset->indicators->market_cap_ema,
      $configuration->nth_root
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
  foreach ( $balance->assets as $asset ) {
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
   * Finally, prepend quote currency and return $balance.
   */
  array_unshift( $balance->assets, $asset_quote );
  return $balance;
}


/**
 * Perform a portfolio rebalance.
 *
 * @param \Trader\Exchanges\Balance $balance       Portfolio.
 * @param string                    $mode          Rebalance mode as defined by allocation in $balance->assets[$i]->allocation_rebl[$mode]
 * @param Configuration             $configuration Rebalance configuration.
 * @param bool                      $simulate      Perform a fake rebalance, e.g. to determine expected fee amount.
 *
 * @return array Order details.
 */
function rebalance( \Trader\Exchanges\Balance $balance, string $mode = 'default', ?Configuration $configuration = null, bool $simulate = false ) : array
{
  $configuration = $configuration ?? Configuration::get();

  /**
   * Initiate array $result containing order data.
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
    if ( floatval( $diff ) <= -$configuration->dust_limit ) {
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
    elseif ( floatval( $diff ) >= $configuration->dust_limit && floatval( $diff ) < \Trader\Exchanges\Bitvavo::MIN_QUOTE + 1 ) {
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
    if ( floatval( $asset->amount_quote_to_buy ) >= $configuration->dust_limit ) {
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
    if ( floatval( $asset->amount_quote_to_buy ) < $configuration->dust_limit ) {
      continue;
    }

    $amount_quote_to_buy = ! $simulate ? bcmul( $balance->assets[0]->available, trader_get_allocation( $asset->amount_quote_to_buy, $to_buy_total ) ) : $asset->amount_quote_to_buy;

    if ( floatval( $amount_quote_to_buy ) >= \Trader\Exchanges\Bitvavo::MIN_QUOTE ) {
      $result[] = $asset->rebl_buy_order = \Trader\Exchanges\Bitvavo::buy_asset( $asset->symbol, $amount_quote_to_buy, $simulate );
    }
  }

  return $result;
}
