<?php

defined( 'ABSPATH' ) || exit;


/**
 * Update market cap history.
 */
add_action( 'trader_cronjob_hourly', array( '\Trader\Metrics\CoinMarketCap', 'list_latest' ) );
