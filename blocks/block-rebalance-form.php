<?php

defined( 'ABSPATH' ) || exit;


/**
 * @param [type] $block_attributes
 * @param [type] $content
 */
function trader_dynamic_block_rebalance_form_cb( $block_attributes, $content )
{
  /**
   * Check user capabilities.
   */
  $current_user = wp_get_current_user();
  if ( ! current_user_can( 'trader_manage_portfolio' ) ) {
    return;
  }

  ob_start();

  $configuration = \Trader\Configuration::get_configuration_from_environment();

  $errors = get_error_obj();

  if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
    if ( isset( $_POST['action'] )
      && isset( $_POST['do-portfolio-rebalance-nonce'] ) && wp_verify_nonce( $_POST['do-portfolio-rebalance-nonce'], 'portfolio-rebalance-user_' . $current_user->ID )
    ) {
      switch ( $_POST['action'] ) {

        case 'save-configuration':
          $configuration->save();
          ?>
          <div class="updated notice is-dismissible"><p><?php esc_html_e( 'Configuration updated.', 'trader' ); ?></p></div>
          <?php

          break;

        case 'do-portfolio-rebalance':
          $balance_allocated = \Trader::get_asset_allocations( \Trader\Exchanges\Bitvavo::current_user(), $configuration );

          $balance_exchange = \Trader\Exchanges\Bitvavo::current_user()->get_balance();
          $balance          = \Trader\Balance::merge_balance( $balance_allocated, $balance_exchange, $configuration );

          if ( is_wp_error( $balance_allocated ) ) {
            $errors->merge_from( $balance_allocated );
          }
          if ( is_wp_error( $balance_exchange ) ) {
            $errors->merge_from( $balance_exchange );
          }

          $trades = array();
          if ( ! is_wp_error( $balance_allocated ) && ! is_wp_error( $balance_exchange ) ) {
            $trades = \Trader::rebalance( \Trader\Exchanges\Bitvavo::current_user(), $balance, $configuration );
            foreach ( $trades as $index => $order ) {
              if ( ! empty( $order['errorCode'] ) ) {
                $errors->add(
                  $order['errorCode'] . '-' . $index,
                  sprintf( __( 'Exchange error %1$s %2$s: ', 'trader' ), $order['side'], $order['market'] ) . ( $order['error'] ?? __( 'An unknown error occured.', 'trader' ) )
                );
              } elseif ( ! 'filled' === $order['status'] ) {
                $errors->add(
                  'not_filled-' . $index,
                  sprintf( __( 'Order not filled %1$s %2$s: ', 'trader' ), $order['side'], $order['market'] ) . $order['status']
                );
              }
            }
          }

          /**
           * On success, update timestamp of last rebalance.
           * Always save configuration.
           */
          if ( ! $errors->has_errors() ) {
            if ( count( $trades ) > 0 ) {
              $configuration->last_rebalance = new DateTime();

              ?>
              <div class="updated notice is-dismissible"><p><?php esc_html_e( 'Portfolio was rebalanced successfully.', 'trader' ); ?></p></div>
              <?php
            } else {
              ?>
              <div class="updated notice is-dismissible"><p><?php esc_html_e( 'Nothing to rebalance.', 'trader' ); ?></p></div>
              <?php
            }
          }
          $configuration->save();
          ?>
          <div class="updated notice is-dismissible"><p><?php esc_html_e( 'Configuration updated.', 'trader' ); ?></p></div>
          <?php

          break;

        case 'sell-whole-portfolio':
          /**
           * Success or not, disable automation anyway, and before the sell-off to prevent a potential automation being triggered.
           */
          $configuration->automation_enabled = false;
          $configuration->last_rebalance     = new DateTime();
          $configuration->save();
          ?>
          <div class="updated notice is-dismissible"><p><?php esc_html_e( 'Configuration updated.', 'trader' ); ?></p></div>
          <?php

          foreach ( \Trader\Exchanges\Bitvavo::current_user()->sell_whole_portfolio() as $index => $order ) {
            if ( ! empty( $order['errorCode'] ) ) {
              $errors->add(
                $order['errorCode'] . '-' . $index,
                sprintf( __( 'Exchange error %1$s %2$s: ', 'trader' ), $order['side'], $order['market'] ) . ( $order['error'] ?? __( 'An unknown error occured.', 'trader' ) )
              );
            }
          }

          if ( ! $errors->has_errors() ) {
            ?>
            <div class="updated notice is-dismissible"><p><?php esc_html_e( 'Whole portfolio was sold successfully.', 'trader' ); ?></p></div>
            <?php
          }

          break;
      }
    } else {
      $errors->add( 'submit_error', __( 'Action failed.', 'trader' ) );
    }
  }

  if ( $errors->has_errors() ) :
    ?>
    <div class="error"><p><?php echo implode( "</p>\n<p>", array_map( 'esc_html', $errors->get_error_messages() ) ); ?></p></div>
    <?php
  endif;

  ?>
  <form action="<?php echo esc_attr( get_permalink() ); ?>" method="post" class="trader-rebalance">
    <?php wp_nonce_field( 'portfolio-rebalance-user_' . $current_user->ID, 'do-portfolio-rebalance-nonce' ); ?>
    <fieldset>
      <div>
        <p class="form-row form-row-wide">
          <label title="<?php esc_attr_e( 'Max amount of assets from CoinMarketCap listing.', 'trader' ); ?>">
            <?php esc_html_e( 'Top count', 'trader' ); ?> [n]
            <input type="number" min="1" max="100" class="input-number" name="top_count" value="<?php echo esc_attr( $configuration->top_count ); ?>" />
          </label>
        </p>
      </div>
      <div>
        <p class="form-row form-row-2">
          <label title="<?php esc_attr_e( 'Exponential Moving Average period of Market Cap, to smooth out volatility.', 'trader' ); ?>">
            <?php esc_html_e( 'Smoothing [days]', 'trader' ); ?>
            <input type="number" min="1" max="100" class="input-number" name="smoothing" value="<?php echo esc_attr( $configuration->smoothing ); ?>" />
          </label>
        </p>
        <p class="form-row form-row-2">
          <label title="<?php esc_attr_e( 'nth root of Market Cap EMA, to dampen the effect an individual asset has on the portfolio.', 'trader' ); ?>">
            <?php esc_html_e( 'nth root ^(1/[n])', 'trader' ); ?>
            <input type="number" min="1" step=".01" class="input-number" name="nth_root" value="<?php echo esc_attr( $configuration->nth_root ); ?>" />
          </label>
        </p>
      </div>
      <div>
        <p class="form-row form-row-2">
          <label title="<?php echo esc_attr( sprintf( __( 'Allocate a given percentage to quote currency \'%s\'.', 'trader' ), $configuration->quote_currency ) ); ?>">
            <?php esc_html_e( 'Quote allocation', 'trader' ); ?> [%]
            <input id="alloc_quote" type="number" min="0" max="100" step=".01" class="input-number" name="alloc_quote" value="<?php echo esc_attr( $configuration->alloc_quote ); ?>" />
          </label>
        </p>
        <p class="form-row form-row-2">
          <label title="<?php echo esc_attr( sprintf( __( 'Takeout a given amount of quote currency \'%s\'.', 'trader' ), $configuration->quote_currency ) ); ?>">
            <?php esc_html_e( 'Quote takeout', 'trader' ); ?> [<?php echo esc_html( $configuration->quote_currency ); ?>]
            <input type="number" min="0" step=".01" class="input-number" name="takeout" value="<?php echo esc_attr( $configuration->takeout ); ?>" />
          </label>
        </p>
      </div>
      <div>
        <p class="form-row form-row-2">
          <label for="interval_hours" title="<?php esc_attr_e( 'Allow an automated rebalance only once within this interval.', 'trader' ); ?>">
            <?php esc_html_e( 'Rebalance interval', 'trader' ); ?> [hrs]
          </label>
          <label style="float:right;" title="<?php esc_attr_e( 'Automatically perform portfolio rebalance when conditions are met.', 'trader' ); ?>">
            <?php esc_html_e( 'Automation', 'trader' ); ?>
            <input type="checkbox" name="automation_enabled" <?php checked( $configuration->automation_enabled ); ?> />
          </label>
          <input id="interval_hours" type="number" min="1" class="input-number" name="interval_hours" value="<?php echo esc_attr( $configuration->interval_hours ); ?>" />
        </p>
        <p class="form-row form-row-2">
          <label title="<?php esc_attr_e( 'Minimum required percentage difference to trigger an automated rebalance.', 'trader' ); ?>">
            <?php esc_html_e( 'Rebalance threshold', 'trader' ); ?> [%]
            <span style="float:right;">(<?php echo esc_html( $configuration->quote_currency ); ?>~<span class="trader-threshold-absolute"></span>)</span>
            <input type="number" min="0" max="100" step=".01" class="input-number" name="rebalance_threshold" value="<?php echo esc_attr( $configuration->rebalance_threshold ); ?>" />
          </label>
        </p>
      </div>
    </fieldset>
    <p>
      <?php
      echo esc_html(
        sprintf(
          __( 'Last rebalance: %s', 'trader' ),
          $configuration->last_rebalance instanceof DateTime ? $configuration->last_rebalance->setTimezone( wp_timezone() )->format( 'Y-m-d H:i:s' ) : __( 'Never', 'trader' )
        )
      );
      ?>
    </p>
    <p style="display:inline-block;">
      <button type="submit" class="button" name="action" value="save-configuration"><?php esc_html_e( 'Save changes', 'trader' ); ?></button>
    </p>
    <p style="display:inline-block;">
      <button type="submit" class="button" name="action" value="do-portfolio-rebalance" disabled
      onclick="return confirm('<?php esc_attr_e( 'This will perform a portfolio rebalance.\nAre you sure?', 'trader' ); ?>');">
      <?php printf( __( 'Rebalance now (fee ≈ %1$s %2$s)', 'trader' ), $configuration->quote_currency, '<span class="trader-expected-fee"></span>' ); ?></button>
    </p>
    <p style="display:inline-block;">
      <button type="submit" class="button trader-danger-zone" name="action" value="sell-whole-portfolio"
      onclick="return confirm('<?php esc_attr_e( 'This will sell all your assets and disables automation.\nAre you sure?', 'trader' ); ?>');">
      <?php esc_html_e( 'Sell whole portfolio', 'trader' ); ?></button>
    </p>
  </form>

  <?php
  return ob_get_clean();
}
