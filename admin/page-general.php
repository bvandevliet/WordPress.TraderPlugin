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
function trader_admin_page_general( $page )
{
  /**
   * Register "from" email setting.
   */
  register_setting(
    'trader_general',            // $option_group / $page_slug
    'trader_general_from_email', // $option_name
    array(                       // $args = array()
      'type'              => 'string',
      'sanitize_callback' => 'sanitize_email',
      'show_in_rest'      => false,
    )
  );

  /**
   * Add general settings section.
   */
  add_settings_section(
    'trader_general_section', // $id
    null,                     // $title
    function ()               // $callback
    {},
    'trader_general'          // $page_slug
  );

  /**
   * Add "from" email settings field.
   */
  add_settings_field(
    'trader_general_from_email_field', // $id
    __( 'From email', 'trader' ),      // $title
    function ( $args )                 // $callback
    {
      ?>
      <fieldset>

        <input type="text"
        id="trader_general_from_email_field"
        class="regular-text"
        name="trader_general_from_email"
        placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>"
        value="<?php echo esc_attr( get_option( 'trader_general_from_email', '' ) ); ?>" />

        <p class="description">
          <?php
          _e(
            'This email address is used as the "From" address for automated emails.',
            'trader'
          );
          ?>
        </p>

      </fieldset>
      <?php
    },
    'trader_general',         // $page_slug
    'trader_general_section', // $section = 'default'
    array(                    // $args    = array()
      'label_for' => 'trader_general_from_email_field',
    )
  );
}
