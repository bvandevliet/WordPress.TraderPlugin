<?php

defined( 'ABSPATH' ) || exit;


/**
 * Print html for a basic balance overview.
 *
 * @param \Trader\Balance|\WP_Error|null $balance_exchange The exchange balance.
 * @param int|null                       $user_id          User ID for the configuration to use. Defaults to current user.
 */
function trader_echo_balance_summary( $balance_exchange = null, ?int $user_id = null )
{
  if ( $balanced_passed = $balance_exchange instanceof \Trader\Balance ) {
    $deposit_history    = \Trader\Exchanges\Bitvavo::current_user()->deposit_history();
    $withdrawal_history = \Trader\Exchanges\Bitvavo::current_user()->withdrawal_history();
    $moneyflow_now      = bcadd( $balance_exchange->amount_quote_total, $withdrawal_history['total'] );
  }
  $configuration = \Trader\Configuration::get_configuration_from_environment( $user_id );
  ?>
  <figure class="wp-block-table">
    <table class="trader-balance-summary no-wrap" style="width:auto;">
      <tr>
        <td>Total deposited</td>
        <td class="trader-number">(i)</td>
        <td class="trader-number">:</td>
        <td class="trader-number"><?php echo esc_html( $configuration->quote_currency ); ?></td>
        <td class="trader-number trader-total-deposited"><?php echo $balanced_passed ? number_format( $deposit_history['total'], 2 ) : '?'; ?></td>
        <td class="trader-number"></td>
      </tr>
      <tr>
        <td>Total withdrawn</td>
        <td class="trader-number">(o)</td>
        <td class="trader-number">:</td>
        <td class="trader-number"><?php echo esc_html( $configuration->quote_currency ); ?></td>
        <td class="trader-number trader-total-withdrawn"><?php echo $balanced_passed ? number_format( $withdrawal_history['total'], 2 ) : '?'; ?></td>
        <td class="trader-number"></td>
      </tr>
      <tr>
        <td>Current balance</td>
        <td class="trader-number">(b)</td>
        <td class="trader-number">:</td>
        <td class="trader-number"><?php echo esc_html( $configuration->quote_currency ); ?></td>
        <td class="trader-number trader-current-balance"><?php echo $balanced_passed ? number_format( $balance_exchange->amount_quote_total, 2 ) : '?'; ?></td>
        <td class="trader-number"></td>
      </tr>
      <tr>
        <td>Moneyflow</td>
        <td class="trader-number">(B=o+b)</td>
        <td class="trader-number">:</td>
        <td class="trader-number"><?php echo esc_html( $configuration->quote_currency ); ?></td>
        <td class="trader-number trader-moneyflow"><?php echo $balanced_passed ? number_format( $moneyflow_now, 2 ) : '?'; ?></td>
        <td class="trader-number"></td>
      </tr>
      <tr style="border-top-width:1px;">
        <td>Total gain</td>
        <td class="trader-number">(B-i)</td>
        <td class="trader-number">:</td>
        <td class="trader-number"><?php echo esc_html( $configuration->quote_currency ); ?></td>
        <td class="trader-number trader-total-gain-quote"><?php echo $balanced_passed ? number_format( bcsub( $moneyflow_now, $deposit_history['total'] ), 2 ) : '?'; ?></td>
        <td class="trader-number"></td>
      </tr>
      <tr>
        <td></td>
        <td class="trader-number">(B/i-1)</td>
        <td class="trader-number">:</td>
        <td class="trader-number"></td>
        <td class="trader-number trader-total-gain-perc"><?php echo $balanced_passed ? trader_get_gain_perc( $moneyflow_now, $deposit_history['total'], 2 ) : '?'; ?></td>
        <td class="trader-number">%</td>
      </tr>
    </table>
  </figure>
  <?php
}

/**
 * Print html for a portfolio balance overview.
 *
 * @param \Trader\Balance|null $balance         The balance to print.
 * @param boolean              $show_current    Print current balance?
 * @param boolean              $show_rebalanced Print rebalanced situation?
 * @param int|null             $user_id         User ID for the configuration to use. Defaults to current user.
 */
