<?php

defined( 'ABSPATH' ) || exit;


/**
 * @param [type] $block_attributes
 * @param [type] $content
 */
function trader_dynamic_block_onchain_summary_cb( $block_attributes, $content )
{
  ob_start();

  trader_echo_onchain_summary();

  return ob_get_clean();
}
