<?php

namespace Trader;

defined( 'ABSPATH' ) || exit;


/**
 * Class for modifying constants in config.php.
 */
class Config_Editor extends File_Editor
{
  public function __construct()
  {
    /**
     * Create file if not yet exists and insert php opening tag.
     */
    if ( ! file_exists( TRADER_ABSPATH . 'config.php' ) ) {
      if ( false !== $pointer = fopen( TRADER_ABSPATH . 'config.php', 'w' ) ) {
        fwrite( $pointer, '<?php' . PHP_EOL );
        fclose( $pointer );
      }
    }

    parent::__construct( TRADER_ABSPATH . 'config.php' );
  }

  /**
   * Updates the given constant and value.
   * If the constant was not yet defined, it is added.
   *
   * @param string           $name  The name of the constant to define.
   * @param null|string|bool $value The value of the constant or null to remove its declaration.
   *
   * @return bool Whether the constant was updated, added or removed successfully.
   */
  public function set_constant( string $name, null|string|bool $value ) : bool
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
    $name = trader_trim( wp_strip_all_tags( $name ), '' );

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
      // phpcs:ignore WordPress.PHP.StrictComparisons
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
      $this->contents .= PHP_EOL . 'define( \'' . $name . '\', ' . $content_value . ' );' . PHP_EOL;
    }

    return true;
  }
}
