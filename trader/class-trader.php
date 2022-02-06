<?php

defined( 'ABSPATH' ) || exit;


class Trader
{
  /**
   * Sets absolute asset allocation values into $asset.
   *
   * Subject to change: more indicators may be added in later versions.
   *
   * @param mixed         $weighting  User defined adjusted weighting factor, usually 1.
   * @param \Trader\Asset $asset      The asset object.
   * @param mixed         $market_cap Smoothed Market Cap value.
   * @param mixed         $nth_root   The nth root of Market Cap to use for allocation.
   */
  protected static function set_asset_allocations( $weighting, \Trader\Asset $asset, $market_cap, $nth_root )
  {
    $asset->allocation_rebl['default']  = trader_max( 0, bcmul( $weighting, pow( $market_cap, 1 / $nth_root ) ) );
    $asset->allocation_rebl['absolute'] = trader_max( 0, $weighting );
  }


  /**
   * Construct a ranked $balance with rebalanced allocation data.
   *
   * @param \Trader\Exchanges\Exchange $exchange      The exchange.
   * @param \Trader\Configuration      $configuration Rebalance configuration.
   *
   * @return \Trader\Balance|\WP_Error
   */
  public static function get_asset_allocations( \Trader\Exchanges\Exchange $exchange, \Trader\Configuration $configuration )
  {
    $alloc_quote = ! empty( $configuration->alloc_quote ) ? bcdiv( trader_max( 0, trader_min( 100, $configuration->alloc_quote ) ), 100 ) : '0';
    $alloc_quote = $configuration->alloc_quote_fag_multiply ? bcmul( $alloc_quote, bcdiv( \Trader\Metrics\Alternative_Me::fag_index_current(), 100 ) ) : $alloc_quote;

    /**
     * List latest based on market cap.
     */
    $cmc_latest = \Trader\Metrics\CoinMarketCap::list_latest(
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
    $balance             = new \Trader\Balance();
    $asset_quote         = new \Trader\Asset();
    $asset_quote->symbol = \Trader\Exchanges\Bitvavo::QUOTE_CURRENCY;

    /**
     * Loop through the asset ranking and retrieve Market Cap EMA.
     */
    foreach ( $cmc_latest as $asset_cmc_arr ) {
      /**
       * Get Market Cap EMA.
       */
      \Trader\Indicator::retrieve_market_cap_ema( $asset_cmc_arr, $market_cap_ema, $configuration->smoothing );
      $asset_cmc_arr[0]->indicators                 = new \stdClass();
      $asset_cmc_arr[0]->indicators->market_cap_ema = end( $market_cap_ema );
    }

    /**
     * Sort again based on EMA value then handle top count.
     */
    usort( $cmc_latest, fn( $a, $b ) => $b[0]->indicators->market_cap_ema <=> $a[0]->indicators->market_cap_ema );
    $cmc_latest = array_slice( $cmc_latest, 0, $configuration->top_count );

    /**
     * Loop to leave out certain non-relevant assets then retrieve candlesticks and indicators.
     */
    foreach ( $cmc_latest as $asset_cmc_arr ) {
      /**
       * Skip if is stablecoin, one of its tags are excluded or weighting is set to zero.
       */
      if (
        count( array_intersect( array_merge( array( 'stablecoin' ), $configuration->excluded_tags ), $asset_cmc_arr[0]->tags ) ) > 0 ||
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
      $candles = $exchange->candles(
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
         * ERROR HANDLING ? ! !
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
      $balance->assets[] = new \Trader\Asset( $asset_cmc_arr[0] );
    }

    /**
     * Loop to retrieve absolute asset allocations.
     */
    $total_allocations = array();
    foreach ( $balance->assets as $index => $asset ) {
      /**
       * Retrieve weighted asset allocation.
       */
      self::set_asset_allocations(
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
   * @param \Trader\Exchanges\Exchange $exchange      The exchange.
   * @param \Trader\Balance            $balance       Rebalanced portfolio.
   * @param \Trader\Configuration      $configuration Rebalance configuration.
   * @param bool                       $simulate      Perform a fake rebalance, e.g. to determine expected fee amount.
   *
   * @return array Order details.
   */
  public static function rebalance(
    \Trader\Exchanges\Exchange $exchange, \Trader\Balance $balance, \Trader\Configuration $configuration, bool $simulate = false ) : array
  {
    /**
     * Initiate array $result containing order data.
     */
    $result = ! $simulate ? $exchange->cancel_all_orders() : array();

    // back-compat
    $mode = $configuration->rebalance_mode ?? 'default';

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

      $diff            = bcsub( $amount_quote, $asset->amount_quote );
      $diff_float      = floatval( $diff );
      $diff_float_abs  = floatval( bcabs( $diff ) );
      $diff_spread_abs = trader_max( bcabs( bcsub( $amount_quote, bcmul( $asset->amount_quote, '0.99' ) ) ), bcabs( bcsub( $amount_quote, bcmul( $asset->amount_quote, '1.01' ) ) ) );

      $amount_quote_to_sell = 0;

      /**
       * REDUCE allocation ..
       */
      if ( $diff_float < 0 && $configuration->dust_limit <= $diff_float_abs ) {
        if ( $diff_float_abs < \Trader\Exchanges\Bitvavo::MIN_QUOTE ) {
          // REBUY REQUIRED ..
          $amount_quote_to_sell = bcadd( $diff_spread_abs, \Trader\Exchanges\Bitvavo::MIN_QUOTE );

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
      elseif ( $diff_float > 0 && $configuration->dust_limit <= $diff_float_abs ) {
        if ( $diff_float_abs < \Trader\Exchanges\Bitvavo::MIN_QUOTE + floatval( bcsub( $diff_spread_abs, $diff ) ) ) {
          // REBUY REQUIRED ..
          $amount_quote_to_sell = \Trader\Exchanges\Bitvavo::MIN_QUOTE;

          // $amount_quote_to_sell = bcdiv( $amount_quote_to_sell, bcsub( 1, \Trader\Exchanges\Bitvavo::TAKER_FEE ) ); // COMPENSATE FOR FEE IN SELL ORDER ..
          // $amount_quote_to_sell = bcmul( $amount_quote_to_sell, bcadd( 1, \Trader\Exchanges\Bitvavo::TAKER_FEE ) ); // COMPENSATE FOR FEE IN REBUY ORDER ..
        }
      }
      // else // ONLY BUYING MAY BE REQUIRED ..

      if ( floatval( $amount_quote_to_sell ) > 0 ) {
        $result[] = $asset->rebl_sell_order = $exchange->sell_asset( $asset->symbol, $amount_quote_to_sell, $simulate );
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
          substr( $asset->rebl_sell_order['status'], 0, 6 ) === 'cancel' || in_array( $asset->rebl_sell_order['status'], array( 'filled', 'expired', 'rejected' ), true )
        ) {
          continue;
        }

        $all_filled = false;

        if ( $fill_checks <= 1 ) {
          // QUEUE THIS ASSET REBL INSTEAD OF ORDER CANCEL !!
          $exchange->cancel_order( $asset->rebl_sell_order['market'], $asset->rebl_sell_order['orderId'] );
          $asset->rebl_sell_order['status'] = 'canceled';
        } else {
          $asset->rebl_sell_order = $exchange->get_order( $asset->rebl_sell_order['market'], $asset->rebl_sell_order['orderId'] );
        }
      }

      $fill_checks--;
    }

    /**
     * Portfolio rebalancing: third loop adding amounts to scale to available balance.
     * No need to pass takeout value as it is already applied to the passed $balance.
     */
    $config_without_takeout          = clone $configuration;
    $config_without_takeout->takeout = 0;
    $balance                         = ! $simulate ? \Trader\Balance::merge_balance( $balance, $exchange->get_balance(), $config_without_takeout ) : $balance;
    $to_buy_total                    = 0;
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
        $result[] = $asset->rebl_buy_order = $exchange->buy_asset( $asset->symbol, $amount_quote_to_buy, $simulate );
      }
    }

    return $result;
  }


  /**
   * Rebalance all portfolio's that are automated and in turn.
   *
   * @hooked trader_cronjob_hourly_filtered
   *
   * @return void
   */
  public static function do_automations()
  {
    /**
     * Run each automation in an asyncronous thread when possible.
     */
    $pool = \Spatie\Async\Pool::create()->concurrency( 20 )->timeout( 299 );

    foreach ( \Trader\Configuration::get_automations() as $user_id => $configurations ) {
      $pool->add(
        function () use ( $user_id, $configurations )
        {
          $automations_triggered = array();

          $errors    = new \WP_Error();
          $timestamp = new DateTime();

          // Check user permission.
          if ( ! user_can( $user_id, 'trader_manage_portfolio' ) ) {
            return array();
          }

          $bitvavo          = new \Trader\Exchanges\Bitvavo( $user_id );
          $balance_exchange = $bitvavo->get_balance();

          if ( is_wp_error( $balance_exchange ) ) {
            $errors->merge_from( $balance_exchange );
            return array( array( $user_id, $timestamp, $errors ) );
          }

          foreach ( $configurations as $configuration ) {
            /**
             * Only automation-enabled configurations should be passed, but can do no harm to double-check.
             */
            if ( ! $configuration->automation_enabled ) {
              continue;
            }

            /**
             * Check if rebalance interval has elapsed.
             * Using timestamp to hours then rounded, to compensate DateTime diff for small variations.
             */
            if ( null !== $configuration->last_rebalance && round( ( $timestamp->getTimestamp() - $configuration->last_rebalance->getTimestamp() ) / 60 / 60, 0, PHP_ROUND_HALF_DOWN ) < $configuration->interval_hours ) {
              continue;
            }

            $balance_allocated = self::get_asset_allocations( $bitvavo, $configuration );
            $balance           = \Trader\Balance::merge_balance( $balance_allocated, $balance_exchange, $configuration );

            if ( is_wp_error( $balance_allocated ) ) {
              $errors->merge_from( $balance_allocated );
              $automations_triggered[] = array( $user_id, $timestamp, $errors );
              continue;
            }

            /**
             * Check if rebalance threshold is reached.
             */
            if ( ! array_some(
              $balance->assets,
              function ( \Trader\Asset $asset ) use ( $balance, $configuration )
              {
                $allocation_rebl    = $asset->allocation_rebl[ $configuration->rebalance_mode ] ?? 0;
                $amount_balanced    = bcmul( $allocation_rebl, $balance->amount_quote_total );
                $alloc_perc_current = 100 * $asset->allocation_current;
                $alloc_perc_rebl    = 100 * $allocation_rebl;
                $diff               = $alloc_perc_current - $alloc_perc_rebl;
                $diff_quote         = $asset->amount_quote - $amount_balanced;

                return // at least the dust- and minimum order amount should be reached
                  abs( $diff_quote ) >= $configuration->dust_limit &&
                  abs( $diff_quote ) >= \Trader\Exchanges\Bitvavo::MIN_QUOTE
                  && (
                  // if configured rebalance threshold is reached
                  bcabs( $diff ) >= $configuration->rebalance_threshold
                  ||
                  // or if the asset should not be allocated at all
                  // phpcs:ignore WordPress.PHP.StrictComparisons
                  ( $alloc_perc_current > $alloc_perc_rebl && 0 == $alloc_perc_rebl )
                );
              }
            ) ) {
              continue;
            }

            /**
             * Rebalance.
             */
            foreach ( self::rebalance( $bitvavo, $balance, $configuration ) as $index => $order ) {
              if ( ! empty( $order['errorCode'] ) ) {
                $errors->add(
                  $order['errorCode'] . '-' . $index,
                  sprintf( __( 'Exchange error %1$s %2$s: ', 'trader' ), $order['side'], $order['market'] ) . ( $order['error'] ?? __( 'An unknown error occured.', 'trader' ) )
                );
              }
            }

            /**
             * On success, update timestamp of last rebalance.
             */
            $timestamp = new DateTime(); // refresh
            if ( ! $errors->has_errors() ) {
              $configuration->last_rebalance = $timestamp;
              $configuration->save( $user_id );
            }

            $automations_triggered[] = array( $user_id, $timestamp, $errors );
          }

          return $automations_triggered;
        }
      )->then(
        function ( array $automations_triggered )
        {
          foreach ( $automations_triggered as $automation_triggered ) {
            /**
             * Fires on each triggered automation.
             *
             * @param int       $user_id   The ID of the user to which the automation belongs.
             * @param DateTime  $timestamp The timestamp of when the automation was triggered.
             * @param \WP_Error $errors    Errors if any.
             */
            do_action( 'trader_automation_triggered', $automation_triggered[0], $automation_triggered[1], $automation_triggered[2] );
          }
        }
      )->catch(
        function ( Throwable $exception )
        {}
      )->timeout(
        function ()
        {}
      );
    }

    $pool->wait();
  }
}
