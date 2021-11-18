<?php

namespace Trader;

defined( 'ABSPATH' ) || exit;


class Balance
{
  /**
   * Array of assets. First item must be quote asset.
   *
   * @var Asset[]
   */
  public array $assets = array();

  /**
   * Total value of balance in quote currency.
   *
   * @var string
   */
  public ?string $amount_quote_total = null;
}
