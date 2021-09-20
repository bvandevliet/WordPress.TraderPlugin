<?php

namespace Trader;

defined( 'ABSPATH' ) || exit;


class Indicator extends \LupeCode\phpTraderNative\Trader
{
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
