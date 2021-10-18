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

  $assets_weightings = get_user_meta( $current_user->ID, 'asset_weightings', true );
  $assets_weightings = is_array( $assets_weightings ) ? $assets_weightings : array();

  $interval_days = isset( $_GET['interval_days'] ) ? trader_max( 1, floatstr( intval( $_GET['interval_days'] ) ) ) : 7;
  $top_count     = isset( $_GET['top_count'] ) ? trader_max( 1, floatstr( intval( $_GET['top_count'] ) ) ) : 30;
  $max_limit     = isset( $_GET['max_limit'] ) ? trader_max( 1, floatstr( intval( $_GET['max_limit'] ) ) ) : 20;
  $args          = array(
    'alloc_quote' => isset( $_GET['alloc_quote'] ) ? trader_max( 0, floatstr( floatval( $_GET['alloc_quote'] ) ) ) : '0',
    'takeout'     => isset( $_GET['takeout'] ) ? trader_max( 0, floatstr( floatval( $_GET['takeout'] ) ) ) : '0',
  );

  $errors = new WP_Error();

  if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
    if ( isset( $_POST['action'] )
      && isset( $_POST['do-portfolio-rebalance-nonce'] ) && wp_verify_nonce( $_POST['do-portfolio-rebalance-nonce'], 'portfolio-rebalance-user_' . $current_user->ID )
    ) {
      switch ( $_POST['action'] ) {

        case 'do-portfolio-rebalance':
          $balance_exchange  = \Trader\Exchanges\Bitvavo::get_balance();
          $balance_allocated = \Trader\get_asset_allocations( $assets_weightings, $args, $interval_days, $top_count, $max_limit );
          $balance           = \Trader\merge_balance( $balance_allocated, $balance_exchange, $args );

          foreach ( \Trader\rebalance( $balance ) as $index => $order ) {
            if ( ! empty( $order['errorCode'] ) ) {
              $errors->add( $order['errorCode'] . '-' . $index, $order['error'] ?? __( 'An unknown error occured.', 'trader' ) );
            }
          }

          break;

        case 'sell-whole-portfolio':
          foreach ( \Trader\Exchanges\Bitvavo::sell_whole_portfolio() as $index => $order ) {
            if ( ! empty( $order['errorCode'] ) ) {
              $errors->add( $order['errorCode'] . '-' . $index, $order['error'] ?? __( 'An unknown error occured.', 'trader' ) );
            }
          }

          break;
      }
    } else {
      $errors->add( 'submit_error', __( 'Action failed.', 'trader' ) );
    }
  }

  ob_start();
  echo '<pre><code>';

  $balance_exchange = \Trader\Exchanges\Bitvavo::get_balance();
  if ( ! is_wp_error( $balance_exchange ) ) {
    $balance_allocated = \Trader\get_asset_allocations( $assets_weightings, $args, $interval_days, $top_count, $max_limit );
    $balance           = \Trader\merge_balance( $balance_allocated, $balance_exchange, $args );

    $deposit_history    = \Trader\Exchanges\Bitvavo::deposit_history();
    $withdrawal_history = \Trader\Exchanges\Bitvavo::withdrawal_history();

    $moneyflow_now = bcadd( $balance->amount_quote_total, $withdrawal_history['total'] );

    echo ''
       . '    DEPOSIT TOTAL (i)         : €' . str_pad( number_format( $deposit_history['total'], 2 ), 10, ' ', STR_PAD_LEFT ) . '<br>'
       . ' WITHDRAWAL TOTAL (o)         : €' . str_pad( number_format( $withdrawal_history['total'], 2 ), 10, ' ', STR_PAD_LEFT ) . '<br>'
       . '      BALANCE NOW (b)         : €' . str_pad( number_format( $balance->amount_quote_total, 2 ), 10, ' ', STR_PAD_LEFT ) . '<br>'
       . '    MONEYFLOW NOW (B=o+b)     : €' . str_pad( number_format( $moneyflow_now, 2 ), 10, ' ', STR_PAD_LEFT ) . '<br>'
       . '       GAIN TOTAL (B-i)       : €' . str_pad( number_format( bcsub( $moneyflow_now, $deposit_history['total'] ), 2 ), 10, ' ', STR_PAD_LEFT ) . '<br>'
       . '       GAIN TOTAL (B/i-1)     :  ' . str_pad( trader_get_gain_perc( $moneyflow_now, $deposit_history['total'] ), 10, ' ', STR_PAD_LEFT ) . '%' . '<br>';
  } else {
    // $errors->merge_from( $balance_exchange );
    foreach ( $balance_exchange->get_error_codes() as $code ) {
      foreach ( $balance_exchange->get_error_messages( $code ) as $error_message ) {
        $errors->add( 'exchange-' . $code, __( 'Exchange error: ', 'trader' ) . $error_message );
      }
    }
  }

  if ( isset( $errors ) && is_wp_error( $errors ) && $errors->has_errors() ) :
    ?><div class="error"><p><?php echo implode( "</p>\n<p>", $errors->get_error_messages() ); ?></p></div>
    <?php
  endif;

  $market_cap = \Trader\Metrics\CoinMetrics::market_cap( 'BTC' );
  if ( false !== $market_cap[0]['time'] ) {
    $nupl_mvrvz = \Trader\Metrics\CoinMetrics::nupl_mvrvz( $market_cap );
    $fag_index  = \Trader\Metrics\Alternative_Me::fag_index()[0]->value;

    echo '<br>'
       . 'BTC top is reached when ..<br>';
    echo '<a href="https://www.lookintobitcoin.com/charts/relative-unrealized-profit--loss/"'
       . 'target="_blank" rel="noopener noreferrer"'
       . '>nupl</a>         :  ' . number_format( $nupl_mvrvz['nupl'], 2 ) . ' >=  0.75 and falling<br>';
    echo '<a href="https://www.lookintobitcoin.com/charts/mvrv-zscore/"'
       . 'target="_blank" rel="noopener noreferrer"'
       . '>mvrv_z_score</a> :  ' . number_format( $nupl_mvrvz['mvrvz'], 2 ) . ' >=  9.00 and falling<br>';
    echo '<a href="https://alternative.me/crypto/fear-and-greed-index/"'
       . 'target="_blank" rel="noopener noreferrer"'
       . '>fag_index</a>    : ' . number_format( $fag_index, 0 ) . '    >= 80    and falling<br>';
  } else {
    echo '<br>'
       . 'Something went wrong while fetching onchain indicators ..<br>';
  }

  if ( ! is_wp_error( $balance_exchange ) ) :

    echo '<br> ASSET   NOW           NOW  REBL         REBL';
    foreach ( $balance->assets as $asset ) {
      echo '<br>'
        . str_pad( $asset->symbol, 6, ' ', STR_PAD_LEFT ) . ':'
        . '  €' . str_pad( number_format( $asset->amount_quote, 2 ), 8, ' ', STR_PAD_LEFT )
        . str_pad( number_format( 100 * $asset->allocation_current, 2 ), 7, ' ', STR_PAD_LEFT ) . '%'
        . '  €' . str_pad( number_format( bcmul( reset( $asset->allocation_rebl ), $balance->amount_quote_total ), 2 ), 8, ' ', STR_PAD_LEFT )
        . str_pad( number_format( 100 * reset( $asset->allocation_rebl ), 2 ), 7, ' ', STR_PAD_LEFT ) . '%';
    }
    echo '<br>';

  endif;
  echo '</code></pre>';

  if ( ! is_wp_error( $balance_exchange ) ) :

    /**
     * WIP, WILL BE FURTHER IMPROVED FOR UX !!
     */
    $rebl_query = array_merge( compact( /*'interval_days', */'top_count', 'max_limit' ), $args );
    ?>
    <form action="<?php echo esc_attr( get_permalink() ); ?>" method="get">
      <!-- <p class="form-row">
        <label><?php esc_html_e( 'Interval days', 'trader' ); ?> [n] <span class="required">*</span>
        <input type="number" min="1" class="input-number" name="interval_days" value="<?php echo esc_attr( $rebl_query['interval_days'] ); ?>" />
        </label>
      </p> -->
      <p class="form-row form-row-first">
        <label><?php esc_html_e( 'Top count', 'trader' ); ?> [n] <span class="required">*</span>
        <input type="number" min="1" class="input-number" name="top_count" value="<?php echo esc_attr( $rebl_query['top_count'] ); ?>" />
        </label>
      </p>
      <p class="form-row form-row-last">
        <label><?php esc_html_e( 'Max limit', 'trader' ); ?> [n] <span class="required">*</span>
        <input type="number" min="1" class="input-number" name="max_limit" value="<?php echo esc_attr( $rebl_query['max_limit'] ); ?>" />
        </label>
      </p>
      <div class="clear"></div>
      <p class="form-row form-row-first">
        <label><?php esc_html_e( 'Allocation quote', 'trader' ); ?> [%] <span class="required">*</span>
        <input type="number" min="0" class="input-number" name="alloc_quote" value="<?php echo esc_attr( intval( $rebl_query['alloc_quote'] ) ); ?>" />
        </label>
      </p>
      <p class="form-row form-row-last">
        <label><?php esc_html_e( 'Quote takeout', 'trader' ); ?> [€] <span class="required">*</span>
        <input type="number" min="0" class="input-number" name="takeout" value="<?php echo esc_attr( $rebl_query['takeout'] ); ?>" />
        </label>
      </p>
      <div class="clear"></div>
      <p>
        <button type="submit" class="button" value="<?php esc_attr_e( 'Refresh', 'trader' ); ?>"><?php esc_html_e( 'Refresh', 'trader' ); ?></button>
      </p>
    </form>
    <form style="display:inline-block;" action="<?php echo esc_attr( get_permalink() ) . '?' . urldecode( http_build_query( $rebl_query ) ); ?>" method="post">
      <?php wp_nonce_field( 'portfolio-rebalance-user_' . $current_user->ID, 'do-portfolio-rebalance-nonce' ); ?>
      <p>
        <input type="hidden" name="action" value="do-portfolio-rebalance" />
        <button type="submit" class="button trader-action-zone" value="<?php esc_attr_e( 'Rebalance now', 'trader' ); ?>"
        onclick="return confirm('<?php echo esc_attr__( 'This will perform a portfolio rebalance.\nAre you sure?', 'trader' ); ?>');"><?php esc_html_e( 'Rebalance now', 'trader' ); ?></button>
      </p>
    </form>
    <form style="display:inline-block;" action="<?php echo esc_attr( get_permalink() ) . '?' . urldecode( http_build_query( $rebl_query ) ); ?>" method="post">
      <?php wp_nonce_field( 'portfolio-rebalance-user_' . $current_user->ID, 'do-portfolio-rebalance-nonce' ); ?>
      <p>
        <input type="hidden" name="action" value="sell-whole-portfolio" />
        <button type="submit" class="button trader-danger-zone" value="<?php esc_attr_e( 'Sell whole portfolio', 'trader' ); ?>"
        onclick="return confirm('<?php echo esc_attr__( 'This will sell all your assets.\nAre you sure?', 'trader' ); ?>');"><?php esc_html_e( 'Sell whole portfolio', 'trader' ); ?></button>
      </p>
    </form>

    <?php
  endif;
  return ob_get_clean();
}
