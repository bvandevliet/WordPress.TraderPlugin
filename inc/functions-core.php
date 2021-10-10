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
 * @return string|string[]|null The encrypted string key(s).
 */
function trader_encrypt_key( $key )
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
    /**
     * ERROR HANDLING !!
     */
    return null;
  }

  return \Defuse\Crypto\Crypto::encrypt( $key, $secret_key );
}

/**
 * Decrypts an encrypted string key.
 *
 * @param string|string[] $encrypted_key The encrypted string key(s).
 *
 * @return string|string[]|null The original string key(s).
 */
function trader_decrypt_key( $encrypted_key )
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
    /**
     * ERROR HANDLING !!
     */
    return null;
  }

  try {
    return \Defuse\Crypto\Crypto::decrypt( $encrypted_key, $secret_key );
  } catch ( \Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $ex ) {
    /**
     * ERROR HANDLING !!
     */
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
