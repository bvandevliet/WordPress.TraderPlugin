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
   * Supported to trade against.
   *
   * @var string[]
   */
  public const QUOTES_SUPPORTED = array(
    'BTC',
    'EUR', // self::QUOTE_CURRENCY
  );

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
  private ?int $user_id = null;

  /**
   * {@inheritDoc}
   */
  public function __construct( ?int $user_id = null )
  {
    $this->user_id = $user_id;
  }


  /**
   * Container for the wrapper instance belonging to the current user.
   *
   * @var null|Bitvavo
   */
  private static ?Bitvavo $bitvavo_current_user = null;

  /**
   * {@inheritDoc}
   *
   * @return Bitvavo
   */
  public static function current_user() : Bitvavo
  {
    if ( null === self::$bitvavo_current_user ) {
      self::$bitvavo_current_user = new Bitvavo();
    }

    return self::$bitvavo_current_user;
  }


  /**
   * Container for the underlying parent instance of the class.
   *
   * @var null|\Bitvavo
   */
  private ?\Bitvavo $instance = null;

  /**
   * {@inheritDoc}
   *
   * @return \Bitvavo
   */
  public function get_instance() : \Bitvavo
  {
    if ( null === $this->instance ) {
      $api_key    = \Trader\API_Keys::get_api_key( 'bitvavo_key', $this->user_id );
      $api_secret = \Trader\API_Keys::get_api_key( 'bitvavo_secret', $this->user_id );

      /**
       * ERROR HANDLING SOMEHOW !!
       *
       * @link https://github.com/ccxt/ccxt/blob/e7ad04747f72e601eead58b02899b75126dc619d/js/bitvavo.js#L146
       * if( ! empty( $response['errorCode'] ) && in_array( $response['errorCode'], array() ) ) {}
       */
      $this->instance = new \Bitvavo(
        array(
          'APIKEY'       => $api_key,
          'APISECRET'    => $api_secret,
          'ACCESSWINDOW' => 10000,
          'DEBUGGING'    => false,
        )
      );
    }

    return $this->instance;
  }


  /**
   * {@inheritDoc}
   */
  public function candles( string $market, string $chart, array $args = array() ) : array
  {
    return $this->get_instance()->candles( $market, $chart, $args );
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
  public function deposit_history() : array
  {
    $deposits = $this->get_instance()->depositHistory( array() );
    $total    = 0;
    $prices   = array( self::QUOTE_CURRENCY => 1 );

    foreach ( $deposits as $deposit ) {
      if ( $deposit['symbol'] !== self::QUOTE_CURRENCY && ! array_key_exists( $deposit['symbol'], $prices ) ) {
        $market                       = $deposit['symbol'] . '-' . self::QUOTE_CURRENCY;
        $prices[ $deposit['symbol'] ] = $this->get_instance()->tickerPrice( array( 'market' => $market ) )['price'];
      }
      $amount_quote = floatstr( bcmul( $deposit['amount'], $prices[ $deposit['symbol'] ] ) );

      $total = bcadd( $total, $amount_quote );
    }

    return compact( 'deposits', 'total' );
  }


  /**
   * {@inheritDoc}
   */
  public function withdrawal_history() : array
  {
    $withdrawals = $this->get_instance()->withdrawalHistory( array() );
    $total       = 0;
    $prices      = array( self::QUOTE_CURRENCY => 1 );

    foreach ( $withdrawals as $withdrawal ) {
      if ( $withdrawal['symbol'] !== self::QUOTE_CURRENCY && ! array_key_exists( $withdrawal['symbol'], $prices ) ) {
        $market                          = $withdrawal['symbol'] . '-' . self::QUOTE_CURRENCY;
        $prices[ $withdrawal['symbol'] ] = $this->get_instance()->tickerPrice( array( 'market' => $market ) )['price'];
      }
      $amount_quote = floatstr( bcmul( $withdrawal['amount'], $prices[ $withdrawal['symbol'] ] ) );

      $total = bcadd( $total, $amount_quote );
    }

    return compact( 'withdrawals', 'total' );
  }


  /**
   * {@inheritDoc}
   */
  public function get_balance()
  {
    $balance_exchange = $this->get_instance()->balance( array() );

    if ( ! is_array( $balance_exchange ) || ! empty( $balance_exchange['errorCode'] ) ) {
      $errors = new \WP_Error();
      $errors->add(
        'exchange_bitvavo-' . ( $balance_exchange['errorCode'] ?? 0 ),
        __( 'Exchange error: ', 'trader' ) . ( $balance_exchange['error'] ?? __( 'An unknown error occured.', 'trader' ) )
      );
      return $errors;
    }

    $balance = new \Trader\Balance();

    for ( $i = 0, $length = count( $balance_exchange ); $i < $length; $i++ ) {
      $asset = new \Trader\Asset();

      $asset->symbol       = self::QUOTE_CURRENCY;
      $asset->price        = '1';
      $asset->available    = $balance_exchange[ $i ]['available'];
      $asset->amount       = floatstr( bcadd( $asset->available, $balance_exchange[ $i ]['inOrder'] ) );
      $asset->amount_quote = $asset->amount;

      if ( $i > 0 ) { // $balance_exchange[$i]['symbol'] !== self::QUOTE_CURRENCY )
        $asset->symbol = $balance_exchange[ $i ]['symbol'];
        $market        = $asset->symbol . '-' . self::QUOTE_CURRENCY;

        $asset->price        = $this->get_instance()->tickerPrice( array( 'market' => $market ) )['price'];
        $asset->amount_quote = floatstr( bcmul( $asset->amount, $asset->price ) );
      }

      $balance->amount_quote_total = bcadd( $balance->amount_quote_total, $asset->amount_quote );

      // phpcs:ignore WordPress.PHP.StrictComparisons
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
  public function cancel_all_orders( array $ignore = array() ) : array
  {
    $result = array();

    foreach ( $this->get_instance()->ordersOpen( array() ) as $order ) {
      if ( ! in_array( explode( '-', $order['market'] )[0], $ignore, true ) ) {
        $result[] = $this->get_instance()->cancelOrder( $order['market'], $order['orderId'] );
      }
    }

    return $result;
  }


  /**
   * {@inheritDoc}
   */
  public function sell_whole_portfolio( array $ignore = array() ) : array
  {
    $result = self::cancel_all_orders( $ignore );

    $balance = $this->get_instance()->balance( array() );

    foreach ( $balance as $asset ) {
      if ( $asset['symbol'] === self::QUOTE_CURRENCY ) {
        continue;
      }

      // phpcs:ignore WordPress.PHP.StrictComparisons
      if ( $asset['available'] == 0 || in_array( $asset['symbol'], $ignore, true ) ) {
        continue;
      }

      $market = $asset['symbol'] . '-' . self::QUOTE_CURRENCY;

      $amount = floatstr( $asset['available'] );

      $result[] = $this->get_instance()->placeOrder(
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
  public function buy_asset( string $market, $amount_quote, bool $simulate = false ) : array
  {
    $symbol_quote   = explode( '-', $market );
    $symbol         = $symbol_quote[0];
    $quote_currency = $symbol_quote[1];

    $response = array(
      'orderId'           => null,
      'market'            => $market,
      'side'              => 'buy',
      'status'            => 'rejected',
      'amountQuote'       => &$amount_quote,
      'filledAmount'      => '0',
      'filledAmountQuote' => '0',
      'feePaid'           => '0',
    );

    if ( $symbol === $quote_currency ) {
      return $response;
    }

    if ( floatval( $amount_quote ) <= 0 ) {
      return $response;
    }

    $response['status'] = 'new';

    // $market_info = $this->get_instance()->markets( ['market' => $market] );
    // $asset_info  = $this->get_instance()->assets( ['symbol' => $symbol] );

    // $min_quote  = $market_info['minOrderInQuoteAsset'];
    // $min_amount = $market_info['minOrderInBaseAsset'];

    $price = $this->get_instance()->tickerPrice( array( 'market' => $market ) )['price'];
    // $book  = $this->get_instance()->tickerBook( ['market' => $market] );

    $amount_quote = trader_floor( $amount_quote, 2 );
    // $amount       = trader_floor( bcdiv( $amount_quote, $price ), $asset_info['decimals'] );

    return wp_parse_args(
      $simulate
        ? array(
          'feePaid' => floatstr( bcmul( $amount_quote, self::TAKER_FEE ) ),
        )
        : $this->get_instance()->placeOrder(
          $market,
          'buy',
          'market',
          array(
            'amountQuote'             => $amount_quote,
            'disableMarketProtection' => true,
            'responseRequired'        => false,
          )
        ),
      $response
    );
  }


  /**
   * {@inheritDoc}
   */
  public function sell_asset( string $market, $amount_quote, bool $simulate = false ) : array
  {
    $symbol_quote   = explode( '-', $market );
    $symbol         = $symbol_quote[0];
    $quote_currency = $symbol_quote[1];

    $response = array(
      'orderId'           => null,
      'market'            => $market,
      'side'              => 'sell',
      'status'            => 'rejected',
      'amountQuote'       => &$amount_quote,
      'filledAmount'      => '0',
      'filledAmountQuote' => '0',
      'feePaid'           => '0',
    );

    if ( $symbol === $quote_currency ) {
      return $response;
    }

    if ( floatval( $amount_quote ) <= 0 ) {
      return $response;
    }

    $asset = $this->get_instance()->balance( array( 'symbol' => $symbol ) )[0];

    // phpcs:ignore WordPress.PHP.StrictComparisons
    if ( $asset['available'] == 0 ) {
      return $response;
    }

    $response['status'] = 'new';

    // $market_info = $this->get_instance()->markets( ['market' => $market] );
    $asset_info = $this->get_instance()->assets( array( 'symbol' => $symbol ) );

    // $min_quote  = $market_info['minOrderInQuoteAsset'];
    // $min_amount = $market_info['minOrderInBaseAsset'];

    $price = $this->get_instance()->tickerPrice( array( 'market' => $market ) )['price'];
    // $book  = $this->get_instance()->tickerBook( ['market' => $market] );

    $amount_quote = trader_ceil( $amount_quote, 2 );
    $amount       = trader_min( $asset['available'], trader_floor( bcdiv( $amount_quote, $price ), $asset_info['decimals'] ) );

    /**
     * Prevent dust.
     */
    if ( floatval( bcmul( bcsub( $asset['available'], $amount ), $price ) ) <= 2 ) {
      $amount             = $asset['available'];
      $response['amount'] = $amount;
      $amount_quote       = bcmul( $amount, $price );

      return wp_parse_args(
        $simulate
          ? array(
            'amountQuote' => floatstr( $amount_quote ),
            'feePaid'     => floatstr( bcmul( $amount_quote, self::TAKER_FEE ) ),
          )
          : $this->get_instance()->placeOrder(
            $market,
            'sell',
            'market',
            array(
              'amount'                  => $amount,
              'disableMarketProtection' => true,
              'responseRequired'        => false,
            )
          ),
        $response
      );
    }

    return wp_parse_args(
      $simulate
        ? array(
          'feePaid' => floatstr( bcmul( $amount_quote, self::TAKER_FEE ) ),
        )
        : $this->get_instance()->placeOrder(
          $market,
          'sell',
          'market',
          array(
            'amountQuote'             => $amount_quote,
            'disableMarketProtection' => true,
            'responseRequired'        => false,
          )
        ),
      $response
    );
  }

  /**
   * {@inheritDoc}
   */
  public function get_order( string $market, string $order_id ) : array
  {
    return $this->get_instance()->getOrder( $market, $order_id );
  }

  /**
   * {@inheritDoc}
   */
  public function cancel_order( string $market, string $order_id ) : array
  {
    return $this->get_instance()->cancelOrder( $market, $order_id );
  }
}
