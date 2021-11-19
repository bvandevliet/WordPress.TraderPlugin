<?php

defined( 'ABSPATH' ) || exit;


/**
 * Dynamic block Portfolio output.
 *
 * @param [type] $block_attributes
 * @param [type] $content
 *
 * @return void
 */
function trader_dynamic_block_portfolio_cb( $block_attributes, $content )
{
  /**
   * Check user capabilities.
   */
  $current_user = wp_get_current_user();
  if ( ! current_user_can( 'trader_manage_portfolio' ) ) {
    return;
  }

  $configuration = \Trader\Configuration::get_args_from_request_params();

  $errors = get_error_obj();

  $balance_allocated = \Trader::get_asset_allocations( $configuration );

  if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
    if ( isset( $_POST['action'] )
      && isset( $_POST['do-portfolio-rebalance-nonce'] ) && wp_verify_nonce( $_POST['do-portfolio-rebalance-nonce'], 'portfolio-rebalance-user_' . $current_user->ID )
    ) {
      switch ( $_POST['action'] ) {

        case 'do-portfolio-rebalance':
          $balance_exchange = \Trader\Exchanges\Bitvavo::get_balance();
          $balance          = \Trader::merge_balance( $balance_allocated, $balance_exchange, $configuration );

          if ( is_wp_error( $balance_allocated ) ) {
            $errors->merge_from( $balance_allocated );
          }
          if ( is_wp_error( $balance_exchange ) ) {
            $errors->merge_from( $balance_exchange );
          }

          if ( is_wp_error( $balance_allocated ) || is_wp_error( $balance_exchange ) ) {
            break;
          }

          foreach ( \Trader::rebalance( $balance, 'default', $configuration ) as $index => $order ) {
            if ( ! empty( $order['errorCode'] ) ) {
              $errors->add(
                $order['errorCode'] . '-' . $index,
                sprintf( __( 'Exchange error %1$s %2$s: ', 'trader' ), $order['side'], $order['market'] ) . ( $order['error'] ?? __( 'An unknown error occured.', 'trader' ) )
              );
            }
          }

          break;

        case 'sell-whole-portfolio':
          foreach ( \Trader\Exchanges\Bitvavo::sell_whole_portfolio() as $index => $order ) {
            if ( ! empty( $order['errorCode'] ) ) {
              $errors->add(
                $order['errorCode'] . '-' . $index,
                sprintf( __( 'Exchange error %1$s %2$s: ', 'trader' ), $order['side'], $order['market'] ) . ( $order['error'] ?? __( 'An unknown error occured.', 'trader' ) )
              );
            }
          }

          break;
      }
    } else {
      $errors->add( 'submit_error', __( 'Action failed.', 'trader' ) );
    }
  }

  $balance_exchange = \Trader\Exchanges\Bitvavo::get_balance();
  $balance          = \Trader::merge_balance( $balance_allocated, $balance_exchange, $configuration );

  if ( is_wp_error( $balance_allocated ) ) {
    $errors->merge_from( $balance_allocated );
  }
  if ( is_wp_error( $balance_exchange ) ) {
    $errors->merge_from( $balance_exchange );
  }

  ob_start();

  if ( $errors->has_errors() ) :
    ?><div class="error"><p><?php echo implode( "</p>\n<p>", $errors->get_error_messages() ); ?></p></div>
    <?php
  endif;

  trader_echo_balance_summary( $balance_exchange );

  trader_echo_portfolio( $balance );

  trader_echo_onchain_summary();

  if ( ! is_wp_error( $balance_allocated ) && ! is_wp_error( $balance_exchange ) ) :
    $expected_fee = 0;
    foreach ( \Trader::rebalance( $balance, 'default', $configuration, true ) as $fake_order ) {
      $expected_fee = bcadd( $expected_fee, trader_ceil( $fake_order['feePaid'] ?? 0, 2 ) );
    }
    $expected_fee = number_format( trader_ceil( $expected_fee, 2 ), 2 );

    /**
     * WIP, WILL BE FURTHER IMPROVED FOR UX !!
     */
    ?>
    <form action="<?php echo esc_attr( get_permalink() ); ?>" method="get">
      <!-- <p class="form-row">
        <label title="<?php esc_attr_e( 'Rebalance interval in days.', 'trader' ); ?>">
          <?php esc_html_e( 'Interval days', 'trader' ); ?> [n]&nbsp;
          <span style="float:right;"><?php echo esc_html( sprintf( __( '(current = %s)', 'trader' ), $configuration->interval_hours ) ); ?></span>
          <input type="number" min="1" class="input-number" name="interval_hours" value="<?php echo esc_attr( $configuration->interval_hours ); ?>" />
        </label>
      </p> -->
      <fieldset class="wp-block-columns">
        <div class="wp-block-column">
          <p class="form-row form-row-wide">
            <label title="<?php esc_attr_e( 'Max amount of assets from CoinMarketCap listing.', 'trader' ); ?>">
              <?php esc_html_e( 'Top count', 'trader' ); ?> [n]&nbsp;
              <span style="float:right;"><?php echo esc_html( sprintf( __( '(current = %s)', 'trader' ), $configuration->top_count ) ); ?></span>
              <input type="number" min="1" max="100" class="input-number" name="top_count" value="<?php echo esc_attr( $configuration->top_count ); ?>" />
            </label>
          </p>
          <div class="clear"></div>
          <p class="form-row form-row-first">
            <label title="<?php esc_attr_e( 'Exponential Moving Average period of Market Cap, to smooth out volatility.', 'trader' ); ?>">
              <?php esc_html_e( 'Smoothing [days]', 'trader' ); ?>&nbsp;
              <span style="float:right;"><?php echo esc_html( sprintf( __( '(current = %s)', 'trader' ), $configuration->smoothing ) ); ?></span>
              <input type="number" min="1" class="input-number" name="smoothing" value="<?php echo esc_attr( $configuration->smoothing ); ?>" />
            </label>
          </p>
          <p class="form-row form-row-last">
            <label title="<?php esc_attr_e( 'nth root of Market Cap EMA, to dampen the effect an individual asset has on the portfolio.', 'trader' ); ?>">
              <?php esc_html_e( 'nth root ^(1/[n])', 'trader' ); ?>&nbsp;
              <span style="float:right;"><?php echo esc_html( sprintf( __( '(current = %s)', 'trader' ), $configuration->nth_root ) ); ?></span>
              <input type="number" min="1" class="input-number" name="nth_root" value="<?php echo esc_attr( $configuration->nth_root ); ?>" />
            </label>
          </p>
          <div class="clear"></div>
        <!-- </div>
        <div class="wp-block-column"> -->
          <p class="form-row form-row-first">
            <label for="alloc_quote" title="<?php echo esc_attr( sprintf( __( 'Allocate a given percentage to quote currency \'%s\'.', 'trader' ), \Trader\Exchanges\Bitvavo::QUOTE_CURRENCY ) ); ?>">
              <?php esc_html_e( 'Quote allocation', 'trader' ); ?> [%]&nbsp;
            </label>
            <label style="float:right;" title="<?php echo esc_attr_e( 'Multiply quote allocation by Fear and Greed index.', 'trader' ); ?>">
              <?php echo esc_html( sprintf( __( '&#8226;~.%s', 'trader' ), \Trader\Metrics\Alternative_Me::fag_index_current() ) ); ?>
              <input type="checkbox" name="alloc_quote_fag_multiply" <?php checked( $configuration->alloc_quote_fag_multiply ); ?> />
            </label>
            <input id="alloc_quote" type="number" min="0" class="input-number" name="alloc_quote" value="<?php echo esc_attr( $configuration->alloc_quote ); ?>" />
          </p>
          <p class="form-row form-row-last">
            <label title="<?php echo esc_attr( sprintf( __( 'Takeout a given amount of quote currency \'%s\'.', 'trader' ), \Trader\Exchanges\Bitvavo::QUOTE_CURRENCY ) ); ?>">
              <?php esc_html_e( 'Quote takeout', 'trader' ); ?> [€]&nbsp;
              <span style="float:right;"><?php echo esc_html( sprintf( __( '(current = %s)', 'trader' ), $configuration->takeout ) ); ?></span>
              <input type="number" min="0" class="input-number" name="takeout" value="<?php echo esc_attr( $configuration->takeout ); ?>" />
            </label>
          </p>
        </div>
        <div class="wp-block-column"></div>
      </fieldset>
      <p>
        <button type="submit" class="button"><?php esc_html_e( 'Refresh', 'trader' ); ?></button>
      </p>
    </form>
    <form action="<?php echo esc_attr( get_permalink() ); ?>" method="post">
      <?php wp_nonce_field( 'portfolio-rebalance-user_' . $current_user->ID, 'do-portfolio-rebalance-nonce' ); ?>
      <input type="hidden" name="top_count" value="<?php echo esc_attr( $configuration->top_count ); ?>" />
      <input type="hidden" name="smoothing" value="<?php echo esc_attr( $configuration->smoothing ); ?>" />
      <input type="hidden" name="nth_root" value="<?php echo esc_attr( $configuration->nth_root ); ?>" />
      <input type="hidden" name="alloc_quote" value="<?php echo esc_attr( $configuration->alloc_quote ); ?>" />
      <input type="hidden" name="alloc_quote_fag_multiply" value="<?php echo esc_attr( $configuration->alloc_quote_fag_multiply ); ?>" />
      <input type="hidden" name="takeout" value="<?php echo esc_attr( $configuration->takeout ); ?>" />
      <p style="display:inline-block;">
        <button type="submit" class="button trader-action-zone" name="action" value="do-portfolio-rebalance"
        onclick="return confirm('<?php esc_attr_e( 'This will perform a portfolio rebalance.\nAre you sure?', 'trader' ); ?>');"><?php echo esc_html( sprintf( __( 'Rebalance now (fee ≈ € %s)', 'trader' ), $expected_fee ) ); ?></button>
      </p>
      <p style="display:inline-block;">
        <button type="submit" class="button trader-danger-zone" name="action" value="sell-whole-portfolio"
        onclick="return confirm('<?php esc_attr_e( 'This will sell all your assets.\nAre you sure?', 'trader' ); ?>');"><?php esc_html_e( 'Sell whole portfolio', 'trader' ); ?></button>
      </p>
    </form>

    <?php
  endif;

  return ob_get_clean();
}
