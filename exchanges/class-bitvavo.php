<?php

namespace Trader\Exchanges;

defined( 'ABSPATH' ) || exit;


/**
 * A wrapper class for the Bitvavo API.
 */
class Bitvavo extends Exchange
{
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
  public function is_tradable( string $market ) : bool
  {
    $data = $this->get_instance()->markets( array( 'market' => $market ) );

    return isset( $data['status'] ) && $data['status'] === 'trading';
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
  public function deposit_history()
  {
    $errors = new \WP_Error();

    $deposits = $this->get_instance()->depositHistory( array() );

    if ( self::check_error( $errors, $deposits )->has_errors() ) {
      return $errors;
    }

    $total  = 0;
    $prices = array( self::QUOTE_CURRENCY => 1 );

    foreach ( $deposits as $deposit ) {
      if ( $deposit['symbol'] !== self::QUOTE_CURRENCY && ! array_key_exists( $deposit['symbol'], $prices ) ) {
        $market = $deposit['symbol'] . '-' . self::QUOTE_CURRENCY;

        $price_response = $this->get_instance()->tickerPrice( array( 'market' => $market ) );

        if ( self::check_error( $errors, $price_response )->has_errors() ) {
          return $errors;
        }

        $prices[ $deposit['symbol'] ] = $price_response['price'];
      }
      $amount_quote = floatstr( bcmul( $deposit['amount'], $prices[ $deposit['symbol'] ] ) );

      $total = bcadd( $total, $amount_quote );
    }

    return compact( 'deposits', 'total' );
  }


  /**
   * {@inheritDoc}
   */
  public function withdrawal_history()
  {
    $errors = new \WP_Error();

    $withdrawals = $this->get_instance()->withdrawalHistory( array() );

    if ( self::check_error( $errors, $withdrawals )->has_errors() ) {
      return $errors;
    }

    $total  = 0;
    $prices = array( self::QUOTE_CURRENCY => 1 );

    foreach ( $withdrawals as $withdrawal ) {
      if ( $withdrawal['symbol'] !== self::QUOTE_CURRENCY && ! array_key_exists( $withdrawal['symbol'], $prices ) ) {
        $market = $withdrawal['symbol'] . '-' . self::QUOTE_CURRENCY;

        $price_response = $this->get_instance()->tickerPrice( array( 'market' => $market ) );

        if ( self::check_error( $errors, $price_response )->has_errors() ) {
          return $errors;
        }

        $prices[ $withdrawal['symbol'] ] = $price_response['price'];
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
    $errors = new \WP_Error();

    $balance_exchange = $this->get_instance()->balance( array() );

    if ( self::check_error( $errors, $balance_exchange )->has_errors() ) {
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

        $price_response = $this->get_instance()->tickerPrice( array( 'market' => $market ) );

        if ( self::check_error( $errors, $price_response )->has_errors() ) {
          return $errors;
        }

        $asset->price        = $price_response['price'];
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

    $open_orders = $this->get_instance()->ordersOpen( array() );

    if ( ! is_array( $open_orders ) || ! empty( $open_orders['errorCode'] ) ) {
      return $result;
    }

    /**
     * Run each sell order in an asyncronous thread when possible.
     */
    $pool_selloff = \Spatie\Async\Pool::create()->concurrency( 8 )->timeout( 299 );
    foreach ( $open_orders as $order ) {
      $pool_automations->add(
        function () use ( &$order, &$ignore, &$result )
        {
          if ( ! in_array( explode( '-', $order['market'] )[0], $ignore, true ) ) {
            $result[] = $this->get_instance()->cancelOrder( $order['market'], $order['orderId'] );
          }
        }
      );
    }
    $pool_selloff->wait();

    return $result;
  }


  /**
   * {@inheritDoc}
   */
  public function sell_whole_portfolio( array $ignore = array() ) : array
  {
    $result = self::cancel_all_orders( $ignore );

    $balance = $this->get_instance()->balance( array() );

    if ( ! is_array( $balance ) || ! empty( $balance['errorCode'] ) ) {
      return array(
        array(
          'errorCode'         => $balance['errorCode'] ?? 0,
          'error'             => $balance['error'] ?? __( 'An unknown error occured.', 'trader' ),
          'orderId'           => null,
          'market'            => null,
          'side'              => 'sell',
          'status'            => 'rejected',
          'amountQuote'       => '0',
          'filledAmount'      => '0',
          'filledAmountQuote' => '0',
          'feePaid'           => '0',
        ),
      );
    }

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
  public function buy_asset( string $symbol, $amount_quote, bool $simulate = false ) : array
  {
    $market = $symbol . '-' . self::QUOTE_CURRENCY;

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

    if ( $symbol === self::QUOTE_CURRENCY ) {
      return $response;
    }

    if ( (float) $amount_quote <= 0 ) {
      return $response;
    }

    $response['status'] = 'new';

    // $market_info = $this->get_instance()->markets( ['market' => $market] );
    // $min_quote  = $market_info['minOrderInQuoteAsset'];
    // $min_amount = $market_info['minOrderInBaseAsset'];

    // $price = $this->get_instance()->tickerPrice( array( 'market' => $market ) )['price'];

    // $asset_info  = $this->get_instance()->assets( ['symbol' => $symbol] );

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
  public function sell_asset( string $symbol, $amount_quote, bool $simulate = false ) : array
  {
    $market = $symbol . '-' . self::QUOTE_CURRENCY;

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

    if ( $symbol === self::QUOTE_CURRENCY ) {
      return $response;
    }

    if ( (float) $amount_quote <= 0 ) {
      return $response;
    }

    $balance = $this->get_instance()->balance( array( 'symbol' => $symbol ) );

    if ( ! is_array( $balance ) || ! empty( $balance['errorCode'] ) ) {
      $response['errorCode'] = $balance['errorCode'] ?? 0;
      $response['error']     = $balance['error'] ?? __( 'An unknown error occured.', 'trader' );
      return $response;
    }

    // phpcs:ignore WordPress.PHP.StrictComparisons
    if ( $balance[0]['available'] == 0 ) {
      return $response;
    }

    $response['status'] = 'new';

    // $market_info = $this->get_instance()->markets( ['market' => $market] );
    // $min_quote  = $market_info['minOrderInQuoteAsset'];
    // $min_amount = $market_info['minOrderInBaseAsset'];

    $price_response = $this->get_instance()->tickerPrice( array( 'market' => $market ) );

    if ( ! is_array( $price_response ) || ! empty( $price_response['errorCode'] ) ) {
      $response['errorCode'] = $price_response['errorCode'] ?? 0;
      $response['error']     = $price_response['error'] ?? __( 'An unknown error occured.', 'trader' );
      return $response;
    }

    $price = $price_response['price'];

    $asset_info = $this->get_instance()->assets( array( 'symbol' => $symbol ) );

    if ( ! is_array( $asset_info ) || ! empty( $asset_info['errorCode'] ) ) {
      $response['errorCode'] = $asset_info['errorCode'] ?? 0;
      $response['error']     = $asset_info['error'] ?? __( 'An unknown error occured.', 'trader' );
      return $response;
    }

    $amount_quote = trader_ceil( $amount_quote, 2 );
    $amount       = trader_min( $balance[0]['available'], trader_floor( bcdiv( $amount_quote, $price ), $asset_info['decimals'] ) );

    // Prevent dust.
    if ( (float) bcmul( bcsub( $balance[0]['available'], $amount ), $price ) <= 2 ) {
      $amount             = $balance[0]['available'];
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
