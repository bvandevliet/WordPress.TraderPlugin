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

    // do something ..
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
}
