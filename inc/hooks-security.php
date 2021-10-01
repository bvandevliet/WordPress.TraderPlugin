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
