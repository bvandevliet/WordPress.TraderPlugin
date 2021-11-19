<?php

namespace Trader;

defined( 'ABSPATH' ) || exit;


/**
 * User specific rebalance configuration.
 */
class Configuration
{
  /**
   * Creates an instance and optionally pre-define properties.
   * Use the get() method instead if you intend to save() this configuration, to avoid overwrites.
   *
   * @param array|object $object An optional array or object to "cast" to an instance of Asset.
   */
  public function __construct( $object = array() )
  {
    foreach ( (array) $object as $key => $value ) {
      $this->$key = $value;
    }
  }

  /**
   * Configurations cache.
   *
   * FOR NOW, ONLY ONE CONFIGURATION PER USER IS SUPPORTED !!
   *
   * @var Configuration[]
   */
  private static array $configurations = array();

  /**
   * Get configuration by user id.
   *
   * @param int|null $user_id Defaults to current user.
   *
   * @return Configuration
   */
  public static function &get( ?int $user_id = null ) : Configuration
  {
    $user_id = $user_id ?? wp_get_current_user()->ID;

    if ( empty( self::$configurations[ $user_id ] ) ) {
      $configuration                    = get_user_meta( $user_id, 'trader_configuration', true );
      self::$configurations[ $user_id ] = $configuration instanceof Configuration ? $configuration : new Configuration();
    }

    $configuration =& self::$configurations[ $user_id ];

    // back-compat
    if ( 0 === count( $configuration->asset_weightings ) ) {
      $asset_weightings                = get_user_meta( $user_id, 'asset_weightings', true );
      $configuration->asset_weightings = is_array( $asset_weightings ) ? $asset_weightings : array();
    }

    return $configuration;
  }

  /**
   * Get rebalance parameters from request parameters.
   *
   * @return Configuration
   */
  public static function get_args_from_request_params() : Configuration
  {
    $configuration = self::get();

    foreach ( (array) $configuration as $param => $default ) {
      // phpcs:ignore WordPress.Security
      $req_value = $_POST[ $param ] ?? $_GET[ $param ] ?? null;
      $req_value = null !== $req_value ? wp_unslash( $req_value ) : null;
      switch ( $param ) {
        case 'top_count':
          $configuration->$param = is_numeric( $req_value ) ? min( max( 1, intval( $req_value ) ), 100 ) : $default;
          break;
        case 'smoothing':
        case 'nth_root':
        case 'dust_limit':
          $configuration->$param = is_numeric( $req_value ) ? max( 1, intval( $req_value ) ) : $default;
          break;
        case 'alloc_quote':
          $configuration->$param = is_numeric( $req_value ) ? trader_max( 0, floatstr( floatval( $req_value ) ) ) : $default;
          break;
        case 'takeout':
          $configuration->$param = is_numeric( $req_value ) ? trader_max( 0, floatstr( floatval( $req_value ) ) ) : $default;
          break;
        case 'alloc_quote_fag_multiply':
          $configuration->$param = ! empty( $req_value ) ? boolval( $req_value ) : $default;
          break;
        default:
          $configuration->$param = is_numeric( $req_value ) ? floatstr( floatval( $req_value ) ) : $default;
      }
    }

    return $configuration;
  }

  /**
   * Save this configuration.
   *
   * @param int|null $user_id Defaults to current user.
   */
  public function save( ?int $user_id = null )
  {
    $user_id = $user_id ?? wp_get_current_user()->ID;

    // back-compat
    delete_user_meta( $user_id, 'asset_weightings' );

    update_user_meta( $user_id, 'trader_configuration', $this );
  }

  /**
   * Alternative asset allocation weighting factors.
   *
   * @var array
   */
  public array $asset_weightings = array();

  /**
   * Amount of assets from the top market cap ranking.
   *
   * @var int
   */
  public int $top_count = 30;

  /**
   * The period to use for smoothing Market Cap.
   *
   * @var int
   */
  public int $smoothing = 14;

  /**
   * The nth root of Market Cap to use for allocation.
   *
   * @var int
   */
  public int $nth_root = 4;

  /**
   * Minimum required allocation difference in quote currency.
   *
   * @var int
   */
  public int $dust_limit = 2;

  /**
   * Allocation percentage to keep in quote currency.
   *
   * @var int|float|string
   */
  public $alloc_quote = 0;

  /**
   * Amount in quote currency to keep out / not re-invest.
   *
   * @var int|float|string
   */
  public $takeout = 0;

  /**
   * Multiply quote allocation by Fear and Greed index.
   *
   * @var bool
   */
  public bool $alloc_quote_fag_multiply = false;

  /**
   * Rebalance period in hours.
   *
   * @var int
   */
  public int $interval_hours = 96;

  /**
   * Rebalance allocation percentage difference threshold.
   *
   * @var int|float|string
   */
  public $rebalance_threshold = '1';

  /**
   * Automatic periodic rebalancing.
   *
   * @var bool
   */
  public bool $automation_enabled = false;
}
