<?php

defined( 'ABSPATH' ) || exit;


/**
 * Register settings, sections and fields (admin_init).
 *
 * @param array $page {.
 *   @type string $slug
 *   @type string $page_title
 *   @type string $menu_title
 *   @type string $capabilities
 * }
 */
function trader_admin_page_apis( $page )
{
  /**
   * Register API keys setting.
   */
  register_setting(
    'trader_apis',     // $option_group / $page_slug
    'trader_api_keys', // $option_name
    array(             // $args = array()
      'type'              => 'string',
      'sanitize_callback' => 'trader_encrypt_key',
      'show_in_rest'      => false,
    )
  );

  /**
   * Add API keys settings section.
   */
  add_settings_section(
    'trader_apis_section', // $id
    null,                  // $title
    function ()            // $callback
    {
      ?>
      <p class="description">
        <?php
        _e(
          'This is where to configure API keys to reach endpoints needed for general purposes.
          <br>All users with a portfolio or any rebalancing cronjob can trigger requests to these services.
          <br>These API keys are stored encrypted and will never be revealed publicly.
          <br>
          <br>In case a service requires to whitelist your IP-adres, use:',
          'trader'
        )
        ?>
        <code><?php echo esc_html( $_SERVER['SERVER_ADDR'] ); ?></code>
      </p>
      <?php
    },
    'trader_apis' // $page_slug
  );

  /**
   * Add API keys settings field.
   */
  add_settings_field(
    'trader_apis_cmc_field',         // $id
    __( 'CoinMarketCap', 'trader' ), // $title
    function ( $args )               // $callback
    {
      /**
       * DO VALIDATION OF API KEYS WHEN SAVING !!
       */
      $keys = \Trader\API_Keys::get_api_keys();

      ?>
      <fieldset>

        <input type="text"
        id="trader_apis_cmc_field"
        class="regular-text"
        name="trader_api_keys[coinmarketcap]"
        placeholder=""
        value="<?php echo esc_attr( $keys['coinmarketcap'] ?? '' ); ?>" />

        <p class="description">
        <?php
        _e(
          'This service is used to obtain a ranking of assets.',
          'trader'
        );
        ?>
      </p>

      </fieldset>
      <?php
    },
    'trader_apis',         // $page_slug
    'trader_apis_section', // $section = 'default'
    array(                 // $args    = array()
      'label_for' => 'trader_apis_cmc_field',
    )
  );
}
