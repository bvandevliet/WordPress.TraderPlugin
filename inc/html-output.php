<?php

defined( 'ABSPATH' ) || exit;


/**
 * Print html for a basic balance overview.
 *
 * @param \Trader\Exchanges\Balance|\Trader\Exchanges\WP_Error $balance_exchange The exchange balance.
 */
function trader_echo_balance_summary( $balance_exchange )
{
  if ( ! is_wp_error( $balance_exchange ) ) :
    $deposit_history    = \Trader\Exchanges\Bitvavo::deposit_history();
    $withdrawal_history = \Trader\Exchanges\Bitvavo::withdrawal_history();

    $moneyflow_now = bcadd( $balance_exchange->amount_quote_total, $withdrawal_history['total'] );
    ?>
    <figure class="wp-block-table">
      <table class="trader-balance-summary no-wrap" style="width:auto;">
        <tr>
          <td>Total deposited</td>
          <td class="trader-number">(i)</td>
          <td class="trader-number">:</td>
          <td class="trader-number">€</td>
          <td class="trader-number"><?php echo number_format( $deposit_history['total'], 2 ); ?></td>
          <td class="trader-number"></td>
        </tr>
        <tr>
          <td>Total withdrawn</td>
          <td class="trader-number">(o)</td>
          <td class="trader-number">:</td>
          <td class="trader-number">€</td>
          <td class="trader-number"><?php echo number_format( $withdrawal_history['total'], 2 ); ?></td>
          <td class="trader-number"></td>
        </tr>
        <tr>
          <td>Current balance</td>
          <td class="trader-number">(b)</td>
          <td class="trader-number">:</td>
          <td class="trader-number">€</td>
          <td class="trader-number"><?php echo number_format( $balance_exchange->amount_quote_total, 2 ); ?></td>
          <td class="trader-number"></td>
        </tr>
        <tr>
          <td>Moneyflow</td>
          <td class="trader-number">(B=o+b)</td>
          <td class="trader-number">:</td>
          <td class="trader-number">€</td>
          <td class="trader-number"><?php echo number_format( $moneyflow_now, 2 ); ?></td>
          <td class="trader-number"></td>
        </tr>
        <tr style="border-top-width:1px;">
          <td>Total gain</td>
          <td class="trader-number">(B-i)</td>
          <td class="trader-number">:</td>
          <td class="trader-number">€</td>
          <td class="trader-number"><?php echo number_format( bcsub( $moneyflow_now, $deposit_history['total'] ), 2 ); ?></td>
          <td class="trader-number"></td>
        </tr>
        <tr>
          <td></td>
          <td class="trader-number">(B/i-1)</td>
          <td class="trader-number">:</td>
          <td class="trader-number"></td>
          <td class="trader-number"><?php echo trader_get_gain_perc( $moneyflow_now, $deposit_history['total'] ); ?></td>
          <td class="trader-number">%</td>
        </tr>
      </table>
    </figure>
    <?php
  endif;
}

/**
 * Print html for a portfolio balance overview.
 *
 * @param \Trader\Exchanges\Balance $balance         The balance to print.
 * @param boolean                   $show_current    Print current balance?
 * @param boolean                   $show_rebalanced Print rebalanced situation?
 */
function trader_echo_portfolio( \Trader\Exchanges\Balance $balance, bool $show_current = true, bool $show_rebalanced = true )
{
  ?>
  <figure class="wp-block-table">
    <table class="trader-portfolio no-wrap" style="width:auto;">
      <thead>
        <tr>
          <th><?php esc_html_e( 'Asset', 'trader' ); ?></th>
          <?php if ( $show_current ) : ?>
          <th></th>
          <th colspan="4" class="min-width"><?php esc_html_e( 'Current balance', 'trader' ); ?></th>
          <?php endif; ?>
          <?php if ( $show_rebalanced ) : ?>
          <th></th>
          <th colspan="4" class="min-width"><?php esc_html_e( 'Rebalanced situation', 'trader' ); ?></th>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ( $balance->assets as $asset ) : ?>
          <tr>
            <td><?php echo esc_html( $asset->symbol ); ?></td>
            <?php if ( $show_current ) : ?>
            <td class="trader-number"></td>
            <td class="trader-number">€</td>
            <td class="trader-number"><?php echo esc_html( number_format( $asset->amount_quote, 2 ) ); ?></td>
            <td class="trader-number"><?php echo esc_html( number_format( 100 * $asset->allocation_current, 2 ) ); ?></td>
            <td class="trader-number">%</td>
            <?php endif; ?>
            <?php if ( $show_rebalanced ) : ?>
            <td class="trader-number"></td>
            <td class="trader-number">€</td>
            <td class="trader-number"><?php echo esc_html( number_format( bcmul( reset( $asset->allocation_rebl ), $balance->amount_quote_total ), 2 ) ); ?></td>
            <td class="trader-number"><?php echo esc_html( number_format( 100 * reset( $asset->allocation_rebl ), 2 ) ); ?></td>
            <td class="trader-number">%</td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </figure>
  <?php
}

/**
 * Print html for a basic onchain indicator summary.
 *
 * @param array|null $market_cap [CapMrktCurUSD, CapRealUSD][]
 */
function trader_echo_onchain_summary( ?array $market_cap = null )
{
  $market_cap = $market_cap ?? \Trader\Metrics\CoinMetrics::market_cap( 'BTC' );

  if ( false !== $market_cap[0]['time'] ) :
    $nupl_mvrvz = \Trader\Metrics\CoinMetrics::nupl_mvrvz( $market_cap );
    $fag_index  = \Trader\Metrics\Alternative_Me::fag_index()[0]->value;
    ?>
    <figure class="wp-block-table">
      <table class="trader-onchain-summary no-wrap" style="width:auto;">
        <tr>
          <td colspan="99"><?php esc_html_e( 'BTC top is reached when ..', 'trader' ); ?></td>
        </tr>
        <tr>
          <td><a href="https://www.lookintobitcoin.com/charts/relative-unrealized-profit--loss/" target="_blank" rel="noopener noreferrer">nupl</a></td>
          <td class="trader-number">:</td>
          <td class="trader-number"><?php echo number_format( $nupl_mvrvz['nupl'], 2 ); ?></td>
          <td class="trader-number">&nbsp;<?php esc_html_e( '>=  0.75 and falling', 'trader' ); ?></td>
        </tr>
        <tr>
          <td><a href="https://www.lookintobitcoin.com/charts/mvrv-zscore/" target="_blank" rel="noopener noreferrer">mvrv_z_score</a></td>
          <td class="trader-number">:</td>
          <td class="trader-number"><?php echo number_format( $nupl_mvrvz['mvrvz'], 2 ); ?></td>
          <td class="trader-number">&nbsp;<?php esc_html_e( '>=  9.00 and falling', 'trader' ); ?></td>
        </tr>
        <tr>
          <td><a href="https://alternative.me/crypto/fear-and-greed-index/" target="_blank" rel="noopener noreferrer">fag_index</a></td>
          <td class="trader-number">:</td>
          <td class="trader-number"><?php echo number_format( $fag_index, 0 ); ?>&nbsp;&nbsp;&nbsp;</td>
          <td class="trader-number"><?php esc_html_e( '>= 80    and falling', 'trader' ); ?></td>
        </tr>
      </table>
    </figure>
    <?php
  else :
    esc_html_e( 'Something went wrong while fetching onchain indicators ..', 'trader' );
  endif;
}
