<?php

namespace Trader;

defined( 'ABSPATH' ) || exit;


/**
 * Class for modifying constants in wp-config.php.
 */
class WP_Config_Editor extends File_Editor
{
  /**
   * Constructor.
   *
   * Creates an instance of File_Editor for wp-config.php.
   */
  public function __construct()
  {
    parent::__construct( self::get_wp_config_path() );
  }

  /**
   * Retrieve the path to wp-config.php.
   *
   * @return string The filepath for wp-config.php.
   */
  public static function get_wp_config_path()
  {
    if (
      ! file_exists( $path = ABSPATH . 'wp-config.php' ) &&
      file_exists( dirname( ABSPATH ) . '/wp-config.php' ) &&
      ! file_exists( dirname( ABSPATH ) . '/wp-settings.php' )
    ) {
      $path = dirname( ABSPATH ) . '/wp-config.php';
    }

    /**
     * Filters the wp-config.php filepath, return an empty string to avoid modifications to this file.
     */
    return apply_filters( 'trader_wp_config_path', $path );
  }

  /**
   * Updates the given constant and value.
   * If the constant was not yet defined, it is added.
   *
   * @param string           $name  The name of the constant to define.
   * @param string|bool|null $value The value of the constant or null to remove its declaration.
   *
   * @return bool Whether the constant was updated, added or removed successfully.
   */
  public function set_constant( string $name, $value )
  {
    /**
     * If content could not be read, return false.
     */
    if ( $this->read() === false ) {
      return false;
    }

    /**
     * Sanitize.
     */
    $name = strtoupper( sanitize_key( wp_strip_all_tags( $name ) ) );

    /**
     * Remove constant declarations from content if NULL was passed.
     */
    if ( $value === null ) {
      $this->contents = preg_replace(
        '/\s*?define\(\s*?[\'"]' . preg_quote( $name, '/' ) . '[\'"]\s*?,\s*?(.*?)\s*?\);\s*?(\r|\r?\n)/im',
        PHP_EOL,
        $this->contents
      );

      return true;
    }

    /**
     * If BOOL ..
     */
    elseif (
      is_bool( $value ) ||
      (
        is_string( $value ) &&
        strtolower( $value ) === 'on'
      )
    ) {
      $content_value = json_encode( $value == true );
    }

    /**
     * If STRING ..
     */
    elseif ( is_string( $value ) ) {
      $content_value = "'" . addslashes( wp_strip_all_tags( $value ) ) . "'";
    }

    /**
     * Else, bail.
     */
    else {
      return false;
    }

    /**
     * Update the first constant declaration in content.
     */
    if ( preg_match( '/define\(\s*?[\'"]' . preg_quote( $name, '/' ) . '[\'"]\s*?,\s*?(.*?)\s*?\);/im', $this->contents, $constants ) ) {
      $this->contents = str_replace(
        $constants[0],
        'define( \'' . $name . '\', ' . $content_value . ' );',
        $this->contents
      );
    }

    /**
     * Add new constant declaration to content.
     */
    else {
      $this->contents = preg_replace(
        '/(\/\*.*?stop\sediting!.*?\*\/)/im',
        'define( \'' . $name . '\', ' . $content_value . ' );' . PHP_EOL . PHP_EOL . '$1',
        $this->contents
      );
    }

    return true;
  }
}
