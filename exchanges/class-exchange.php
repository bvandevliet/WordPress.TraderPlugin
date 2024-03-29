<?php

namespace Trader\Exchanges;

defined( 'ABSPATH' ) || exit;


/**
 * Base class for Exchange objects,
 * based on Bitvavo parameters, other exchanges may have to overwrite properties.
 */
abstract class Exchange
{
  /**
   * The default quote currency.
   *
   * @var string
   */
  public const QUOTE_CURRENCY = 'EUR';

  /**
   * The minimum amount quote for market orders.
   *
   * @var int
   */
  public const MIN_QUOTE = 5;

  /**
   * The maximum fee for taker orders.
   *
   * @var string
   */
  public const TAKER_FEE = '.0025';

  /**
   * The maximum fee for maker orders.
   *
   * @var string
   */
  public const MAKER_FEE = '.0015';

  /**
   * The maximum amount of candles that can be retrieved in a single API call.
   *
   * @var int
   */
  public const CANDLES_LIMIT = 1440;


  /**
   * User ID this instance belongs to.
   *
   * @var null|int
   */
  protected ?int $user_id = null;

  /**
   * Constructor.
   *
   * @param null|int $user_id User ID.
   */
  public function __construct( ?int $user_id = null )
  {
    $this->user_id = $user_id;
  }


  /**
   * Check for errors in API response.
   *
   * @param \WP_Error $errors   Error object to append to.
   * @param mixed     $response Response data.
   *
   * @return \WP_Error
   */
  public static function check_error( \WP_Error $errors, $response ) : \WP_Error
  {
    if ( ! is_array( $response ) || ! empty( $response['errorCode'] ) ) {
      $errors->add(
        'exchange_' . get_called_class() . '-' . ( $response['errorCode'] ?? 0 ),
        __( 'Exchange error: ', 'trader' ) . ( $response['error'] ?? __( 'An unknown error occured.', 'trader' ) ),
        $response
      );
    }

    return $errors;
  }


  /**
   * Container for the wrapper instance belonging to the current user.
   *
   * @var ?self
   */
  protected static ?self $instance_current_user = null;

  /**
   * Get wrapper instance belonging to the current user.
   *
   * @return self
   */
  public static function current_user() : self
  {
    if ( null === static::$instance_current_user ) {
      static::$instance_current_user = new static();
    }

    return static::$instance_current_user;
  }


  /**
   * Get native instance of a connected API object.
   *
   * @return mixed
   */
  abstract public function get_instance();


  /**
   * Determine if a given market is tradable.
   *
   * @param string $market Market.
   */
  abstract public function is_tradable( string $market ) : bool;

  /**
   * Get candlesticks from exchange.
   *
   * @param string $market Market.
   * @param string $chart  Chart timeframe.
   * @param array  $args   {
   *   Optional.
   *   @type int     $limit  Limit amount of returned candles.
   *   @type int     $start  Integer specifying from which time candles should be returned. Should be a timestamp in milliseconds since 1 Jan 1970.
   *   @type int     $end    Integer specifying up to which time candles should be returned. Should be a timestamp in milliseconds since 1 Jan 1970.
   * }
   *
   * @return array Candlestick data as returned by exchange.
   */
  abstract public function candles( string $market, string $chart, array $args = array() ) : array;

  /**
   * Retrieve ohlcv arrays where first index is oldest and last index is latest data.
   *
   * @param array $candles   Candles in the format as returned by the exchange.
   * @param array $open_arr  Array of candle "open" values.
   * @param array $high_arr  Array of candle "high" values.
   * @param array $low_arr   Array of candle "low" values.
   * @param array $close_arr Array of candle "close" values.
   * @param array $vol_arr   Array of candle "volume" values.
   */
  abstract public static function retrieve_ohlcv( array $candles, array &$open_arr, array &$high_arr, array &$low_arr, array &$close_arr, array &$vol_arr );

  /**
   * Returns the deposit history.
   *
   * @return \WP_Error|array $history {.
   *   @type array   $deposits {.
   *     @type string  $symbol
   *     @type string  $amount
   *   }
   *   @type string  $total
   * }
   */
  abstract public function deposit_history() : \WP_Error|array;

  /**
   * Returns the withdrawal history.
   *
   * @return \WP_Error|array $history {.
   *   @type array   $withdrawals {.
   *     @type string  $symbol
   *     @type string  $amount
   *   }
   *   @type string  $total
   * }
   */
  abstract public function withdrawal_history() : \WP_Error|array;

  /**
   * Get balance. First entry of $assets is quote currency.
   *
   * @return \Trader\Balance|\WP_Error
   */
  abstract public function get_balance() : \Trader\Balance|\WP_Error;

  /**
   * Cancel all existing open orders.
   *
   * @param string[] $ignore List of assets for which existing orders should be kept unaffected.
   *
   * @return array List of order data.
   */
  abstract public function cancel_all_orders( array $ignore = array() ) : array;

  /**
   * Sell whole portfolio.
   *
   * @param string[] $ignore List of assets that should not be sold.
   *
   * @return array List of order data.
   */
  abstract public function sell_whole_portfolio( array $ignore = array() ) : array;

  /**
   * Buy asset.
   *
   * @param string $symbol       Symbol of asset to buy.
   * @param mixed  $amount_quote Amount to buy in quote currency.
   * @param bool   $simulate     Perform a fake order, e.g. to determine expected fee amount.
   *
   * @return array List of order data.
   */
  abstract public function buy_asset( string $symbol, $amount_quote, bool $simulate = false ) : array;

  /**
   * Sell asset.
   *
   * @param string $symbol           Symbol of asset to sell.
   * @param mixed  $amount_quote     Amount to sell in quote currency.
   * @param bool   $simulate         Perform a fake order, e.g. to determine expected fee amount.
   * @param bool   $amount_available Amount of asset available to sell.
   * @param bool   $price            Current price of asset.
   *
   * @return array List of order data.
   */
  abstract public function sell_asset( string $symbol, $amount_quote, bool $simulate = false, $amount_available = null, $price = null ) : array;

  /**
   * Get order data.
   *
   * @param string $market   Market.
   * @param string $order_id Order ID.
   *
   * @return array List of order data.
   */
  abstract public function get_order( string $market, string $order_id ) : array;

  /**
   * Cancel order.
   *
   * @param string $market   Market.
   * @param string $order_id Order ID.
   *
   * @return array List of order data.
   */
  abstract public function cancel_order( string $market, string $order_id ) : array;
}
