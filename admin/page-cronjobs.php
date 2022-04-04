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
function trader_admin_page_cronjobs( $page )
{
  /**
   * Register cronjobs setting.
   */
  register_setting(
    'trader_cronjobs',        // $option_group / $page_slug
    'trader_disable_wp_cron', // $option_name
    array(                    // $args = array()
      'type'              => 'boolean',
      'sanitize_callback' => function ( $value )
      {
        if ( ! trader_enable_wp_cron( empty( $value ) ) ) {
          update_option( 'trader_disable_wp_cron', defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON );
        }
        return boolval( $value );
      },
      'show_in_rest'      => false,
    )
  );

  /**
   * Add cronjobs settings section.
   */
  add_settings_section(
    'trader_cronjobs_section', // $id
    null,                      // $title
    function ()                // $callback
    {},
    'trader_cronjobs'          // $page_slug
  );

  /**
   * Add cronjobs settings field.
   */
  add_settings_field(
    'trader_disable_wp_cron_field',    // $id
    __( 'Disable WP-Cron', 'trader' ), // $title
    function ( $args )                 // $callback
    {
      ?>
      <fieldset>
        <label>
          <input type="checkbox"
          id="trader_disable_wp_cron_field"
          name="trader_disable_wp_cron"
          <?php checked( ! empty( get_option( 'trader_disable_wp_cron', false ) ) ); ?> />
          <?php _e( 'Set constant DISABLE_WP_CRON to \'true\' in wp-config.php', 'trader' ); ?>
        </label>

        <p class="description">
          <?php
          _e(
            'Disable WP-Cron to improve performance and reliability,<br>
            but you need to hook wp-cron.php to the system task scheduler on at least an hourly interval.<br>
            <a href="https://developer.wordpress.org/plugins/cron/hooking-wp-cron-into-the-system-task-scheduler/" target="_blank" rel="noopener noreferrer">Read more</a>',
            'trader'
          );
          ?>
        </p>

      </fieldset>
      <?php
    },
    'trader_cronjobs',         // $page_slug
    'trader_cronjobs_section', // $section = 'default'
    array(                     // $args    = array()
      'label_for' => 'trader_disable_wp_cron_field',
    )
  );
}
