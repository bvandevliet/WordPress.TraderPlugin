<?php

namespace Trader;

defined( 'ABSPATH' ) || exit;


/**
 * Class for editing files on the server.
 */
class File_Editor
{
  /**
   * Holds the filepath.
   *
   * @var string
   */
  protected $file_path;

  /**
   * Holds the file content.
   *
   * @var string
   */
  protected $contents = null;

  /**
   * Make specific private properties readonly.
   *
   * @param string $prop Property name.
   *
   * @return null|mixed Property value or null on failure.
   */
  public function __get( string $prop )
  {
    if ( $prop === 'contents' ) {
      $this->read();
      return $this->contents;
    } elseif ( in_array( $prop, array( 'file_path' ), true ) ) {
      return $this->$prop;
    }
    return null;
  }

  /**
   * Constructor.
   *
   * Stores the filepath.
   *
   * @param string $file_path Filepath.
   */
  public function __construct( string $file_path )
  {
    $this->file_path = $file_path;
  }

  /**
   * Read file contents and store it in a placeholder property.
   *
   * @return bool Whether the file was fetched successfully.
   */
  public function fetch() : bool
  {
    return false !== $this->contents = @file_get_contents( $this->file_path );
  }

  /**
   * Read file contents if not yet done and store it in a placeholder property.
   *
   * @return bool Whether the file was read successfully.
   */
  public function read() : bool
  {
    /**
     * If content is already read, return early.
     */
    if (
      $this->contents !== null &&
      $this->contents !== false
    ) {
      return true;
    }

    /**
     * If content is not yet read, do it now.
     */
    if ( $this->contents === null ) {
      return $this->fetch();
    }

    /**
     * If content could not be read, return false.
     */
    return $this->contents !== false;
  }

  /**
   * Write contents currently stored in the placeholder property to wp-config.php.
   *
   * @return bool Whether wp-config.php was written successfully.
   */
  public function write() : bool
  {
    if (
      $this->contents === null ||
      $this->contents === false
    ) {
      return false;
    }

    return @file_put_contents( $this->file_path, $this->contents, LOCK_EX ) !== false;
  }
}
