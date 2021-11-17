<?php

namespace Trader;

defined( 'ABSPATH' ) || exit;


/**
 * Get rebalance parameters from request parameters.
 *
 * @return array
 */
function get_args_from_request_params() : array
{
  $defaults = array(
    'top_count'                => 30,
    'smoothing'                => 14,
    'sqrt'                     => 4,
    'alloc_quote'              => 0,
    'takeout'                  => 0,
    'alloc_quote_fag_multiply' => false,
  );

  $args = array();
  foreach ( $defaults as $param => $default ) {
    // phpcs:ignore WordPress.Security
    $req_value = $_POST[ $param ] ?? $_GET[ $param ] ?? null;
    $req_value = null !== $req_value ? wp_unslash( $req_value ) : null;
    switch ( $param ) {
      case 'top_count':
        $args[ $param ] = is_numeric( $req_value ) ? min( max( 1, intval( $req_value ) ), 100 ) : $default;
        break;
      case 'smoothing':
      case 'sqrt':
        $args[ $param ] = is_numeric( $req_value ) ? max( 1, intval( $req_value ) ) : $default;
        break;
      case 'alloc_quote':
        $args[ $param ] = is_numeric( $req_value ) ? trader_max( 0, floatstr( floatval( $req_value ) ) ) : $default;
        break;
      case 'takeout':
        $args[ $param ] = is_numeric( $req_value ) ? trader_max( 0, floatstr( floatval( $req_value ) ) ) : $default;
        break;
      case 'alloc_quote_fag_multiply':
        $args[ $param ] = ! empty( $req_value ) ? boolval( $req_value ) : $default;
        break;
      default:
        $args[ $param ] = is_numeric( $req_value ) ? floatstr( floatval( $req_value ) ) : $default;
    }
  }

  return $args;
}

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
    $balance_merged = new \Trader\Exchanges\Balance();
  } else {
    // Clone the object to prevent making changes to the original.
    $balance_merged = clone $balance;
  }
  if ( is_wp_error( $balance_exchange ) || ! $balance_exchange instanceof \Trader\Exchanges\Balance ) {
    $balance_exchange = new \Trader\Exchanges\Balance();
  }

  $args['takeout'] = ! empty( $args['takeout'] ) ? trader_max( 0, trader_min( $balance_exchange->amount_quote_total, $args['takeout'] ) ) : 0;
  $takeout_alloc   = $args['takeout'] > 0 ? trader_get_allocation( $args['takeout'], $balance_exchange->amount_quote_total ) : 0;

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
 * Retrieve allocation indicators.
 *
 * Subject to change: more indicators may be added in later versions.
 *
 * @param array $asset_cmc_arr  Array of historical asset objects of a single asset.
 * @param array $market_cap_ema Out. Smoothed Market Cap values.
 * @param int   $smoothing      The period to use for smoothing Market Cap.
 * @param float $interval_days  Rebalance period.
 */
function retrieve_allocation_indicators(
  array $asset_cmc_arr,
  &$market_cap_ema,
  int $smoothing = 14,
  float $interval_days = 7 )
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
 * @param int                     $sqrt       The nth root of Market Cap to use for allocation.
 */
function set_asset_allocations(
  $weighting,
  \Trader\Exchanges\Asset $asset,
  $market_cap,
  int $sqrt = 4 )
{
  $asset->allocation_rebl['default']  = trader_max( 0, bcmul( $weighting, pow( $market_cap, 1 / $sqrt ) ) );
  $asset->allocation_rebl['absolute'] = trader_max( 0, $weighting );
}


/**
 * Construct a ranked $balance with rebalanced allocation data.
 *
 * @param array $assets_weightings     User defined adjusted weighting factors per asset.
 * @param array $args {.
 *   @type int          $top_count                Amount of assets from the top market cap ranking.
 *   @type int          $smoothing                The period to use for smoothing Market Cap.
 *   @type int          $sqrt                     The square root of market cap to use in allocation calculation.
 *   @type float        $interval_days            Rebalance period.
 *   @type float|string $alloc_quote              Allocation to keep in quote currency. Default is 0.
 *   @type bool         $alloc_quote_fag_multiply Multiply quote allocation by Fear and Greed index. Default is true.
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
      'top_count'                => 30,
      'smoothing'                => 14,
      'sqrt'                     => 4,
      'interval_days'            => 7,
      'alloc_quote_fag_multiply' => false,
    )
  );

  $alloc_quote = ! empty( $args['alloc_quote'] ) ? bcdiv( trader_max( 0, trader_min( 100, $args['alloc_quote'] ) ), 100 ) : '0';
  $alloc_quote = $args['alloc_quote_fag_multiply'] ? bcmul( $alloc_quote, bcdiv( \Trader\Metrics\Alternative_Me::fag_index_current(), 100 ) ) : $alloc_quote;

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
      'sort'    => 'market_cap',
      'convert' => \Trader\Exchanges\Bitvavo::QUOTE_CURRENCY,
    ),
    $args['smoothing']
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
  foreach ( $cmc_latest as $index => $asset_cmc_arr ) {
    /**
     * Handle top count limit.
     */
    if ( $index + 1 >= $args['top_count'] ) {
      break;
    }

    /**
     * Skip if is stablecoin or weighting is set to zero.
     */
    if (
      in_array( 'stablecoin', $asset_cmc_arr[0]->tags, true ) ||
      ( array_key_exists( $asset_cmc_arr[0]->symbol, $assets_weightings ) && $assets_weightings[ $asset_cmc_arr[0]->symbol ] <= 0 )
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
     * Get indicator data.
     */
    retrieve_allocation_indicators(
      $asset_cmc_arr,
      $market_cap_ema,
      $args['smoothing'],
      $args['interval_days']
    );
    $asset_cmc_arr[0]->indicators                 = new \stdClass();
    $asset_cmc_arr[0]->indicators->market_cap_ema = end( $market_cap_ema );

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
      $assets_weightings[ $asset->symbol ] ?? 1,
      $asset,
      $asset->indicators->market_cap_ema,
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
