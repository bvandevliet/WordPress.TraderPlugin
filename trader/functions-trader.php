<?php

namespace Trader;

defined( 'ABSPATH' ) || exit;


/**
 * Update an existing balance with actual values from an exchange balance.
 *
 * @param array $balance {
 *   Existing balance.
 *   @type object[] $assets {
 *     First entry is quote currency.
 *     @type string   $symbol
 *     @type string   $price
 *     @type string   $amount
 *     @type string   $amount_quote
 *     @type string   $allocation_current
 *   }
 *   @type string? $amount_quote_total
 * }
 * @param array $balance_exchange {
 *   Updated balance.
 *   @type object[] $assets {
 *     First entry is quote currency.
 *     @type string   $symbol
 *     @type string   $price
 *     @type string   $amount
 *     @type string   $amount_quote
 *     @type string   $allocation_current
 *   }
 *   @type string $amount_quote_total
 * }
 *
 * @return array $balance_merged {
 *   Merged balance.
 *   @type object[] $assets {
 *     First entry is quote currency.
 *     @type string   $symbol
 *     @type string   $price
 *     @type string   $amount
 *     @type string   $amount_quote
 *     @type string   $allocation_current
 *   }
 *   @type string $amount_quote_total
 * }
 */
function merge_balance( array $balance, array $balance_exchange = null ) : array
{
  /**
   * Get current allocations.
   */
  foreach ( $balance['assets'] as $asset ) { // pass by ref not required since var is object
    $asset->amount             = 0;
    $asset->amount_quote       = 0;
    $asset->allocation_current = 0;

    if ( ! empty( $balance_exchange['assets'] ) ) {
      foreach ( $balance_exchange['assets'] as $asset_exchange ) {
        if ( $asset_exchange->symbol === $asset->symbol ) {
          // we cannot use wp_parse_args() as we have to re-assign $asset which breaks the reference to the original object
          // $asset = (object) wp_parse_args( $asset_exchange, (array) $asset );
          foreach ( (array) $asset_exchange as $key => $data ) {
            $asset->$key = $data;
          }
          break;
        }
      }
    }
  }

  /**
   * Append missing allocations.
   */
  if ( ! empty( $balance_exchange['assets'] ) ) {
    foreach ( $balance_exchange['assets'] as $asset_exchange ) {
      foreach ( $balance['assets'] as $asset ) {
        if ( $asset_exchange->symbol === $asset->symbol ) {
          continue 2;
        }
      }

      $asset_exchange->allocation_rebl = array( 0 );
      $balance['assets'][]             = $asset_exchange;
    }
  }

  /**
   * Find quote currency entry, move it to the beginning of the array.
   *
   * ONLY BITVAVO EXCHANGE IS SUPPORTED YET !!
   */
  for ( $i = 0, $length = count( $balance['assets'] ); $i < $length; $i++ ) {
    if ( $balance['assets'][ $i ]->symbol === \Trader\Exchanges\Bitvavo::QUOTE_CURRENCY ) {
      $asset_quote = array_splice( $balance['assets'], $i, 1 )[0];
      array_unshift( $balance['assets'], $asset_quote );
      break;
    }
  }

  /**
   * Set total amount of quote currency and return $balance.
   */
  $balance['amount_quote_total'] = $balance_exchange['amount_quote_total'] ?? 0;
  return $balance;
}


/**
 * Retrieve allocation indicators.
 *
 * Subject to change: more indicators may be added in later versions.
 *
 * @param string        $symbol        Asset symbol.
 * @param float         $interval_days Rebalance period.
 * @param array[]|false $market_cap    Out. Historical price, free-float, current and realized market cap data.
 */
function retrieve_allocation_indicators(
  string $symbol,
  float $interval_days = 7,
  &$market_cap = null )
{
  // Market Cap.
  $market_cap = Metrics\CoinMetrics::market_cap( $symbol );
}


/**
 * Sets absolute asset allocation values into $asset.
 *
 * Subject to change: more indicators may be added in later versions.
 *
 * @param mixed  $weighting  User defined adjusted weighting factor, usually 1.
 * @param object $asset      The asset object.
 * @param array  $market_cap Historical price, free-float, current and realized market cap data.
 */
function set_asset_allocations(
  $weighting,
  object $asset, // pass by ref not required since var is object
  array $market_cap )
{
  $asset->allocation_rebl = array();

  $cap_ff = $market_cap[0]['CapMrktFFUSD'] ?? 0;

  $asset->allocation_rebl['default']  = trader_max( 0, bcmul( $weighting, pow( $cap_ff, 1 / 5 ) ) );
  $asset->allocation_rebl['absolute'] = trader_max( 0, $weighting );
}


/**
 * Construct a ranked $balance with rebalanced allocation data.
 *
 * @param array         $asset_weightings User defined adjusted weighting factors per asset.
 * @param float         $interval_days    Rebalance period.
 * @param integer       $top_count        Amount of assets from the top market cap ranking.
 * @param integer       $max_limit        Max amount of assets in portfolio.
 * @param object[]|null $cmc_latest       Provide a custom set of ranked assets. Optional.
 *
 * @return array $balance {
 *   @type object[] $assets {
 *     @type string   $symbol
 *     @type string   $price
 *     @type array[]  $allocation_rebl {
 *       @type string => string  $mode => $allocation
 *     }
 *   }
 * }
 */
