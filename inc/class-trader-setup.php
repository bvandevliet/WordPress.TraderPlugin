<?php

defined( 'ABSPATH' ) || exit;


class Trader_Setup
{
  /**
   * Wrapper function called on plugin activation.
   *
   * @todo Support for error messages.
   */
  public static function on_activation()
  {
    if ( ! current_user_can( 'activate_plugins' ) ) {
      return;
    }

    self::create_db_tables();
    self::add_roles_caps();
    self::verify_secret_key();
  }

  /**
   * Wrapper function called on plugin deactivation.
   */
  public static function on_deactivation()
  {
    if ( ! current_user_can( 'activate_plugins' ) ) {
      return;
    }

    // do something ..
  }

  /**
   * Wrapper function called on plugin removal.
   */
  public static function on_uninstall()
  {
    if ( ! current_user_can( 'delete_plugins' ) ) {
      return;
    }

    self::remove_roles_caps();
    self::drop_db_tables();
  }

  /**
   * Regenerates a secret key.
   * Be careful, this will invalidate all stored encrypted data such as API keys!
   *
   * @return bool Whether the secret key was successfully updated.
   */
  protected static function regenerate_secret_key() : bool
  {
    $config_editor = new \Trader\Config_Editor();

    $key = \Defuse\Crypto\Key::createNewRandomKey()->saveToAsciiSafeString();

    $const_updated = $config_editor->set_constant( 'TRADER_SECRET_KEY', $key );

    return $const_updated && $config_editor->write();
  }

  /**
   * Creates a secret key if not yet exists.
   *
   * @return bool Whether the secret key is validated.
   */
  public static function verify_secret_key() : bool
  {
    if ( ! defined( 'TRADER_SECRET_KEY' ) ) {
      return self::regenerate_secret_key();
    }

    try {
      $key = \Defuse\Crypto\Key::loadFromAsciiSafeString( TRADER_SECRET_KEY );
    } catch ( \Defuse\Crypto\Exception\BadFormatException $ex ) {
      return false;
    } catch ( Exception $ex ) {
      /**
       * ERROR HANDLING !!
       */
      return false;
    }

    return true;
  }

  /**
   * Add custom user roles and capabilities.
   */
  public static function add_roles_caps()
  {
    $wp_roles = wp_roles();

    $wp_roles->add_cap( 'administrator', 'trader_manage_options' );
    $wp_roles->add_cap( 'administrator', 'trader_manage_users' );

    foreach ( array_keys( $wp_roles->roles ) as $role ) {
      $wp_roles->add_cap( $role, 'trader_manage_portfolio' );
    }

    add_role(
      'trader_user',
      'Trader User',
      array(
        'trader_manage_portfolio' => true,
      )
    );
  }

  /**
   * Remove custom user roles and capabilities.
   */
  protected static function remove_roles_caps()
  {
    $wp_roles = wp_roles();

    $roles = array(
      'trader_user',
    );

    $caps = array(
      'trader_manage_options',
      'trader_manage_users',
      'trader_manage_portfolio',
    );

    foreach ( $roles as $role ) {
      remove_role( $role );
    }

    foreach ( array_keys( $wp_roles->roles ) as $role ) {
      foreach ( $caps as $cap ) {
        $wp_roles->remove_cap( $role, $cap );
      }
    }
  }

  /**
   * Create database tables.
   *
   * @global wpdb $wpdb
   *
   * @return bool
   */
  public static function create_db_tables() : bool
  {
    global $wpdb;

    $collate = $wpdb->has_cap( 'collation' ) ? $wpdb->get_charset_collate() : '';

    return true === $wpdb->query(
      "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}trader_market_cap (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        symbol VARCHAR(24) NOT NULL,
        circulating_supply BIGINT UNSIGNED,
        total_supply BIGINT UNSIGNED,
        last_updated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        quote LONGTEXT
      ) $collate;"
    );
  }

  /**
   * Drop database tables.
   *
   * @global wpdb $wpdb
   */
  private static function drop_db_tables()
  {
    global $wpdb;

    $collate = $wpdb->has_cap( 'collation' ) ? $wpdb->get_charset_collate() : '';

    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}trader_market_cap" );
  }
}
