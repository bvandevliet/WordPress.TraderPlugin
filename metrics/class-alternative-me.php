<?php

namespace Trader\Metrics;

defined( 'ABSPATH' ) || exit;


class Alternative_Me
{
  /**
   * The API endpoint base url.
   *
   * @var string
   */
  public const URL = 'https://api.alternative.me/';

  /**
   * Cached value of current Fear and Greed index.
   *
   * @var null|int
   */
  private static ?int $fag_index_cached = null;

  /**
   * Perform API request.
   *
   * @param string $endpoint Endpoint for this API request.
   * @param array  $query    URL query arguments.
   *
   * @return mixed|false
   */
  private static function request( string $endpoint, array $query = array() )
  {
    $url = self::URL . ltrim( $endpoint, '/' );

    $args = array(
      'headers' => array(
        'Accepts' => 'application/json',
        'Accept'  => 'application/json',
      ),
    );

    $response = trader_request( $url, $query, $args );
    return false !== $response ? json_decode( $response ) : null;
  }


  /**
   * Fear and Greed index.
   *
   * @param array $query https://alternative.me/crypto/fear-and-greed-index/#api
   *
   * @return array
   */
  public static function fag_index( $query = array() ) : array
  {
    $endpoint = 'fng';

    $query = wp_parse_args(
      $query,
      array( 'limit' => 1 )
    );

    $response = self::request( $endpoint, $query );
    return is_object( $response ) ? $response->data : null;
  }

  /**
   * Current (cached) Fear and Greed index.
   *
   * @return int
   */
  public static function fag_index_current() : int
  {
    if ( null === self::$fag_index_cached ) {
      $result                 = self::fag_index( $query = array( 'limit' => 1 ) );
      self::$fag_index_cached = ! is_array( $result ) || count( $result ) === 0 ? 50 : $result[0]->value; // "50" !!
    }

    return self::$fag_index_cached;
  }
}
