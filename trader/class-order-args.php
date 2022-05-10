<?php

namespace Trader\OrderArgs;

defined( 'ABSPATH' ) || exit;


// phpcs:disable Generic.Files.OneObjectStructurePerFile
// phpcs:disable WordPress.NamingConventions.ValidVariableName

/**
 * Base order arguments,
 * based on Bitvavo format.
 */
abstract class BaseArgs
{
  /**
   * Sets pre-defined properties to this instance.
   *
   * @param array|object $object Array or object of args to parse into the instance.
   */
  public function parse( $object = array() )
  {
    foreach ( (array) $object as $key => $value ) {
      $this->$key = $value;
    }
  }

  /**
   * Specifies the amount of the base asset that will be bought/sold.
   *
   * @var string
   */
  public ?string $amount = null;

  /**
   * If this is set to 'true', all order information is returned.
   * Set this to 'false' when only an acknowledgement of success or failure is required, this is faster.
   *
   * @var bool
   */
  public bool $responseRequired = false;
}

/**
 * Market order args.
 */
class Market extends BaseArgs
{
  /**
   * @param mixed $amountQuote The amount of the quote currency that will be bought/sold for the best price available.
   * @param mixed $amount      The amount of the base asset that will be bought/sold for the best price available.
   *                            When specified, this will have precedence over `amountQuote`.
   */
  public function __construct( $amountQuote, $amount = null )
  {
    $this->amountQuote = null !== $amountQuote && null === $amount ? floatstr( $amountQuote ) : null;
    $this->amount      = null !== $amount ? floatstr( $amount ) : null;

    // $this->market    = $market;
    // $this->side      = $side;
    // $this->orderType = 'market';
  }

  /**
   * Only for market orders: If amountQuote is specified, [amountQuote] of the quote currency will be bought/sold for the best price available.
   *
   * @var string
   */
  public ?string $amountQuote = null;

  /**
   * Only for market orders: In order to protect clients from filling market orders with undesirable prices,
   * the remainder of market orders will be canceled once the next fill price is 10% worse than the best fill price.
   *
   * @var bool
   */
  public bool $disableMarketProtection = true;
}

/**
 * Limit order.
 */
class Limit extends BaseArgs
{
  /**
   * @param mixed $amount The amount of the base asset that will be bought/sold.
   * @param mixed $price  The amount in quote currency that is paid/received for each unit of base currency.
   */
  public function __construct( $amount, $price )
  {
    // $this->market    = $market;
    // $this->side      = $side;
    // $this->orderType = 'limit';

    $this->amount = null !== $amount ? floatstr( $amount ) : null;
    $this->price  = null !== $price ? floatstr( $price ) : null;
  }

  /**
   * Only for limit orders: Specifies the amount in quote currency that is paid/received for each unit of base currency.
   *
   * @var string
   */
  public string $price;
}
