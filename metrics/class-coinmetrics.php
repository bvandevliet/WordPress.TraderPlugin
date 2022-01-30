<?php

namespace Trader\Metrics;

defined( 'ABSPATH' ) || exit;


class CoinMetrics
{
  /**
   * The API endpoint base url.
   *
   * @var string
   */
  public const URL = 'https://community-api.coinmetrics.io/v4/';

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
   * Get historical price, free-float, current and realized market cap data.
   *
   * @param string $symbol Asset symbol.
   *
   * @return array[] [time, PriceUSD, CapMrktFFUSD, CapMrktCurUSD, CapRealUSD][]
   */
  public static function market_cap( string $symbol )
  {
    $endpoint = 'timeseries/asset-metrics';

    $result = self::request(
      $endpoint,
      array(
        'assets'    => strtolower( $symbol ),
        'metrics'   => 'PriceUSD,CapMrktFFUSD,CapMrktCurUSD,CapRealUSD',
        'page_size' => 10000,
      )
    );

    if ( ! is_object( $result ) || empty( $result->data ) || ! is_array( $result->data ) ) {
      return array(
        array(
          'time'          => false, // indicates an error occured
          'PriceUSD'      => 0,
          'CapMrktFFUSD'  => 0,
          'CapMrktCurUSD' => 0,
          'CapRealUSD'    => 0,
        ),
      );
    }

    return array_map( fn( $item ) => (array) $item, $result->data );
  }

  /**
   * Get current Relative Unrealized Profit/Loss and MVRV Z-Score.
   *
   * @param array[] $market_cap [CapMrktCurUSD, CapRealUSD][]
   *
   * @return array [nupl, mvrv_z]
   */
  public static function nupl_mvrvz( array $market_cap ) : array
  {
    $cap_cur  = array_reverse( array_column( $market_cap, 'CapMrktCurUSD' ) );
    $cap_real = array_reverse( array_column( $market_cap, 'CapRealUSD' ) );

    $diff = $cap_cur[0] - $cap_real[0];

    $st_dev = trader_st_dev( $cap_cur );

    // phpcs:ignore WordPress.PHP.StrictComparisons
    $nupl  = 0 == $cap_cur[0] ? '0' : $diff / $cap_cur[0];
    // phpcs:ignore WordPress.PHP.StrictComparisons
    $mvrvz = 0 == $st_dev ? '0' : $diff / $st_dev;

    return compact( 'nupl', 'mvrvz' );
  }

  /**
   * Get historical Relative Unrealized Profit/Loss and MVRV Z-Score.
   *
   * @param array[] $market_cap [CapMrktCurUSD, CapRealUSD][]
   *
   * @return array[] [nupl, mvrv_z][]
   */
  public static function nupl_mvrvz_arr( array $market_cap ) : array
  {
    $cap_cur  = array_column( $market_cap, 'CapMrktCurUSD' );
    $cap_real = array_column( $market_cap, 'CapRealUSD' );

    $length = min( count( $cap_cur ), count( $cap_real ) );

    $cap_cur  = array_slice( $cap_cur, 0, $length );
    $cap_real = array_slice( $cap_real, 0, $length );

    $nupl  = array();
    $mvrvz = array();

    for ( $index = 0; $index < $length; $index++ ) {
      $diff   = $cap_cur[ $index ] - $cap_real[ $index ];
      $st_dev = trader_st_dev( array_slice( $cap_cur, $index ) );

      // phpcs:ignore WordPress.PHP.StrictComparisons
      $nupl[]  = 0 == $cap_cur[ $index ] ? '0' : floatstr( $diff / $cap_cur[ $index ] );
      // phpcs:ignore WordPress.PHP.StrictComparisons
      $mvrvz[] = 0 == $st_dev ? '0' : floatstr( $diff / $st_dev );
    }

    return compact( 'nupl', 'mvrvz' );
  }
}
