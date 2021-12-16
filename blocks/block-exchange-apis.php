<?php

defined( 'ABSPATH' ) || exit;


/**
 * @param [type] $block_attributes
 * @param [type] $content
 */
function trader_dynamic_block_exchange_apis_cb( $block_attributes, $content )
{
  /**
   * Check user capabilities.
   */
  $current_user = wp_get_current_user();
  if ( ! current_user_can( 'trader_manage_portfolio' ) ) {
    return;
  }

  ob_start();

  if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
    /**
     * Process form data ..
     */
    if ( isset( $_POST['save-exchange-apis-nonce'] ) && wp_verify_nonce( $_POST['save-exchange-apis-nonce'], 'update-user_' . $current_user->ID ) ) {
      $errors = get_error_obj();

      /**
       * DO VALIDATION OF API KEYS WHEN SAVING !!
       */
      $keys = \Trader\API_Keys::get_api_keys_user();

      if ( isset( $_POST['api_keys'] ) && is_array( $_POST['api_keys'] ) ) {
        $keys = wp_parse_args( wp_unslash( $_POST['api_keys'] ), $keys );
      }

      if ( ! $errors->has_errors() ) {
        update_user_meta( $current_user->ID, 'api_keys', array_map( 'trader_encrypt_key', $keys ) );
      }
    }
  }

  $keys = \Trader\API_Keys::get_api_keys_user( null, true );

  ?>
  <form action="<?php echo esc_attr( get_permalink() ); ?>" method="post">
    <?php wp_nonce_field( 'update-user_' . $current_user->ID, 'save-exchange-apis-nonce' ); ?>

    <p class="description">
      <?php
      _e(
        'This is where to configure API keys to communicate with your exchanges.
        <br>These API keys are stored encrypted and will never be revealed publicly.
        <br>
        <br>In case a service requires to whitelist your IP-adres, use:',
        'trader'
      )
      ?>
      <code><?php echo esc_html( $_SERVER['SERVER_ADDR'] ); ?></code>
    </p>

    <?php if ( isset( $errors ) && is_wp_error( $errors ) && ! $errors->has_errors() ) : ?>
      <div class="updated notice is-dismissible"><p><?php esc_html_e( 'API keys updated.', 'trader' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $errors ) && is_wp_error( $errors ) && $errors->has_errors() ) : ?>
      <div class="error"><p><?php echo implode( "</p>\n<p>", esc_html( $errors->get_error_messages() ) ); ?></p></div>
    <?php endif; ?>

    <fieldset>
      <!-- <legend><?php esc_html_e( 'Exchange API keys', 'trader' ); ?></legend> -->

      <p class="form-row form-row-wide">
        <label for="api_keys[bitvavo_key]"><?php esc_html_e( 'Bitvavo API key', 'trader' ); ?></label>
        <input type="text" class="input-text" name="api_keys[bitvavo_key]" id="api_keys[bitvavo_key]" autocomplete="off" value="<?php echo esc_attr( $keys['bitvavo_key'] ?? '' ); ?>" />
      </p>
      <p class="form-row form-row-wide">
        <label for="api_keys[bitvavo_secret]"><?php esc_html_e( 'Bitvavo API secret', 'trader' ); ?></label>
        <input type="text" class="input-text" name="api_keys[bitvavo_secret]" id="api_keys[bitvavo_secret]" autocomplete="off" value="<?php echo esc_attr( $keys['bitvavo_secret'] ?? '' ); ?>" />
      </p>
    </fieldset>

    <p>
      <button type="submit" class="button" value="<?php esc_attr_e( 'Save changes', 'trader' ); ?>"><?php esc_html_e( 'Save changes', 'trader' ); ?></button>
    </p>
  </form>

  <?php
  return ob_get_clean();
}
