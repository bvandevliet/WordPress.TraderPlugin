<?php

defined( 'ABSPATH' ) || exit;


/**
 * Hide the admin bar for non-admin users.
 */
add_action(
  'after_setup_theme',
  /**
   * Fires after the theme is loaded.
   */
  function ()
  {
    if ( ! is_admin() && ! current_user_can( 'manage_options' ) ) {
      show_admin_bar( false );
    }
  }
);


/**
 * Enhanced security for sensitive AJAX requests.
 */
add_action(
  'check_ajax_referer',
  /**
   * Fires once the Ajax request has been validated or not.
   *
   * @param string    $action The Ajax nonce action.
   * @param false|int $result False if the nonce is invalid, 1 if the nonce is valid and generated between
   *                          0-12 hours ago, 2 if the nonce is valid and generated between 12-24 hours ago.
   *
   * @link https://developer.wordpress.org/reference/functions/check_ajax_referer/
   */
  function ( $action, $result )
  {
    if ( in_array(
      $action,
      array(
        'ajax_example',
      ),
      true
    ) ) {
      if ( ! is_user_logged_in() || 1 !== $result ) {
        if ( wp_doing_ajax() ) {
          wp_die( -1, 403 );
        } else {
          die( '-1' );}
      }
    }
  },
  PHP_INT_MAX,
  2
);


/**
 * Re-define nocache headers.
 */
add_filter(
  'nocache_headers',
  /**
   * Filters the cache-controlling headers.
   *
   * @param array $headers {
   *   Header names and field values.
   *   @type string $Expires       Expires header.
   *   @type string $Cache-Control Cache-Control header.
   * }
   *
   * @link https://developer.wordpress.org/reference/functions/wp_get_nocache_headers/
   */
  function( $headers )
  {
    return wp_parse_args(
      array(
        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        'Pragma'        => 'no-cache',
        'Expires'       => gmdate( DateTime::RFC7231, time() ),
      ),
      $headers
    );
  }
);

/**
 * Always apply nocache headers.
 */
add_filter(
  'wp_headers',
  /**
   * Filters the HTTP headers before they're sent to the browser.
   *
   * @param string[] $headers Associative array of headers to be sent.
   * @param WP       $wp      Current WordPress environment instance.
   */
  function( $headers/*, $wp*/ )
  {
    return wp_parse_args( wp_get_nocache_headers(), $headers );
  }
);


/**
 * Change the login url.
 *
 * MAKE DYNAMIC USING A SPECIFICALLY ASSIGNED LOGIN PAGE !!
 * NOW THE THEME MUST PROVIDE A TEMPLATE FOR THE "/login" PAGE !!
 */
add_filter(
  'login_url',
  /**
   * Filters the login URL.
   *
   * @param string $login_url    The login URL. Not HTML-encoded.
   * @param string $redirect     The path to redirect to on login, if supplied.
   * @param bool   $force_reauth Whether to force reauthorization, even if a cookie is present.
   */
  function ( $login_url, $redirect, $force_reauth )
  {
    $login_url = home_url( 'login', 'login' );

    if ( ! empty( $redirect ) ) {
      $login_url = add_query_arg( 'redirect_to', rawurlencode( $redirect ), $login_url );
    }

    if ( $force_reauth ) {
      $login_url = add_query_arg( 'reauth', '1', $login_url );
    }

    return $login_url;
  },
  10,
  3
);


/**
 * Handle #loginout# menu item placeholders.
 *
 * @param array $items An array of menu item post objects.
 */
add_filter(
  'wp_get_nav_menu_items',
  /**
   * Filters the navigation menu items being returned.
   *
   * @param array  $items An array of menu item post objects.
   * @param object $menu  The menu object.
   * @param array  $args  An array of arguments used to retrieve menu item objects.
   *
   * @link https://developer.wordpress.org/reference/hooks/wp_get_nav_menu_items/
   */
  function ( $items/*, $menu, $args*/ )
  {
    global $pagenow;

    if ( $pagenow === 'nav-menus.php' || defined( 'DOING_AJAX' ) ) {
      return $items;
    }

    $items_visible = array();

    /**
     * @see https://developer.wordpress.org/reference/functions/auth_redirect/
     */
    $redirect = ( strpos( $_SERVER['REQUEST_URI'], '/options.php' ) && wp_get_referer() ) ? wp_get_referer() : set_url_scheme( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );

    foreach ( $items as $item ) {
      if ( ! empty( $item->url ) && 0 === strpos( $item->url, '#loginout#' ) ) {
        if ( ! is_user_logged_in() ) {
          // $item->url   = esc_url( wp_login_url( $redirect ) );
          // $item->title = __( 'Log in', 'trader' );
        } else {
          $item->url   = esc_url( wp_logout_url() );
          $item->title = __( 'Log out', 'trader' );

          $items_visible[] = $item;
        }
      } else {
        $items_visible[] = $item;
      }
    }

    return $items_visible;
  }
);


/**
 * Add support to easily manage loginout menu item in the admin nav menu screen. WIP !!
 *
 * @link https://wordpress.org/plugins/login-logout-menu/
 */
// add_action(
// 'admin_head-nav-menus.php',
// **
// * Fires in head section for a specific admin page.
// *
// * The dynamic portion of the hook, `$hook_suffix`, refers to the hook suffix for the admin page.
// *
// * @link https://developer.wordpress.org/reference/hooks/admin_head-hook_suffix/
// */
// function ()
// {
// **
// * Adds a meta box to one or more screens.
// *
// * @link https://developer.wordpress.org/reference/functions/add_meta_box/
// */
// add_meta_box(
// 'trader-loginout-menu-item',
// 'Login/Logout menu item',
// **
// * The admin nav menu screen output callback.
// *
// * @link https://developer.wordpress.org/reference/functions/wp_nav_menu_setup/
// * @link https://developer.wordpress.org/reference/functions/wp_nav_menu_item_link_meta_box/
// */
// function ( $args )
// {},
// 'nav-menus',
// 'side',
// 'low',
// array()
// );
// }
// );
