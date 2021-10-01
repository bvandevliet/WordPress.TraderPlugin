<?php

namespace Trader;

defined( 'ABSPATH' ) || exit;


class API_Keys
{
  /**
   * Holds the API keys.
   *
   * @var string[]
   */
  private static ?array $keys = null;

  /**
   * Retrieve API keys.
   *
   * @return string The API key.
   */
  public static function get_api_keys() : array
  {
    if ( null === self::$keys ) {
      $keys       = get_option( 'trader_api_keys' );
      self::$keys = is_array( $keys ) ? array_map( 'trader_decrypt_key', $keys ) : array();
    }

    return self::$keys;
  }

  /**
   * Retrieve API key.
   *
   * @return string The API key.
   */
  public static function get_api_key( string $service ) : string
  {
    return self::get_api_keys()[ $service ] ?? '';
  }
}
