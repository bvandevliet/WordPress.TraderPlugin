<?php

defined( 'ABSPATH' ) || exit;


/**
 * Update market cap history. Must be called prior to automations to update database and set cache.
 */
add_action( 'trader_cronjob_hourly_filtered', array( '\Trader\Metrics\CoinMarketCap', 'list_latest' ), 9 );

/**
 * Rebalance all portfolio's that are automated and in turn.
 */
add_action( 'trader_cronjob_hourly_filtered', array( '\Trader', 'do_automations' )/*, 10*/ );

/**
 * Filter cronjob execution.
 */
add_action(
  'trader_cronjob_hourly',
  function ()
  {
    /**
     * Bail if event was not triggered by the system task scheduler.
     */
    if ( ! empty( get_option( 'trader_disable_wp_cron', false ) ) && ( $_SERVER['REMOTE_ADDR'] ?? '0' ) !== ( $_SERVER['SERVER_ADDR'] ?? -1 ) ) {
      return;
    }

    do_action( 'trader_cronjob_hourly_filtered' );
  }
);
