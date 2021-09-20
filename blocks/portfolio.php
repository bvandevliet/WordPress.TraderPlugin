<?php

defined( 'ABSPATH' ) || exit;


/**
 * Dynamic block Portfolio output.
 *
 * @param [type] $block_attributes
 * @param [type] $content
 *
 * @return void
 */
function trader_dynamic_block_portfolio_cb( $block_attributes, $content )
{
  /**
   * Define alternative portfolio allocation weighting for specific coins.
   * Default is 1, set to 0 to skip the coin.
   *
   * TEMPORARY, MOVE TO DATABASE !!
   */
  $asset_weightings = array(
    'BTC'   => .9,
    'ETH'   => .95,
    'BNB'   => 0,
    'MATIC' => 0,
    'IOTA'  => 0,
    'MIOTA' => 0,
    'TRX'   => 0,
    'XRP'   => 0,
    'XLM'   => 0,
    'AVAX'  => 0,
    'CAKE'  => 0,
    'SHIB'  => 0,
    'BCH'   => 0,
    'BSV'   => 0,
    'WBTC'  => 0,
    'BTCB'  => 0,
    'ETC'   => 0,
    'DOGE'  => 0,
  );

  ob_start();
  echo '<pre><code>';

  $balance = \Trader\merge_balance( \Trader\get_asset_allocations( $asset_weightings ), \Trader\Exchanges\Bitvavo::get_balance() );

  $deposit_total    = 0;
  $withdrawal_total = 0;

  foreach ( \Trader\Exchanges\Bitvavo::get_instance()->depositHistory( array( 'symbol' => 'EUR' ) ) as $deposit ) {
    $deposit_total = bcadd( $deposit_total, $deposit['amount'] );
  }
  foreach ( \Trader\Exchanges\Bitvavo::get_instance()->withdrawalHistory( array( 'symbol' => 'EUR' ) ) as $withdrawal ) {
    $withdrawal_total = bcadd( $withdrawal_total, $withdrawal['amount'] );
  }

  $moneyflow_now = bcadd( $balance['amount_quote_total'], $withdrawal_total );

  echo ''
  . '    DEPOSIT TOTAL (i)         : €' . str_pad( number_format( $deposit_total, 2 ), 10, ' ', STR_PAD_LEFT ) . '<br>'
  . ' WITHDRAWAL TOTAL (o)         : €' . str_pad( number_format( $withdrawal_total, 2 ), 10, ' ', STR_PAD_LEFT ) . '<br>'
  . '      BALANCE NOW (b)         : €' . str_pad( number_format( $balance['amount_quote_total'], 2 ), 10, ' ', STR_PAD_LEFT ) . '<br>'
  . '    MONEYFLOW NOW (B=o+b)     : €' . str_pad( number_format( $moneyflow_now, 2 ), 10, ' ', STR_PAD_LEFT ) . '<br>'
  . '       GAIN TOTAL (B-i)       : €' . str_pad( number_format( bcsub( $moneyflow_now, $deposit_total ), 2 ), 10, ' ', STR_PAD_LEFT ) . '<br>'
  . '       GAIN TOTAL (B/i-1)     :  ' . str_pad( trader_get_gain_perc( $moneyflow_now, $deposit_total ), 10, ' ', STR_PAD_LEFT ) . '%' . '<br>';

  $market_cap = \Trader\Metrics\CoinMetrics::market_cap( 'BTC' );
  $nupl_mvrvz = \Trader\Metrics\CoinMetrics::nupl_mvrvz( $market_cap );
  $fag_index  = \Trader\Metrics\Alternative_Me::fag_index()[0]->value;

  echo '<br>';
  echo 'BTC top is reached when ..' . '<br>';
  echo '<a href="https://www.lookintobitcoin.com/charts/relative-unrealized-profit--loss/"
        target="_blank" rel="noopener noreferrer"
        >nupl</a>         :  ' . number_format( $nupl_mvrvz['nupl'], 2 ) . ' >=  0.75 and falling' . '<br>';
  echo '<a href="https://www.lookintobitcoin.com/charts/mvrv-zscore/"
        target="_blank" rel="noopener noreferrer"
        >mvrv_z_score</a> :  ' . number_format( $nupl_mvrvz['mvrvz'], 2 ) . ' >=  9.00 and falling' . '<br>';
  echo '<a href="https://alternative.me/crypto/fear-and-greed-index/"
        target="_blank" rel="noopener noreferrer"
        >fag_index</a>    : ' . number_format( $fag_index, 0 ) . '    >= 80    and falling' . '<br>';

  foreach ( $balance['assets'] as $asset ) {
    echo '<br>'
    . str_pad( $asset->symbol, 6, ' ', STR_PAD_LEFT ) . ':'
    . str_pad( number_format( 100 * reset( $asset->allocation_rebl ), 2 ), 7, ' ', STR_PAD_LEFT ) . '%'
    . str_pad( number_format( 100 * $asset->allocation_current, 2 ), 7, ' ', STR_PAD_LEFT ) . '%'
    . '  €' . str_pad( number_format( $asset->amount_quote, 2 ), 8, ' ', STR_PAD_LEFT );
  }

  echo '<br>';

  echo '</code></pre>';
  return ob_get_clean();
}


add_filter(
  class_exists( 'WP_Block_Editor_Context' ) ? 'block_categories_all' : 'block_categories', // back-compat <5.8
  function ( $block_categories, $editor_context )
  {
    if ( ! empty( $editor_context->post ) ) {
      array_push(
        $block_categories,
        array(
          'slug'  => 'trader',
          'title' => __( 'Trader', 'trader' ),
          'icon'  => 'dashicons-chart-line',
        )
      );
    }
    return $block_categories;
  },
  10,
  2
);


add_action(
  'init',
  function ()
  {
    wp_register_script(
      'trader-dynamic-block-portfolio-editor-js',
      plugins_url( 'portfolio.js', __FILE__ ),
      array( 'jquery' ),
      '1',
      true
    );

    /**
     * @link https://developer.wordpress.org/reference/functions/register_block_type/
     */
    register_block_type(
      'trader/portfolio',
      array(
        'api_version'     => 2,
        'title'           => 'Portfolio',
        'description'     => 'Shows current portfolio asset allocations.',
        'icon'            => 'editor-ol',
        'category'        => 'trader',
        // 'script'          => 'trader-dynamic-block-portfolio-js',
        'editor_script'   => 'trader-dynamic-block-portfolio-editor-js',
        // 'style'           => 'trader-dynamic-block-portfolio-css',
        // 'editor_style'    => 'trader-dynamic-block-portfolio-editor-css',
        'render_callback' => 'trader_dynamic_block_portfolio_cb',
      )
    );
  }
);
