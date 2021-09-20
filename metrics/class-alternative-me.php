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
   * @return mixed|false
   */
  public static function fag_index( $query = array() )
  {
    $endpoint = 'fng';

    $query = wp_parse_args(
      $query,
      array( 'limit' => 1 )
    );

    $response = self::request( $endpoint, $query );
    return is_object( $response ) ? $response->data : null;
  }
}