function trader_echo_portfolio( \Trader\Balance $balance = null, bool $show_current = true, bool $show_rebalanced = true, ?int $user_id = null )
{
  $balance       = $balance ?? new \Trader\Balance();
  $configuration = \Trader\Configuration::get_configuration_from_environment( $user_id );
  ?>
  <figure class="wp-block-table">
    <table class="trader-portfolio no-wrap" style="width:auto;">
      <thead>
        <tr>
          <th><?php esc_html_e( 'Asset', 'trader' ); ?></th>
          <?php if ( $show_current ) : ?>
            <th colspan="4" class="min-width"><?php esc_html_e( 'Current allocation', 'trader' ); ?></th>
          <?php endif; ?>
          <?php if ( $show_rebalanced ) : ?>
            <th colspan="4" class="min-width"><?php esc_html_e( 'Balanced allocation', 'trader' ); ?></th>
          <?php endif; ?>
          <?php if ( $show_current && $show_rebalanced ) : ?>
            <th colspan="4"><?php esc_html_e( 'Difference', 'trader' ); ?></th>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php
        foreach ( $balance->assets as $asset ) :
          $allocation_rebl    = reset( $asset->allocation_rebl ) ?? 0;
          $amount_balanced    = bcmul( $allocation_rebl, $balance->amount_quote_total );
          $alloc_perc_current = bcmul( 100, $asset->allocation_current );
          $alloc_perc_rebl    = bcmul( 100, $allocation_rebl );
          $diff               = bcsub( $alloc_perc_current, $alloc_perc_rebl );
          $diff_quote         = bcsub( $asset->amount_quote, $amount_balanced );
          ?>
          <tr>
            <td><?php echo esc_html( $asset->symbol ); ?></td>
            <?php if ( $show_current ) : ?>
              <td class="trader-number trader-no-padd-right"><?php echo esc_html( $configuration->quote_currency ); ?> </td>
              <td class="trader-number trader-no-padd-left"><?php echo esc_html( number_format( $asset->amount_quote, 2 ) ); ?></td>
              <td class="trader-number trader-no-padd-right"><?php echo esc_html( number_format( $alloc_perc_current, 2 ) ); ?></td>
              <td class="trader-number trader-no-padd-left"> %</td>
            <?php endif; ?>
            <?php if ( $show_rebalanced ) : ?>
              <td class="trader-number trader-no-padd-right"><?php echo esc_html( $configuration->quote_currency ); ?> </td>
              <td class="trader-number trader-no-padd-left"><?php echo esc_html( number_format( $amount_balanced, 2 ) ); ?></td>
              <td class="trader-number trader-no-padd-right"><?php echo esc_html( number_format( $alloc_perc_rebl, 2 ) ); ?></td>
              <td class="trader-number trader-no-padd-left"> %</td>
            <?php endif; ?>
            <?php if ( $show_current && $show_rebalanced ) : ?>
              <td class="trader-number trader-no-padd-right"><?php echo esc_html( $configuration->quote_currency ); ?> <?php echo $diff_quote >= 0 ? '+' : '-'; ?></td>
              <td class="trader-number trader-no-padd-left"><?php echo esc_html( number_format( abs( $diff_quote ), 2 ) ); ?></td>
              <td class="trader-number trader-no-padd-right"><?php echo esc_html( ( (float) $diff >= 0 ? '+' : '-' ) . number_format( abs( $diff ), 2 ) ); ?></td>
              <td class="trader-number trader-no-padd-left"> %</td>
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
  ?>
  <figure class="wp-block-table">
    <table class="trader-onchain-summary no-wrap" style="width:auto;">
      <tr>
        <td colspan="99"><?php esc_html_e( 'BTC top is reached when ..', 'trader' ); ?></td>
      </tr>
      <?php
      if ( false !== $market_cap[0]['time'] ) :
        $nupl_mvrvz = \Trader\Metrics\CoinMetrics::nupl_mvrvz( $market_cap );
        ?>
        <tr>
          <td><a href="https://www.lookintobitcoin.com/charts/relative-unrealized-profit--loss/" target="_blank" rel="noopener noreferrer">nupl</a></td>
          <td class="trader-number">:</td>
          <td class="trader-number"><?php echo number_format( $nupl_mvrvz['nupl'], 2 ); ?></td>
          <td class="trader-number"><?php esc_html_e( '>=  0.75 and falling', 'trader' ); ?></td>
        </tr>
        <tr>
          <td><a href="https://www.lookintobitcoin.com/charts/mvrv-zscore/" target="_blank" rel="noopener noreferrer">mvrv_z_score</a></td>
          <td class="trader-number">:</td>
          <td class="trader-number"><?php echo number_format( $nupl_mvrvz['mvrvz'], 2 ); ?></td>
          <td class="trader-number"><?php esc_html_e( '>=  9.00 and falling', 'trader' ); ?></td>
        </tr>
      <?php endif; ?>
      <tr>
        <td><a href="https://alternative.me/crypto/fear-and-greed-index/" target="_blank" rel="noopener noreferrer">fag_index</a></td>
        <td class="trader-number">:</td>
        <td class="trader-number"><?php echo \Trader\Metrics\Alternative_Me::fag_index_current(); ?>&nbsp;&nbsp;&nbsp;</td>
        <td class="trader-number"><?php esc_html_e( '>= 80    and falling', 'trader' ); ?></td>
      </tr>
    </table>
  </figure>
  <?php
}
