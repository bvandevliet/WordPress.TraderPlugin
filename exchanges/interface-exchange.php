<?php

namespace Trader\Exchanges;

defined( 'ABSPATH' ) || exit;


interface Exchange
{
  /**
   * The default quote currency.
   *
   * @var string
   */
  // public const QUOTE_CURRENCY;

  /**
   * The minimum amount quote for market orders.
   *
   * @var int
   */
  // public const MIN_QUOTE;

  /**
   * The maximum fee for taker orders.
   *
   * @var string
   */
  // public const TAKER_FEE;

  /**
   * The maximum fee for maker orders.
   *
   * @var string
   */
  // public const MAKER_FEE;

  /**
   * The maximum amount of candles that can be retrieved in a single API call.
   *
   * @var int
   */
  // public const CANDLES_LIMIT;

  /**
   * Get instance of a connected API object.
   *
   * @return mixed
   */
  public static function get_instance();

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
  public static function candles( string $market, string $chart, array $args = array() ) : array;

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
  public static function retrieve_ohlcv( array $candles, array &$open_arr, array &$high_arr, array &$low_arr, array &$close_arr, array &$vol_arr );

  /**
   * Returns the deposit history.
   *
   * @param string $symbol
   *
   * @return array $history {.
   *   @type array   $deposits {.
   *     @type string  $symbol
   *     @type string  $amount
   *   }
   *   @type string  $total
   * }
   */
  public static function deposit_history( string $symbol/* = self::QUOTE_CURRENCY*/ ) : array;

  /**
   * Returns the withdrawal history.
   *
   * @param string $symbol
   *
   * @return array $history {.
   *   @type array   $withdrawals {.
   *     @type string  $symbol
   *     @type string  $amount
   *   }
   *   @type string  $total
   * }
   */
  public static function withdrawal_history( string $symbol/* = self::QUOTE_CURRENCY*/ ) : array;

  /**
   * Get balance. First entry of $assets is quote currency.
   *
   * @return \Trader\Balance|\WP_Error
   */
  public static function get_balance();

  /**
   * Cancel all existing open orders.
   *
   * @param string[] $ignore List of assets for which existing orders should be kept unaffected.
   *
   * @return array List of order data.
   */
  public static function cancel_all_orders( array $ignore = array() ) : array;

  /**
   * Sell whole portfolio.
   *
   * @param string[] $ignore List of assets that should not be sold.
   *
   * @return array List of order data.
   */
  public static function sell_whole_portfolio( array $ignore = array() ) : array;

  /**
   * Buy asset.
   *
   * @param string $symbol       Symbol of asset to buy.
   * @param mixed  $amount_quote Amount to buy in quote currency.
   * @param bool   $simulate     Perform a fake order, e.g. to determine expected fee amount.
   *
   * @return array List of order data.
   */
  public static function buy_asset( string $symbol, $amount_quote, bool $simulate = false ) : array;

  /**
   * Sell asset.
   *
   * @param string $symbol       Symbol of asset to sell.
   * @param mixed  $amount_quote Amount to sell in quote currency.
   * @param bool   $simulate     Perform a fake order, e.g. to determine expected fee amount.
   *
   * @return array List of order data.
   */
  public static function sell_asset( string $symbol, $amount_quote, bool $simulate = false ) : array;

  /**
   * Get order data.
   *
   * @param string $market   Market.
   * @param string $order_id Order ID.
   *
   * @return array List of order data.
   */
  public static function get_order( string $market, string $order_id ) : array;

  /**
   * Cancel order.
   *
   * @param string $market   Market.
   * @param string $order_id Order ID.
   *
   * @return array List of order data.
   */
  public static function cancel_order( string $market, string $order_id ) : array;
}
