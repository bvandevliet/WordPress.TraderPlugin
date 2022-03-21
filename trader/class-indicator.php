<?php

namespace Trader;

defined( 'ABSPATH' ) || exit;


class Indicator extends \LupeCode\phpTraderNative\Trader
{
  // phpcs:disable WordPress.NamingConventions.ValidVariableName

  /**
   * Retrieve Market Cap EMA. This function is specific to the rebalance algorithm.
   *
   * @param array $asset_cmc_arr  Array of historical cmc asset objects of a single asset.
   * @param array $market_cap_ema Out. Smoothed Market Cap values.
   * @param int   $smoothing      The period to use for smoothing Market Cap.
   */
  public static function retrieve_market_cap_ema( array $asset_cmc_arr, &$market_cap_ema, int $smoothing = 14 )
  {
    /**
     * Calculate Exponential Moving Average of Market Cap.
     */
    $market_cap_arr = array();

    foreach ( $asset_cmc_arr as $index => $asset_cmc ) {
      $quote = ( (array) $asset_cmc->quote )[ \Trader\Exchanges\Bitvavo::QUOTE_CURRENCY ];

      $market_cap_arr[] = $quote->market_cap ?? 0;

      /**
       * Break if required amount for smoothing period is reached OR if next iteration is of more than 1 days offset.
       */
      if ( empty( $quote->market_cap ) ||
        $index + 1 >= $smoothing || $index + 1 <= trader_offset_days( $quote->last_updated )
      ) {
        break;
      }
    }

    $real   = array_reverse( $market_cap_arr );
    $period = count( $market_cap_arr );

    // calculate EMA
    $market_cap_ema = $period > 1 ? self::ema( $real, $period ) : /*array_reverse( */$market_cap_arr;/* )*/
  }

  /**
   * Volume Weighted Moving Average
   *
   * @param array $close      Closing price, array of real values.
   * @param array $volume     Volume traded, array of real values.
   * @param int   $timePeriod [OPTIONAL] [DEFAULT 14] Number of period. Usually the amount of candles within a day.
   *
   * @return array Returns an array with calculated data.
   */
  public static function vwma( array $close, array $volume, int $timePeriod = 14 )
  {
    $volume_within_period         = array();
    $volume_x_price_within_period = array();

    $vwma = array();

    $length = count( $close );

    for ( $index = 0; $index < $length; $index++ ) {
      $volume_within_period[] = abs( $volume[ $index ] );
      $volume_within_period   = array_slice( $volume_within_period, - $timePeriod );

      $volume_x_price_within_period[] = abs( $volume[ $index ] ) * $close[ $index ];
      $volume_x_price_within_period   = array_slice( $volume_x_price_within_period, - $timePeriod );

      if ( $index + 1 < $timePeriod ) {
        continue;
      }

      $vwma[] = array_sum( $volume_x_price_within_period ) / array_sum( $volume_within_period );
    }

    return $vwma;
  }

  /**
   * Volume Weighted Average Price
   *
   * The volume weighted average price (VWAP) is a trading benchmark used by traders
   * that gives the average price a security has traded at throughout the day (or the specified time period),
   * based on both volume and price.
   * It is important because it provides traders with insight into both the trend and value of a security.
   *
   * @param array $high       High price, array of real values.
   * @param array $low        Low price, array of real values.
   * @param array $close      Closing price, array of real values.
   * @param array $volume     Volume traded, array of real values.
   * @param int   $timePeriod [OPTIONAL] [DEFAULT 14] Number of period. Usually the amount of candles within a day.
   *
   * @return array Returns an array with calculated data.
   */
  public static function vwap( array $high, array $low, array $close, array $volume, int $timePeriod = 14 )
  {
    return self::vwma( $typ_price, $volume, $timePeriod );
  }
}
