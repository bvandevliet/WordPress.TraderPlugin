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
   * Database columns names vs. CoinMarketCap data names.
   *
   * @var array
   */
  private const COLUMNS = array(
    // $column_name      => $format_and_cmc_name
    'symbol'             => array( '%s', 'symbol' ),
    'circulating_supply' => array( '%d', 'circulating_supply' ),
    'total_supply'       => array( '%d', 'total_supply' ),
    'last_updated'       => array( '%s', 'last_updated' ),
    'quote'              => array( '%s', 'quote' ),
  );

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
   * Serialize a cmc asset into a database record.
   *
   * @param object     $asset_cmc The cmc asset.
   * @param array|null $format    Array of formats to use in $wpdb's update() and insert() methods.
   *
   * @return object $record
   */
  private static function serialize_record( object $asset_cmc, ?array &$format = array() ) : object
  {
    $record = new \stdClass();

    $format = array_column( self::COLUMNS, 0 );

    $index = 0;
    foreach ( self::COLUMNS as $column_name => $format_and_cmc_name ) {
      $cmc_name = $format_and_cmc_name[1];
      switch ( $column_name ) {
        case 'last_updated':
          // '2021-11-02T11:55:27.000Z' is not exactly the ISO8601 format, but what is it then .. ? !!
          // $record->$column_name = \DateTime::createFromFormat( \DateTime::ISO8601, $asset_cmc->$cmc_name )->format( 'Y-m-d H:i:s' );
          $record->$column_name = ( new \DateTime( $asset_cmc->$cmc_name ) )->format( 'Y-m-d H:i:s' );
          break;
        case 'quote':
          $record->$column_name = maybe_serialize( $asset_cmc->$cmc_name );
          break;
        default:
          switch ( $format[ $index ] ) {
            case '%d':
              $record->$column_name = intval( $asset_cmc->$cmc_name );
              break;
            case '%f':
              $record->$column_name = floatstr( $asset_cmc->$cmc_name );
              break;
            default:
              $record->$column_name = sanitize_text_field( $asset_cmc->$cmc_name );
          }
      }
      $index++;
    }

    return $record;
  }

  /**
   * Deserialize a database record into a cmc asset.
   *
   * @param object $record The database record.
   *
   * @return object $asset_cmc
   */
  private static function deserialize_record( object $record ) : object
  {
    $asset_cmc = new \stdClass();

    foreach ( self::COLUMNS as $column_name => $format_and_cmc_name ) {
      $cmc_name = $format_and_cmc_name[1];
      switch ( $column_name ) {
        case 'last_updated':
          // '2021-11-02T11:55:27.000Z' is not exactly the ISO8601 format, but what is it then .. ? !!
          $asset_cmc->$cmc_name = ( new \DateTime( $record->$column_name ) )->format( \DateTime::ISO8601 );
          break;
        case 'quote':
          $asset_cmc->$cmc_name = maybe_unserialize( $record->$column_name );
          break;
        default:
          $asset_cmc->$cmc_name = sanitize_text_field( $record->$column_name );
      }
    }

    return $asset_cmc;
  }

  /**
   * Update and get market cap history.
   *
   * @param array $cmc_latest Array of cmc assets.
   * @param int   $limit      Limit the returned historical database records per asset.
   *
   * @global wpdb $wpdb
   *
   * @return array History extended data.
   */
  private static function update_get_history( array $cmc_latest, int $limit = 1 ) : array
  {
    // \Trader_Setup::create_db_tables();

    global $wpdb;

    $limit = max( 1, $limit );

    $cmc_history = array();

    foreach ( $cmc_latest as $asset_cmc ) {
      $results =
        $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}trader_market_cap WHERE symbol = %s ORDER BY last_updated DESC LIMIT %d", $asset_cmc->symbol, $limit ) );

      $do_insert = true;

      if ( count( $results ) > 0 ) {
        // we need to pass as reference because of re-assignment
        foreach ( $results as $index => &$result ) {
          if ( $index === 0 && 0 === trader_offset_days( $result->last_updated ) ) {
            $do_insert = false;
            $wpdb->update( "{$wpdb->prefix}trader_market_cap", (array) self::serialize_record( $asset_cmc, $format ), array( 'id' => $result->id ), $format, '%d' );
          } else {
            // no need to deserialize first record if $do_insert == false
            $result = self::deserialize_record( $result );
          }
        }
      } else {
        $results = array();
      }

      if ( $do_insert ) {
        $wpdb->insert( "{$wpdb->prefix}trader_market_cap", (array) self::serialize_record( $asset_cmc, $format ), $format );
      }

      $cmc_history[] = array_merge( array( $asset_cmc ), array_slice( $results, $do_insert ? 0 : 1 ) );
    }

    return $cmc_history;
  }

  /**
   * Returns the top 100 from CoinMarketCap.
   *
   * @param array $query https://coinmarketcap.com/api/documentation/v1/#operation/getV1CryptocurrencyListingsLatest
   * @param int   $limit Limit the fetched historical database records per asset, ignore if only a database update is needed.
   *
   * @return object[]|array[]|WP_Error Array of historical object[] per asset if 'sort' == 'market_cap', else object[] with assets.
   */
  public static function list_latest( $query = array(), int $limit = 1 )
  {
    $endpoint = 'cryptocurrency/listings/latest';

    $query = wp_parse_args(
      $query,
      array(
        'sort'    => 'market_cap',
        'convert' => \Trader\Exchanges\Bitvavo::QUOTE_CURRENCY,
      )
    );

    $query['limit'] = 100; // always query 100 results for market cap history log, handle top limit elsewhere

    $response = self::request( $endpoint, $query );

    if ( empty( $response ) || empty( $response->data ) || ! is_array( $response->data ) ) {
      $errors = new \WP_Error();
      $errors->add(
        'market_cap_listings_latest-' . ( $response['error_code'] ?? 0 ),
        __( 'CoinMarketCap error: ', 'trader' ) . ( $response['error_message'] ?? __( 'An unknown error occured.', 'trader' ) )
      );
      return $errors;
    }

    return $query['sort'] === 'market_cap' ? self::update_get_history( $response->data, $limit ) : $response->data;
  }
}
