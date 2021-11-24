<?php

namespace Trader;

defined( 'ABSPATH' ) || exit;


class Admin_Pages
{
  private static function get_pages() : array
  {
    return array(
      array(
        'slug'         => 'general',
        'page_title'   => __( 'General settings', 'trader' ),
        'menu_title'   => __( 'General', 'trader' ),
        'capabilities' => array(),
      ),
      array(
        'slug'         => 'apis',
        'page_title'   => __( 'API connections', 'trader' ),
        'menu_title'   => __( 'API\'s', 'trader' ),
        'capabilities' => array(),
      ),
      array(
        'slug'         => 'cronjobs',
        'page_title'   => __( 'Cronjob management', 'trader' ),
        'menu_title'   => __( 'Cronjobs', 'trader' ),
        'capabilities' => array(),
      ),
    );
  }

  public static function init()
  {
    /**
     * Include admin pages.
     */
    foreach ( self::get_pages() as $page ) {
      require_once TRADER_ABSPATH . 'admin/page-' . $page['slug'] . '.php';
    }

    /**
     * Register settings, sections and fields.
     *
     * @link https://developer.wordpress.org/plugins/settings/
     */
    add_action(
      'admin_init',
      function ()
      {
        self::register_settings();
      }
    );

    /**
     * Add the plugin menu's and pages.
     *
     * @link https://developer.wordpress.org/plugins/administration-menus/
     */
    add_action(
      'admin_menu',
      function ()
      {
        self::register_admin_pages();
      }
    );
  }

  /**
   * Register settings, sections and fields (admin_init).
   */
  private static function register_settings()
  {
    $pages = self::get_pages();

    foreach ( $pages as $page ) {
      /**
       * Check user capabilities.
       */
      if ( array_some(
        array_merge( array( 'trader_manage_options' ), $page['capabilities'] ),
        function( $cap )
        {
          return ! current_user_can( $cap );
        }
      ) ) {
        continue;
      }

      /**
       * Trigger callback function of the settings page.
       */
      call_user_func( 'trader_admin_page_' . $page['slug'], $page );
    }
  }

  /**
   * Add the plugin menu's and pages (admin_menu).
   */
  private static function register_admin_pages()
  {
    $pages = self::get_pages();

    /**
     * Add the main options menu page.
     *
     * @link https://developer.wordpress.org/reference/functions/add_menu_page/
     */
    add_menu_page(
      'Trader',                      // $page_title
      'Trader',                      // $menu_title
      'trader_manage_options',       // $capability
      'trader_' . $pages[0]['slug'], // $menu_slug
      null,                          // $function = ''
      'dashicons-chart-line',        // $icon_url = ''
      99                             // $position = null
    );

    foreach ( $pages as $page ) {
      /**
       * Add options submenu page.
       *
       * @link https://developer.wordpress.org/reference/functions/add_submenu_page/
       */
      add_submenu_page(
        'trader_' . $pages[0]['slug'], // $parent_slug
        $page['page_title'],           // $page_title
        $page['menu_title'],           // $menu_title
        'trader_manage_options',       // $capability
        'trader_' . $page['slug'],     // $menu_slug
        function () use ( $page )      // $function = ''
        {
          self::trader_admin_menu_page( 'trader_' . $page['slug'], array_merge( array( 'trader_manage_options' ), $page['capabilities'] ) );
        }
      );
    }
  }

  /**
   * Print content of a settings page.
   *
   * @link https://developer.wordpress.org/plugins/settings/custom-settings-page/
   *
   * @param string $menu_slug The slug name to refer to this menu by.
   * @param array  $caps      Array of required capabilities to access this menu.
   */
  public static function trader_admin_menu_page( string $menu_slug, array $caps = array() )
  {
    /**
     * Check user capabilities.
     */
    if ( array_some(
      $caps,
      function( $cap )
      {
        return ! current_user_can( $cap );
      }
    ) ) {
      return;
    }

    /**
     * Check whether the user has submitted the settings.
     * WordPress will add the "settings-updated" $_GET parameter to the url.
     */
    if ( ! empty( $_GET['settings-updated'] ) ) {
      add_settings_error(
        'trader',
        'trader_successmsg',
        __( 'Settings saved', 'trader' ),
        'updated'
      );
    }

    // Print error/update messages.
    settings_errors( 'trader' );
    ?>

    <div class="wrap">
      <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
      <form action="options.php" method="post">
        <?php

        // Output security fields for the registered settings.
        settings_fields( $menu_slug ); // $option_group / $page_slug

        // Output setting sections and their fields.
        do_settings_sections( $menu_slug ); // $page_slug

        // Submit ..
        submit_button( __( 'Save settings', 'trader' ) );

        ?>
      </form>
    </div>

    <?php
  }
}
