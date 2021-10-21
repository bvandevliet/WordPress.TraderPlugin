<?php

namespace Trader\Metrics;

defined( 'ABSPATH' ) || exit;


class CoinMarketCap
{
  /**
   * The API endpoint base url.
   *
   * @var string
   */
  public const URL = 'https://pro-api.coinmarketcap.com/v1/';

  /**
   * Retrieve API key.
   *
   * @return string The API key.
   */
  private static function get_api_key() : string
  {
    return \Trader\API_Keys::get_api_key( 'coinmarketcap' );
  }

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
        'Accepts'           => 'application/json',
        'Accept'            => 'application/json',
        'X-CMC_PRO_API_KEY' => self::get_api_key(),
      ),
    );

    $response = trader_request( $url, $query, $args );
    return false !== $response ? json_decode( $response ) : null;
  }


  /**
   * List latest.
   *
   * @param array $query https://coinmarketcap.com/api/documentation/v1/#operation/getV1CryptocurrencyListingsLatest
   *
   * @return object[]|WP_Error
   */
  public static function list_latest( $query = array() )
  {
    $endpoint = 'cryptocurrency/listings/latest';

    $query = wp_parse_args(
      $query,
      array(
        'limit' => 100,
        'sort'  => 'market_cap',
      )
    );

    $response = self::request( $endpoint, $query );

    if ( empty( $response ) || empty( $response->data ) || ! is_array( $response->data ) ) {
      $errors = new \WP_Error();
      $errors->add(
        'market_cap_listings_latest-' . ( $response['error_code'] ?? 0 ),
        __( 'CoinMarketCap error: ', 'trader' ) . ( $response['error_message'] ?? __( 'An unknown error occured.', 'trader' ) )
      );
      return $errors;
    }

    return $response->data;
  }
}
