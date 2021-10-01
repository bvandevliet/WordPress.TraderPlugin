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
   * Holds the API key.
   *
   * @var string
   */
  private static ?string $key = null;

  /**
   * Retrieve API key.
   *
   * @return string The API key.
   */
  private static function get_api_key() : string
  {
    if ( null === self::$key ) {
      self::$key = \Trader\API_Keys::get_api_key( 'coinmarketcap' );
    }

    return self::$key;
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
   * @return object[]|false
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

    return self::request( $endpoint, $query );
  }
}