function get_asset_allocations(
  array $asset_weightings = array(),
  float $interval_days = 7,
  int $top_count = 30,
  int $max_limit = 20,
  $cmc_latest = null ) : array
{
  /**
   * Initiate object[] $assets.
   */
  $assets = array();

  /**
   * List latest based on market cap.
   */
  $cmc_latest = $cmc_latest ?? Metrics\CoinMarketCap::list_latest(
    array(
      'limit'   => $top_count,
      'convert' => Exchanges\Bitvavo::QUOTE_CURRENCY,
    )
  );

  /**
   * Bail if the API request may have failed.
   *
   * ERROR HANDLING !!
   */
  if ( empty( $cmc_latest ) || ! is_array( $cmc_latest->data ) ) {
    return compact( 'assets' );
  }

  /**
   * Loop through the asset ranking and retrieve candlesticks and indicators.
   */
  foreach ( $cmc_latest->data as $asset ) {
    /**
     * Skip if is stablecoin or weighting is set to zero.
     */
    if ( in_array( 'stablecoin', $asset->tags, true ) || ( array_key_exists( $asset->symbol, $asset_weightings ) && $asset_weightings[ $asset->symbol ] == 0 ) ) {
      continue;
    }

    /**
     * Define market.
     *
     * ONLY BITVAVO EXCHANGE IS SUPPORTED YET !!
     */
    $market = $asset->symbol . '-' . Exchanges\Bitvavo::QUOTE_CURRENCY;

    /**
     * Get candlesticks from exchange.
     */
    $candles = Exchanges\Bitvavo::candles(
      $market,
      '4h',
      array(
        'limit' => Exchanges\Bitvavo::CANDLES_LIMIT,
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
     * Set current ticker price.
     */
    $asset->price = Exchanges\Bitvavo::get_instance()->tickerPrice( array( 'market' => $market ) )['price'];

    /**
     * Store indicator data.
     */
    $asset->indicators = new \stdClass();
    retrieve_allocation_indicators(
      $asset->symbol,
      $interval_days,
      $asset->indicators->market_cap
    );

    /**
     * Append to global array for next loop(s).
     */
    $assets[] = $asset;
  }

  /**
   * Loop to retrieve absolute asset allocations.
   */
  $total_allocations = array();
  foreach ( $assets as $index => $asset ) { // pass by ref not required since var is object
    /**
     * Retrieve weighted asset allocation.
     */
    set_asset_allocations(
      $asset_weightings[ $asset->symbol ] ?? 1,
      $asset,
      array( 0 => array( 'CapMrktFFUSD' => ( (array) $asset->quote )[ Exchanges\Bitvavo::QUOTE_CURRENCY ]->market_cap ) )
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

    /**
     * Break if maximum allowed asset amount for portfolio is reached.
     */
    $max_limit--;
    if ( $max_limit === 0 ) {
      $assets = array_slice( $assets, 0, $index + 1 );
      break;
    }
  }

  /**
   * Loop to calculate relative asset allocations.
   */
  foreach ( $assets as $asset ) { // pass by ref not required since var is object
    foreach ( $asset->allocation_rebl as $mode => &$allocation ) {
      $allocation = trader_get_allocation( $allocation, $total_allocations[ $mode ] );
    }
  }

  /**
   * Sort based on allocation.
   */
  usort(
    $assets,
    function ( $a, $b )
    {
      return reset( $b->allocation_rebl ) <=> reset( $a->allocation_rebl );
    }
  );

  /**
   * Finally, return $balance.
   */
  return compact( 'assets' );
}


/**
 * Perform a portfolio rebalance.
 *
 * @param array  $balance {
 *   Portfolio.
 *   @type object[] $assets {
 *     @type string   $symbol
 *     @type string   $price
 *     @type string   $amount
 *     @type string   $amount_quote
 *     @type string   $allocation_current
 *     @type array[]  $allocation_rebl {
 *       @type string => string  $mode => $allocation
 *     }
 *   }
 *   @type string $amount_quote_total
 * }
 * @param string $mode Rebalance mode as defined by allocation in $balance['assets'][$i]->allocation_rebl[$mode]
 * @param array  $args {
 *   @type int $args['dust_limit'] [optional] Minimum required allocation difference in quote currency.
 * }
 *
 * @return array Order details.
 */
function rebalance( array &$balance, string $mode = null, array $args = array() ) : array
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
  $result = Exchanges\Bitvavo::cancel_all_orders();

  /**
   * Portfolio rebalancing: first loop placing sell orders.
   */
  foreach ( $balance['assets'] as $asset ) {
    /**
     * Skip if is quote currency.
     */
    if ( $asset->symbol === \Trader\Exchanges\Bitvavo::QUOTE_CURRENCY ) {
      continue;
    }

    $amount_quote = bcmul( $balance['amount_quote_total'], $asset->allocation_rebl[ $mode ] ?? 0 );

    $diff = bcsub( $amount_quote, $asset->amount_quote );

    $amount_quote_to_sell = 0;

    /**
     * RECUDE allocation ..
     */
    if ( floatval( $diff ) <= -$args['dust_limit'] ) {
      if ( floatval( bcabs( $diff ) ) < Exchanges\Bitvavo::MIN_QUOTE ) {
        // REBUY REQUIRED ..
        $amount_quote_to_sell = bcadd( bcabs( $diff ), Exchanges\Bitvavo::MIN_QUOTE + 1 );

        $amount_quote_to_sell = bcdiv( $amount_quote_to_sell, bcsub( 1, Exchanges\Bitvavo::TAKER_FEE ) ); // COMPENSATE FOR FEE IN SELL ORDER ..
        // $amount_quote_to_sell = bcmul( $amount_quote_to_sell, bcadd( 1, Exchanges\Bitvavo::TAKER_FEE ) ); // COMPENSATE FOR FEE IN REBUY ORDER ..
      } else {
        // NOTHING TO REBUY ..
        $amount_quote_to_sell = bcabs( $diff );
      }
    }

    /**
     * INCREASE allocation ..
     */
    elseif ( floatval( $diff ) >= $args['dust_limit'] && floatval( $diff ) < Exchanges\Bitvavo::MIN_QUOTE + 1 ) {
      // REBUY REQUIRED ..
      $amount_quote_to_sell = Exchanges\Bitvavo::MIN_QUOTE;

      // $amount_quote_to_sell = bcdiv( $amount_quote_to_sell, bcsub( 1, Exchanges\Bitvavo::TAKER_FEE ) ); // COMPENSATE FOR FEE IN SELL ORDER ..
      // $amount_quote_to_sell = bcmul( $amount_quote_to_sell, bcadd( 1, Exchanges\Bitvavo::TAKER_FEE ) ); // COMPENSATE FOR FEE IN REBUY ORDER ..
    }
    // else // ONLY BUYING MAY BE REQUIRED ..

    if ( floatval( $amount_quote_to_sell ) > 0 ) {
      $result[] = $asset->rebl_sell_order = Exchanges\Bitvavo::sell_asset( $asset->symbol, $amount_quote_to_sell );
    }
  }

  /**
   * Portfolio rebalancing: second loop to verify all sell orders are filled.
   * OPTIMIZALBLE IF USING ordersOpen() ENDPOINT INSTEAD => LESS API CALLS => BUT HAS A REQUEST RATE LIMITING WEIGHT OF 25 ..
   */
  $all_filled  = false;
  $fill_checks = 60; // multiply by sleep seconds ..
  while ( ! $all_filled && $fill_checks > 0 ) {
    sleep( 1 ); // multiply by $fill_checks ..

    $all_filled = true;
    foreach ( $balance['assets'] as $asset ) {
      // Only (re)request non-filled orders.
      if (
        empty( $asset->rebl_sell_order['orderId'] ) ||
        substr( $asset->rebl_sell_order['status'], 0, 6 ) === 'cancel' || in_array( $asset->rebl_sell_order['status'], array( 'filled', 'expired', 'rejected' ) )
      ) {
        continue;
      }

      $all_filled = false;

      $market = $asset->symbol . '-' . Exchanges\Bitvavo::QUOTE_CURRENCY;

      if ( $fill_checks <= 1 ) { // QUEUE THIS ASSET REBL INSTEAD OF ORDER CANCEL !!
        Exchanges\Bitvavo::cancel_order( $market, $asset->rebl_sell_order['orderId'] );
      }

      $asset->rebl_sell_order = Exchanges\Bitvavo::get_order( $market, $asset->rebl_sell_order['orderId'] );
    }

    $fill_checks--;
  }

  /**
   * Portfolio rebalancing: third loop adding amounts to scale to available balance.
   */
  $balance      = merge_balance( $balance, Exchanges\Bitvavo::get_balance() );
  $to_buy_total = 0;
  foreach ( $balance['assets'] as $asset ) {
    /**
     * Skip if is quote currency.
     */
    if ( $asset->symbol === \Trader\Exchanges\Bitvavo::QUOTE_CURRENCY ) {
      continue;
    }

    $amount_quote = bcmul( $balance['amount_quote_total'], $asset->allocation_rebl[ $mode ] ?? 0 );

    $asset->amount_quote_to_buy = bcsub( $amount_quote, $asset->amount_quote );

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
  foreach ( $balance['assets'] as $asset ) {
    /**
     * Skip if is quote currency.
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

    $amount_quote_to_buy = bcmul( $balance['assets'][0]['available'], trader_get_allocation( $asset->amount_quote_to_buy, $to_buy_total ) );

    if ( floatval( $amount_quote_to_buy ) >= Exchanges\Bitvavo::MIN_QUOTE ) {
      $result[] = $asset->rebl_buy_order = Exchanges\Bitvavo::buy_asset( $asset->symbol, $amount_quote_to_buy );
    }
  }

  return $result;
}
