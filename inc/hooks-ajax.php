<?php

defined( 'ABSPATH' ) || exit;


/**
 * AJAX hook: get $deposit_history
 */
add_action(
  'wp_ajax_trader_get_deposit_history',
  function ()
  {
    check_ajax_referer( 'trader_ajax' );

    if ( ! current_user_can( 'trader_manage_portfolio' ) ) {
      wp_die( -1, 403 );
    }

    wp_send_json_success( \Trader\Exchanges\Bitvavo::current_user()->deposit_history() );
    wp_die();
  }
);

/**
 * AJAX hook: get $withdrawal_history
 */
add_action(
  'wp_ajax_trader_get_withdrawal_history',
  function ()
  {
    check_ajax_referer( 'trader_ajax' );

    if ( ! current_user_can( 'trader_manage_portfolio' ) ) {
      wp_die( -1, 403 );
    }

    wp_send_json_success( \Trader\Exchanges\Bitvavo::current_user()->withdrawal_history() );
    wp_die();
  }
);

/**
 * AJAX hook: get $balance_exchange
 */
add_action(
  'wp_ajax_trader_get_balance_exchange',
  function ()
  {
    check_ajax_referer( 'trader_ajax' );

    if ( ! current_user_can( 'trader_manage_portfolio' ) ) {
      wp_die( -1, 403 );
    }

    $errors = get_error_obj();

    $balance_exchange = \Trader\Exchanges\Bitvavo::current_user()->get_balance();

    if ( is_wp_error( $balance_exchange ) ) {
      $errors->merge_from( $balance_exchange );
    }

    if ( $errors->has_errors() ) {
      wp_send_json_error( $errors->get_all_error_data() );
      wp_die();
    }

    wp_send_json_success( $balance_exchange );
    wp_die();
  }
);

/**
 * AJAX hook: get $balance
 */
add_action(
  'wp_ajax_trader_get_balance',
  function ()
  {
    check_ajax_referer( 'trader_ajax' );

    if ( ! current_user_can( 'trader_manage_portfolio' ) ) {
      wp_die( -1, 403 );
    }

    $configuration = \Trader\Configuration::get_configuration_from_environment( $_POST['config'] ?? array() );

    $errors = get_error_obj();

    $balance_allocated = \Trader::get_asset_allocations( \Trader\Exchanges\Bitvavo::current_user(), $configuration );

    $balance_exchange = \Trader\Exchanges\Bitvavo::current_user()->get_balance();
    $balance          = \Trader::merge_balance( $balance_allocated, $balance_exchange, $configuration );

    if ( is_wp_error( $balance_allocated ) ) {
      $errors->merge_from( $balance_allocated );
    }
    if ( is_wp_error( $balance_exchange ) ) {
      $errors->merge_from( $balance_exchange );
    }

    if ( $errors->has_errors() ) {
      wp_send_json_error( $errors->get_all_error_data() );
      wp_die();
    }

    $expected_fee = 0;
    foreach ( \Trader::rebalance( \Trader\Exchanges\Bitvavo::current_user(), $balance, $configuration, true ) as $fake_order ) {
      $expected_fee = bcadd( $expected_fee, trader_ceil( $fake_order['feePaid'] ?? 0, 2 ) );
    }

    $balance->expected_fee  = number_format( trader_ceil( $expected_fee, 2 ), 2 );
    $balance->configuration = $configuration;

    wp_send_json_success( $balance );
    wp_die();
  }
);
