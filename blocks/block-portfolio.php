<?php

defined( 'ABSPATH' ) || exit;


/**
 * @param [type] $block_attributes
 * @param [type] $content
 */
function trader_dynamic_block_portfolio_cb( $block_attributes, $content )
{
  ob_start();

  trader_echo_portfolio();

  return ob_get_clean();
}
