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
  $args['headers']   = wp_parse_args(
    $args['headers'],
    array(
      'Cache-Control' => 'no-cache, no-store, must-revalidate',
      'Pragma'        => 'no-cache',
      'Expires'       => '0',
    )
  );

  /**
   * Do the request.
   */
  $response = wp_remote_request( $url, $args );
  return is_wp_error( $response ) || $response['response']['code'] !== 200 ? false : $response['body'];
}
