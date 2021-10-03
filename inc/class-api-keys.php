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
   * Holds the user API keys.
   *
   * @var string[]
   */
  private static ?array $keys_user = null;

  /**
   * Retrieve API keys.
   *
   * @param bool $force Force re-read.
   *
   * @return string[] The API keys.
   */
  public static function get_api_keys( bool $force = false ) : array
  {
    if ( null === self::$keys || $force ) {
      $keys       = get_option( 'trader_api_keys' );
      self::$keys = is_array( $keys ) ? array_map( 'trader_decrypt_key', $keys ) : array();
    }

    return self::$keys;
  }

  /**
   * Retrieve user specific API keys.
   *
   * @param int  $user_id User ID, current user by default.
   * @param bool $force   Force re-read.
   *
   * @return string[] The API keys.
   */
  public static function get_api_keys_user( int $user_id = null, bool $force = false ) : array
  {
    if ( ( null === $user_id || 0 >= $user_id ) && 0 === wp_get_current_user()->ID ) {
      return array();
    }
    if ( null === self::$keys_user || $force ) {
      $keys_user       = get_user_meta( $user_id ?? wp_get_current_user()->ID, 'api_keys', true );
      self::$keys_user = is_array( $keys_user ) ? array_map( 'trader_decrypt_key', $keys_user ) : array();
    }

    return self::$keys_user;
  }

  /**
   * Retrieve API key.
   *
   * @return string The API key.
   */
  public static function get_api_key( string $service ) : string
  {
    return array_merge( self::get_api_keys(), self::get_api_keys_user() ) [ $service ] ?? '';
  }
}
