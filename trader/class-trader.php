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
  public static function get_asset_allocations( \Trader\Exchanges\Exchange $exchange, \Trader\Configuration $configuration ) : \Trader\Balance|\WP_Error
  {
    /**
     * List latest based on market cap (cache supported).
     */
    $cmc_latest = \Trader\Metrics\CoinMarketCap::list_latest(
      array(
        'sort'    => 'market_cap',
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
     * Initiate balance object, quote- and sideline asset.
     */
    $balance        = new \Trader\Balance();
    $asset_quote    = new \Trader\Asset( array( 'symbol' => \Trader\Exchanges\Bitvavo::QUOTE_CURRENCY ) );
    $asset_sideline =
      $asset_quote->symbol === $configuration->sideline_currency ||
      ! $exchange->is_tradable( $configuration->sideline_currency . '-' . \Trader\Exchanges\Bitvavo::QUOTE_CURRENCY )
      ? $asset_quote
      : new \Trader\Asset( array( 'symbol' => $configuration->sideline_currency ) );

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
     * Sort again based on EMA value.
     */
    usort( $cmc_latest, fn( $a, $b ) => $b[0]->indicators->market_cap_ema <=> $a[0]->indicators->market_cap_ema );
    // $cmc_latest = array_slice( $cmc_latest, 0, $configuration->top_count );

    /**
     * Loop to leave out certain non-relevant assets then retrieve candlesticks and indicators.
     */
    $counter = 0;
    foreach ( $cmc_latest as $asset_cmc_arr ) {
      $counter++;

      /**
       * Check if within top count.
       */
      if ( $counter > $configuration->top_count ) {
        break;
      }

      /**
       * Skip if one of the tags are excluded or weighting is set to zero.
       * Decrement counter if skipped.
       */
      $weighting_is_set = array_key_exists( $asset_cmc_arr[0]->symbol, $configuration->asset_weightings );
      $weighting        = $weighting_is_set ? (float) $configuration->asset_weightings[ $asset_cmc_arr[0]->symbol ] : 1;
      if (
        $weighting <= 0 ||
        ( ! $weighting_is_set && count( array_intersect( $configuration->excluded_tags, $asset_cmc_arr[0]->tags ) ) > 0 )
      ) {
        $counter--;
        continue;
      }

      /**
       * Skip sideline currency for now.
       */
      if ( $configuration->sideline_currency === $asset_cmc_arr[0]->symbol ) {
        continue;
      }

      /**
       * Skip if market doesn't exist on the exchange.
       */
      if ( ! $exchange->is_tradable( $asset_cmc_arr[0]->symbol . '-' . \Trader\Exchanges\Bitvavo::QUOTE_CURRENCY ) ) {
        continue;
      }

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
     * Scale for sideline allocation.
     */
    $alloc_sideline = ! empty( $configuration->alloc_sideline ) ? bcdiv( trader_max( 0, trader_min( 100, $configuration->alloc_sideline ) ), 100 ) : '0';
    foreach ( $total_allocations as $mode => $total_allocation ) {
      $asset_quote->allocation_rebl[ $mode ]    = 0; // at least initiate zero, takeout is determined in `merge_balance` but only for existing `allocation_rebl` values.
      $asset_sideline->allocation_rebl[ $mode ] = $alloc_sideline;
      $total_allocations[ $mode ]               = bcmul( bcdiv( '1', bcsub( 1, $alloc_sideline ) ), $total_allocation );
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
     * Finally, prepend quote- and sideline currency.
     */
    if ( $asset_sideline->symbol !== $asset_quote->symbol ) {
      array_unshift( $balance->assets, $asset_sideline );
    }
    array_unshift( $balance->assets, $asset_quote );

    // Then return $balance.
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
    $pool_selling = \Spatie\Async\Pool::create()->concurrency( 20 )->timeout( 299 );
    foreach ( $balance->assets as $asset ) {
      $pool_selling->add(
        function () use ( $exchange, $balance, $configuration, $asset, &$simulate, &$mode, &$result )
        {
          /**
           * Skip if is quote currency.
           */
          if ( $asset->symbol === \Trader\Exchanges\Bitvavo::QUOTE_CURRENCY ) {
            return;
          }

          $amount_quote_balanced = bcmul( $balance->amount_quote_total, $asset->allocation_rebl[ $mode ] ?? 0 );
          $diff                  = bcsub( $amount_quote_balanced, $asset->amount_quote );

          $amount_quote_to_sell = (float) $diff < 0 ? bcabs( $diff ) : 0;

          if ( (float) $amount_quote_to_sell >= \Trader\Exchanges\Bitvavo::MIN_QUOTE ) {
            $asset->rebl_sell_order = $exchange->sell_asset( $asset->symbol, $amount_quote_to_sell, $simulate, $asset->available, $asset->price );
            $result[]               =& $asset->rebl_sell_order;
          }
        }
      );
    }
    $pool_selling->wait();

    /**
     * Portfolio rebalancing: second loop to verify all sell orders are filled.
     * OPTIMIZALBLE IF USING ordersOpen() ENDPOINT INSTEAD => LESS API CALLS => BUT HAS A REQUEST RATE LIMITING WEIGHT OF 25 ..
     */
    $all_filled  = false;
    $fill_checks = 60; // multiplied by sleep(#) seconds ..
    while ( ! $simulate && ! $all_filled && $fill_checks > 0 ) {
      sleep( 1 ); // multiplied by $fill_checks ..
      $all_filled = true;

      /**
       * Run each sell order verification in an asyncronous thread when possible.
       */
      $pool_sell_verify = \Spatie\Async\Pool::create()->concurrency( 20 )->timeout( 299 );
      foreach ( $balance->assets as $asset ) {
        $pool_sell_verify->add(
          function () use ( $exchange, $asset, &$all_filled, &$fill_checks )
          {
            // Only (re)request non-filled orders.
            if (
              empty( $asset->rebl_sell_order['orderId'] ) ||
              substr( $asset->rebl_sell_order['status'], 0, 6 ) === 'cancel' || in_array( $asset->rebl_sell_order['status'], array( 'filled', 'expired', 'rejected' ), true )
            ) {
              return;
            }

            $all_filled = false;

            if ( $fill_checks <= 1 ) {
              // QUEUE THIS ASSET REBL INSTEAD OF ORDER CANCEL !!
              // HANDLE ERRORS / API RATE LIMIT !!
              $exchange->cancel_order( $asset->rebl_sell_order['market'], $asset->rebl_sell_order['orderId'] );
              $asset->rebl_sell_order['status'] = 'canceled';
            } else {
              // HANDLE ERRORS / API RATE LIMIT !!
              $asset->rebl_sell_order = array_merge( $asset->rebl_sell_order, $exchange->get_order( $asset->rebl_sell_order['market'], $asset->rebl_sell_order['orderId'] ) );
            }
          }
        );
      }
      $pool_sell_verify->wait();

      $fill_checks--;
    }

    /**
     * Portfolio rebalancing: third loop adding amounts to scale to available balance.
     * No need to pass takeout value as it is already applied to the passed $balance.
     */
    $balance      = ! $simulate ? \Trader\Balance::merge_balance( $balance, $exchange->get_balance()/*, $takeout = 0 */ ) : $balance;
    $to_buy_total = 0;
    foreach ( $balance->assets as $asset ) {
      $amount_quote_balanced = bcmul( $balance->amount_quote_total, $asset->allocation_rebl[ $mode ] ?? 0 );

      /**
       * If is quote currency, then only add to total buy value.
       * This is done to ensure sideline and takeout is taken care of.
       */
      if ( $asset->symbol === \Trader\Exchanges\Bitvavo::QUOTE_CURRENCY ) {
        $to_buy_total = bcadd( $to_buy_total, $amount_quote_balanced );
        continue;
      }

      $diff =
        bcsub( $amount_quote_balanced, ! $simulate ? $asset->amount_quote : bcsub( $asset->amount_quote, $asset->rebl_sell_order->amountQuote ?? 0 ) );

      /**
       * Only positive diffs can be buy orders.
       * Use minimum order value to filter out irrelevant dust.
       */
      if ( (float) $diff >= \Trader\Exchanges\Bitvavo::MIN_QUOTE ) {
        $asset->amount_quote_to_buy = $diff;
        $to_buy_total               = bcadd( $to_buy_total, $asset->amount_quote_to_buy );
      }
    }

    /**
     * Over-buy prevention 1/2: take the largest total value to calculate the allocation of the available balance with.
     */
    $to_buy_total = trader_max( $to_buy_total, $balance->assets[0]->available );

    /**
     * Portfolio rebalancing: fourth loop (re)buying assets.
     */
    $pool_buying = \Spatie\Async\Pool::create()->concurrency( 20 )->timeout( 299 );
    foreach ( $balance->assets as $asset ) {
      $pool_buying->add(
        function () use ( $exchange, $balance, $configuration, $asset, &$simulate, &$mode, &$result, &$to_buy_total )
        {
          /**
           * Skip:
           * - if is quote currency as it is the currency we buy with, not we can buy;
           * - or if no "to buy" amount is set.
           */
          if ( $asset->symbol === \Trader\Exchanges\Bitvavo::QUOTE_CURRENCY || empty( $asset->amount_quote_to_buy ) ) {
            return;
          }

          /**
           * Determine final amount quote to buy value.
           * Over-buy prevention 2/2: scale to available balance, where $to_buy_total >= $balance->assets[0]->available
           */
          $amount_quote_to_buy = ! $simulate
            ? bcmul( $balance->assets[0]->available, trader_get_allocation( $asset->amount_quote_to_buy, $to_buy_total ) )
            : $asset->amount_quote_to_buy;

          /**
           * Test whether the amount quote to buy is still above the minimum order value.
           */
          if ( (float) $amount_quote_to_buy >= \Trader\Exchanges\Bitvavo::MIN_QUOTE ) {
            $result[] = $asset->rebl_buy_order = $exchange->buy_asset( $asset->symbol, $amount_quote_to_buy, $simulate );
          }
        }
      );
    }
    $pool_buying->wait();

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
    $pool_automations = \Spatie\Async\Pool::create()->concurrency( 8 )->timeout( 299 );
    foreach ( \Trader\Configuration::get_automations() as $user_id => $configurations ) {
      $pool_automations->add(
        function () use ( $user_id, $configurations ) : array
        {
          $automations_triggered = array();

          $errors    = new \WP_Error();
          $timestamp = new DateTime();

          // Check user permission.
          if ( ! user_can( $user_id, 'trader_manage_portfolio' ) ) {
            return $automations_triggered;
          }

          $exchange         = new \Trader\Exchanges\Bitvavo( $user_id );
          $balance_exchange = $exchange->get_balance();

          if ( is_wp_error( $balance_exchange ) ) {
            $errors->merge_from( $balance_exchange );
            return array( array( $user_id, $timestamp, $errors, array() ) );
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

            $balance_allocated = self::get_asset_allocations( $exchange, $configuration );
            $balance           = \Trader\Balance::merge_balance( $balance_allocated, $balance_exchange, $configuration->takeout );

            if ( is_wp_error( $balance_allocated ) ) {
              $errors->merge_from( $balance_allocated );
              $automations_triggered[] = array( $user_id, $timestamp, $errors, array() );
              continue;
            }

            /**
             * Check if rebalance threshold is reached.
             */
            $threshold_reached = false;
            $diff_total        = '0';
            foreach ( $balance->assets as $asset ) {
              $allocation_rebl    = reset( $asset->allocation_rebl ) ?? 0;
              $amount_balanced    = bcmul( $allocation_rebl, $balance->amount_quote_total );
              $alloc_perc_current = bcmul( 100, $asset->allocation_current );
              $alloc_perc_rebl    = bcmul( 100, $allocation_rebl );
              $diff               = bcsub( $alloc_perc_current, $alloc_perc_rebl );
              $diff_abs           = bcabs( $diff );
              $diff_quote         = bcsub( $asset->amount_quote, $amount_balanced );

              if ( ! $threshold_reached ) {
                $threshold_reached =
                  // at least the minimum order amount should be reached
                  (float) bcabs( $diff_quote ) >= \Trader\Exchanges\Bitvavo::MIN_QUOTE
                  && (
                  // if configured rebalance threshold is reached
                  (float) $diff_abs >= (float) $configuration->rebalance_threshold
                  ||
                  // or if the asset should not be allocated at all
                  // phpcs:ignore WordPress.PHP.StrictComparisons
                  ( (float) $alloc_perc_current > (float) $alloc_perc_rebl && 0 == $alloc_perc_rebl ) );
              }

              $diff_total = bcadd( $diff_total, $diff_abs );
            }

            /**
             * Bail if rebalance threshold was not reached.
             */
            if ( ! $threshold_reached ) {
              continue;
            }

            /**
             * Bail if total diff is more than 2/3 of portfolio, in that case probably something is not right.
             *
             * NEED TO ADD EMAIL NOTIFICATION HERE !!
             */
            if ( (float) $diff_total > 66.67 ) {
              continue;
            }

            /**
             * Rebalance.
             */
            $trades = self::rebalance( $exchange, $balance, $configuration );
            foreach ( $trades as $index => $order ) {
              if ( ! empty( $order['errorCode'] ) ) {
                $errors->add(
                  $order['errorCode'] . '-' . $index,
                  sprintf( __( 'Exchange error %1$s %2$s: ', 'trader' ), $order['side'], $order['market'] ) . ( $order['error'] ?? __( 'An unknown error occured.', 'trader' ) ),
                  $order
                );
              } elseif ( ! in_array( $order['status'], array( 'new', 'filled' ), true ) ) {
                $errors->add(
                  'not_filled-' . $index,
                  sprintf( __( 'Order not filled %1$s %2$s: ', 'trader' ), $order['side'], $order['market'] ) . $order['status'],
                  $order
                );
              }
            }

            /**
             * On success, update timestamp of last rebalance.
             */
            $timestamp = new DateTime(); // refresh
            if ( count( $trades ) > 0 && ! $errors->has_errors() ) {
              $configuration->last_rebalance = $timestamp;
              $configuration->save( $user_id );
            }

            $automations_triggered[] = array( $user_id, $timestamp, $errors, $trades );
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
             * @param array     $trades    Trades executed.
             */
            do_action( 'trader_automation_triggered', $automation_triggered[0], $automation_triggered[1], $automation_triggered[2], $automation_triggered[3] );
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

    $pool_automations->wait();
  }
}
