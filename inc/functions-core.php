<?php

defined( 'ABSPATH' ) || exit;


/**
 * Strip whitespace from the beginning and end of a string.
 *
 * @param mixed  $string The input string being trimmed.
 * @param string $delim  Delimiter to replace double whitespaces with.
 *
 * @return string The trimmed string.
 */
function trader_trim( $string, string $delim = ' ' ) : string
{
  return trim( preg_replace( '/([\s\t\v\0\r]|\r?\n)+/', $delim, $string ) );
}

/**
 * Sanitizes and encrypts a string key.
 *
 * @param string|string[] $key The plain string key(s).
 *
 * @return null|string|string[] The encrypted string key(s).
 */
function trader_encrypt_key( string|array $key ) : null|string|array
{
  if ( is_array( $key ) ) {
    return array_map( 'trader_encrypt_key', $key );
  }

  $key = sanitize_key( $key );

  try {
    $secret_key = \Defuse\Crypto\Key::loadFromAsciiSafeString( TRADER_SECRET_KEY );
  } catch ( \Defuse\Crypto\Exception\BadFormatException $ex ) {
    return null;
  } catch ( Exception $ex ) {
    // ERROR HANDLING !!
    return null;
  }

  return \Defuse\Crypto\Crypto::encrypt( $key, $secret_key );
}

/**
 * Decrypts an encrypted string key.
 *
 * @param string|string[] $encrypted_key The encrypted string key(s).
 *
 * @return null|string|string[] The original string key(s).
 */
function trader_decrypt_key( string|array $encrypted_key ) :null|string|array
{
  if ( is_array( $encrypted_key ) ) {
    return array_map( 'trader_decrypt_key', $encrypted_key );
  }

  $encrypted_key = sanitize_key( $encrypted_key );

  try {
    $secret_key = \Defuse\Crypto\Key::loadFromAsciiSafeString( TRADER_SECRET_KEY );
  } catch ( \Defuse\Crypto\Exception\BadFormatException $ex ) {
    return null;
  } catch ( Exception $ex ) {
    // ERROR HANDLING !!
    return null;
  }

  try {
    return \Defuse\Crypto\Crypto::decrypt( $encrypted_key, $secret_key );
  } catch ( \Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $ex ) {
    // ERROR HANDLING !!
    return null;
  }
}

/**
 * Transpose an array.
 *
 * @param array $arr The array to transpose.
 *
 * @return array Transposed array.
 */
function trader_array_transpose( array $arr ) : array
{
  $out = array();

  foreach ( $arr as $key => $sub_arr ) {
    foreach ( $sub_arr as $sub_key => $value ) {
      $out[ $sub_key ][ $key ] = $value;
    }
  }

  return $out;
}

if ( ! function_exists( 'get_error_obj' ) ) {
  /**
   * Get the global Error object.
   *
   * @global \WP_Error|null The global WP_Error object if any.
   *
   * @return \WP_Error $errors
   */
  function get_error_obj() : \WP_Error
  {
    global $errors;

    /**
     * @var \WP_Error
     */
    return is_wp_error( $errors ) ? $errors : new \WP_Error();
  }
}

if ( ! function_exists( 'get_error_data' ) ) {
  /**
   * Get all error data from an existing error object.
   *
   * @param \WP_Error $errors An existing EP_Error object.
   *
   * @return array[] {.
   *   @type int|string $code
   *   @type string     $message
   *   @type mixed      $data
   * }
   */
  function get_error_data( \WP_Error $errors )
  {
    $error_data = array();

    foreach ( $errors->get_error_codes() as $code ) {
      $data_obj   = array(
        'code'     => $code,
        'messages' => $errors->get_error_messages( $code ),
        'data'     => $errors->get_all_error_data( $code ),
      );
      $error_data = array_merge( $error_data, $data_obj );
    }

    return $error_data;
  }
}

if ( ! function_exists( 'array_some' ) ) {
  /**
   * Determines whether the specified callback function returns true for any element of an array.
   *
   * @param array    $arr The array to test.
   * @param callable $cb  The callback function.
   *
   * @return bool
   */
  function array_some( array $arr, callable $cb ) : bool
  {
    foreach ( $arr as $value ) {
      if ( call_user_func( $cb, $value ) === true ) {
        return true;
      }
    }
    return false;
  }
}

if ( ! function_exists( 'array_every' ) ) {
  /**
   * Determines whether all the members of an array satisfy the specified test.
   *
   * @param array    $arr The array to test.
   * @param callable $cb  The callback function.
   *
   * @return bool
   */
  function array_every( array $arr, callable $cb ) : bool
  {
    foreach ( $arr as $value ) {
      if ( call_user_func( $cb, $value ) === false ) {
        return false;
      }
    }
    return true;
  }
}

/**
 * Performs an HTTP request and returns its response.
 *
 * @param string $url   URL to retrieve.
 * @param array  $query Http query args to include in the URL.
 * @param array  $args  https://developer.wordpress.org/reference/classes/WP_Http/request/
 *
 * @return mixed|false
 */
function trader_request( string $url, array $query = array(), array $args = array() )
{
  /**
   * Build the url.
   */
  if ( count( $query ) > 0 ) {
    $url .= '?' . urldecode( http_build_query( $query ) );
  }

  /**
   * Set default arguments.
   */
  $args['sslverify'] = true;
  $args['headers']   = wp_parse_args( $args['headers'], wp_get_nocache_headers() );

  /**
   * Do the request.
   */
  $response = wp_remote_request( $url, $args );
  return '' === wp_remote_retrieve_response_code( $response ) ? false : $response['body'];
}

/**
 * Determine a given date is today or an offset in days from today.
 *
 * @param string $date The datetime to test.
 * @param string $base The datetime of the day to test against, defaults to 'now'.
 *
 * @return int The offset in days the given date differs from today, 0 means today.
 */
function trader_offset_days( string $date, string $base = 'now' ) : int
{
  $given_time = strtotime( $date );
  $base_time  = strtotime( ( new DateTime( $base, new DateTimeZone( 'UTC' ) ) )->format( 'Y-m-d' ) );

  $diff = ( $given_time - $base_time ) / ( 60 * 60 * 24 ); // 86400

  return floor( $diff );
}

/**
 * Enable or disable WP-Cron.
 *
 * @param bool $enable Enable/disable.
 *
 * @return bool Success.
 */
function trader_enable_wp_cron( bool $enable = true ) : bool
{
  $wp_config_editor = new \Trader\WP_Config_Editor();

  $wp_config_editor->set_constant( 'DISABLE_WP_CRON', ! $enable ? true : null );

  return $wp_config_editor->write();
}

/**
 * Determines whether a plugin is active.
 *
 * @param string $plugin Path to the plugin file relative to the plugins directory.
 *
 * The below function is not available from the front-end, so we embedded its body.
 *
 * @link https://developer.wordpress.org/reference/functions/is_plugin_active/
 *
 * @return bool True, if in the active plugins list. False, not in the list.
 */
function trader_is_plugin_active( string $plugin ) : bool
{
  return in_array( $plugin, (array) get_option( 'active_plugins', array() ), true ) || ( is_multisite() && isset( get_site_option( 'active_sitewide_plugins' )[ $plugin ] ) );
}
