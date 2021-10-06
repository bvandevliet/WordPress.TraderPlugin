<?php

namespace Trader\Exchanges;

defined( 'ABSPATH' ) || exit;


/**
 * A wrapper class for the Bitvavo API.
 */
class Bitvavo implements Exchange
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
   * Retrieve API authentication from database.
   *
   * @return string[] The API key and secret for the current user.
   */
  private static function get_authentication() : array
  {
    $api_key    = \Trader\API_Keys::get_api_key( 'bitvavo_key' );
    $api_secret = \Trader\API_Keys::get_api_key( 'bitvavo_secret' );

    return compact( 'api_key', 'api_secret' );
  }

  /**
   * Container for the underlying parent instance of the class.
   *
   * @var null|\Bitvavo
   */
  private static ?\Bitvavo $instance = null;

  /**
   * {@inheritDoc}
   *
   * @return \Bitvavo
   */
  public static function get_instance() : \Bitvavo
  {
    /**
     * ERROR HANDLING SOMEHOW !!
     *
     * @link https://github.com/ccxt/ccxt/blob/e7ad04747f72e601eead58b02899b75126dc619d/js/bitvavo.js#L146
     * if( ! empty( $response['errorCode'] ) && in_array( $response['errorCode'], array() ) ) {}
     */
    if ( null === self::$instance ) {
      $api_auth = self::get_authentication();

      self::$instance = new \Bitvavo(
        array(
          'APIKEY'       => $api_auth['api_key'],
          'APISECRET'    => $api_auth['api_secret'],
          'ACCESSWINDOW' => 10000,
          'DEBUGGING'    => false,
        )
      );
    }

    return self::$instance;
  }


  /**
   * {@inheritDoc}
   */
  public static function candles( string $market, string $chart, array $args = array() ) : array
  {
    return self::get_instance()->candles( $market, $chart, $args );
  }


  /**
   * {@inheritDoc}
   */
  public static function retrieve_ohlcv( array $candles, array &$open_arr, array &$high_arr, array &$low_arr, array &$close_arr, array &$vol_arr )
  {
    $candles = array_reverse( $candles );

    $open_arr  = array_column( $candles, 1 );
    $high_arr  = array_column( $candles, 2 );
    $low_arr   = array_column( $candles, 3 );
    $close_arr = array_column( $candles, 4 );
    $vol_arr   = array_column( $candles, 5 );
  }


  /**
   * {@inheritDoc}
   */
  public static function deposit_history( string $symbol = self::QUOTE_CURRENCY ) : array
  {
    $deposits = self::get_instance()->depositHistory( array( 'symbol' => $symbol ) );
    $total    = 0;

    foreach ( $deposits as $deposit ) {
      $total = bcadd( $total, $deposit['amount'] );
    }

    return compact( 'deposits', 'total' );
  }


  /**
   * {@inheritDoc}
   */
  public static function withdrawal_history( string $symbol = self::QUOTE_CURRENCY ) : array
  {
    $withdrawals = self::get_instance()->withdrawalHistory( array( 'symbol' => $symbol ) );
    $total       = 0;

    foreach ( $withdrawals as $withdrawal ) {
      $total = bcadd( $total, $withdrawal['amount'] );
    }

    return compact( 'withdrawals', 'total' );
  }


  /**
   * {@inheritDoc}
   */
  public static function get_balance() : Balance
  {
    $balance_exchange = self::get_instance()->balance( array() );
    $balance          = new Balance();

    for ( $i = 0, $length = count( $balance_exchange ); $i < $length; $i++ ) {
      $asset = new Asset();

      $asset->symbol       = self::QUOTE_CURRENCY;
      $asset->price        = '1';
      $asset->available    = $balance_exchange[ $i ]['available'];
      $asset->amount       = floatstr( bcadd( $asset->available, $balance_exchange[ $i ]['inOrder'] ) );
      $asset->amount_quote = $asset->amount;

      if ( $i > 0 ) { // $balance_exchange[$i]['symbol'] !== self::QUOTE_CURRENCY )
        $asset->symbol = $balance_exchange[ $i ]['symbol'];
        $market        = $asset->symbol . '-' . self::QUOTE_CURRENCY;

        $asset->price        = self::get_instance()->tickerPrice( array( 'market' => $market ) )['price'];
        $asset->amount_quote = floatstr( bcmul( $asset->amount, $asset->price ) );
      }

      $balance->amount_quote_total = bcadd( $balance->amount_quote_total, $asset->amount_quote );

      if ( $i === 0 || $asset->amount_quote != 0 ) {
        $balance->assets[] = $asset;
      }
    }

    for ( $i = 0, $length = count( $balance->assets ); $i < $length; $i++ ) {
      $balance->assets[ $i ]->allocation_current = trader_get_allocation( $balance->assets[ $i ]->amount_quote, $balance->amount_quote_total );
    }

    return $balance;
  }


  /**
   * {@inheritDoc}
   */
  public static function cancel_all_orders( array $ignore = array() ) : array
  {
    $result = array();

    foreach ( self::get_instance()->ordersOpen( array() ) as $order ) {
      if ( ! in_array( explode( '-', $order['market'] )[0], $ignore, true ) ) {
        $result[] = self::get_instance()->cancelOrder( $order['market'], $order['orderId'] );
      }
    }

    return $result;
  }


  /**
   * {@inheritDoc}
   */
  public static function sell_whole_portfolio( array $ignore = array() ) : array
  {
    $result = self::cancel_all_orders( $ignore );

    $balance = self::get_instance()->balance( array() );

    foreach ( $balance as $asset ) {
      if ( $asset['symbol'] === self::QUOTE_CURRENCY ) {
        continue;
      }

      if ( $asset['available'] == 0 || in_array( $asset['symbol'], $ignore, true ) ) {
        continue;
      }

      $market = $asset['symbol'] . '-' . self::QUOTE_CURRENCY;

      $amount = floatstr( $asset['available'] );

      $result[] = self::get_instance()->placeOrder(
        $market,
        'sell',
        'market',
        array(
          'amount'                  => $amount,
          'disableMarketProtection' => false,
          'responseRequired'        => false,
        )
      );
    }

    return $result;
  }


  /**
   * {@inheritDoc}
   */
  public static function buy_asset( string $symbol, $amount_quote ) : array
  {
    $response = array(
      'orderId'           => null,
      'market'            => null,
      'side'              => 'buy',
      'status'            => 'rejected',
      'amountQuote'       => &$amount_quote,
      'filledAmount'      => '0',
      'filledAmountQuote' => '0',
      'feePaid'           => '0',
    );

    if ( $symbol === self::QUOTE_CURRENCY ) {
      return $response;
    }

    $market             = $symbol . '-' . self::QUOTE_CURRENCY;
    $response['market'] = $market;

    if ( floatval( $amount_quote ) <= 0 ) {
      return $response;
    }

    $response['status'] = 'new';

    // $market_info = self::get_instance()->markets( ['market' => $market] );
    // $asset_info  = self::get_instance()->assets( ['symbol' => $symbol] );

    // $min_quote  = $market_info['minOrderInQuoteAsset'];
    // $min_amount = $market_info['minOrderInBaseAsset'];

    $price = self::get_instance()->tickerPrice( array( 'market' => $market ) )['price'];
    // $book  = self::get_instance()->tickerBook( ['market' => $market] );

    $amount_quote = trader_floor( $amount_quote, 2 );
    // $amount       = trader_floor( bcdiv( $amount_quote, $price ), $asset_info['decimals'] );

    return wp_parse_args(
      self::get_instance()->placeOrder(
        $market,
        'buy',
        'market',
        array(
          'amountQuote'      => $amount_quote,
          // 'disableMarketProtection' => true,
          'responseRequired' => false,
        )
      ),
      $response
    );
  }


  /**
   * {@inheritDoc}
   */
  public static function sell_asset( string $symbol, $amount_quote ) : array
  {
    $response = array(
      'orderId'           => null,
      'market'            => null,
      'side'              => 'sell',
      'status'            => 'rejected',
      'amountQuote'       => &$amount_quote,
      'filledAmount'      => '0',
      'filledAmountQuote' => '0',
      'feePaid'           => '0',
    );

    if ( $symbol === self::QUOTE_CURRENCY ) {
      return $response;
    }

    $market             = $symbol . '-' . self::QUOTE_CURRENCY;
    $response['market'] = $market;

    if ( floatval( $amount_quote ) <= 0 ) {
      return $response;
    }

    $asset = self::get_instance()->balance( array( 'symbol' => $symbol ) )[0];

    if ( $asset['available'] == 0 ) {
      return $response;
    }

    $response['status'] = 'new';

    // $market_info = self::get_instance()->markets( ['market' => $market] );
    $asset_info = self::get_instance()->assets( array( 'symbol' => $symbol ) );

    // $min_quote  = $market_info['minOrderInQuoteAsset'];
    // $min_amount = $market_info['minOrderInBaseAsset'];

    $price = self::get_instance()->tickerPrice( array( 'market' => $market ) )['price'];
    // $book  = self::get_instance()->tickerBook( ['market' => $market] );

    $amount_quote = trader_ceil( $amount_quote, 2 );
    $amount       = trader_min( $asset['available'], trader_floor( bcdiv( $amount_quote, $price ), $asset_info['decimals'] ) );

    /**
     * Prevent dust.
     */
    if ( floatval( bcmul( bcsub( $asset['available'], $amount ), $price ) ) <= 2 ) {
      $amount             = $asset['available'];
      $response['amount'] = $amount;

      return wp_parse_args(
        self::get_instance()->placeOrder(
          $market,
          'sell',
          'market',
          array(
            'amount'           => $amount,
            // 'disableMarketProtection' => true,
            'responseRequired' => false,
          )
        ),
        $response
      );
    }

    return wp_parse_args(
      self::get_instance()->placeOrder(
        $market,
        'sell',
        'market',
        array(
          'amountQuote'      => $amount_quote,
          // 'disableMarketProtection' => true,
          'responseRequired' => false,
        )
      ),
      $response
    );
  }

  /**
   * {@inheritDoc}
   */
  public static function get_order( string $market, string $order_id ) : array
  {
    return self::get_instance()->getOrder( $market, $order_id );
  }

  /**
   * {@inheritDoc}
   */
  public static function cancel_order( string $market, string $order_id ) : array
  {
    return self::get_instance()->cancelOrder( $market, $order_id );
  }
}
