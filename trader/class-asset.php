<?php

namespace Trader;

defined( 'ABSPATH' ) || exit;


class Asset
{
  /**
   * Creates an instance and optionally pre-define properties.
   *
   * @param array|object $object Optional array or object of args to parse into the instance.
   */
  public function __construct( array|object $object = array() )
  {
    foreach ( (array) $object as $key => $value ) {
      $this->$key = $value;
    }
  }

  /**
   * Asset symbol.
   *
   * @var string
   */
  public string $symbol = '';

  /**
   * Price of asset in quote currency.
   *
   * @var string
   */
  public ?string $price = null;

  /**
   * Amount of asset that is directly available to trade.
   *
   * @var string
   */
  public string $available = '0';

  /**
   * Total amount of asset.
   *
   * @var string
   */
  public string $amount = '0';

  /**
   * Value of total asset amount in quote currency.
   *
   * @var string
   */
  public string $amount_quote = '0';

  /**
   * Current allocation of asset.
   *
   * @var string
   */
  public string $allocation_current = '0';

  /**
   * Associative array with rebalanced allocations.
   * string $mode => string $allocation
   *
   * @var array
   */
  public array $allocation_rebl = array();
}
