<?php

defined( 'ABSPATH' ) || exit;


/**
 * Convert a numeric input (e.g. float) to an arbitrary precision numeric string.
 *
 * @param mixed $number The numeric value to process.
 *
 * @return string The numeric string.
 */
function floatstr( $number ) : string
{
  $number = /*(string) */trader_trim( $number );

  // https://stackoverflow.com/a/21903161
  $mantissa_exp = explode( 'e', strtolower( $number ) );
  if ( ! empty( $mantissa_exp[1] ) && $mantissa_exp[1] !== '' ) {
    $number = bcmul( $mantissa_exp[0], bcpow( '10', $mantissa_exp[1] ) );
  }

  $number = preg_replace( '/^(-?)0*(\d+)(\.?\d*?)0*$|^0*(\.0*\d*?)0*$/', '$1$2$3$4', $number );

  return rtrim( preg_replace( '/^(-?)\./', '${1}0.', $number ), '.' );
}

if ( ! function_exists( 'bcabs' ) ) {
  /**
   * Arbitrary precision absolute value.
   *
   * @param mixed $number The numeric value to process.
   *
   * @return string The absolute value of number.
   */
  function bcabs( $number ) : string
  {
    return floatstr( preg_replace( '/^-/', '', $number ) );
  }
}

/**
 * Find lowest value.
 *
 * @param array|mixed ...$values Array to look through.
 *
 * @return string The numerically lowest of the parameter values.
 */
function trader_min( ...$values ) : string
{
  $values = array_map( 'floatstr', $values );

  sort( $values, SORT_NUMERIC );

  return $values[0];
}

/**
 * Find highest value.
 *
 * @param array|mixed ...$values Array to look through.
 *
 * @return string The numerically highest of the parameter values.
 */
function trader_max( ...$values ) : string
{
  $values = array_map( 'floatstr', $values );

  sort( $values, SORT_NUMERIC );

  return end( $values );
}

/**
 * Round up.
 *
 * @param mixed $number    The numeric value to round up.
 * @param int   $precision The number of decimal points.
 *
 * @return string Value rounded up to the next precision number.
 */
function trader_ceil( $number, int $precision = 0 ) : string
{
  $mult = (int) pow( 10, max( 0, $precision ) );

  return floatstr( bcdiv( ceil( bcmul( (string) $number, $mult ) ), $mult ) );
}

/**
 * Round down.
 *
 * @param mixed $number    The numeric value to round down.
 * @param int   $precision The number of decimal points.
 *
 * @return string Value rounded down to the next precision number.
 */
function trader_floor( $number, int $precision = 0 ) : string
{
  $mult = (int) pow( 10, max( 0, $precision ) );

  return floatstr( bcdiv( floor( bcmul( (string) $number, $mult ) ), $mult ) );
}

/**
 * Find the amount of decimals in a number.
 *
 * @param mixed $number The numeric value to determine the amount of decimals of.
 *
 * @return int The amount of decimals in number.
 */
function trader_get_decimals( $number ) : int
{
  $number = floatstr( $number );

  $matched = preg_match( '/\.(\d*?)0*$/', $number, $matches );
  return $matched === 1 ? strlen( $matches[1] ) : 0;
}

/**
 * Find the precision of a number.
 *
 * @param mixed $number The numeric value to determine the precision of.
 *
 * @return int The precision of number.
 */
function trader_get_precision( $number ) : int
{
  $number = floatstr( $number );

  return strlen( preg_replace( '/^-?0*(\d*)\.?(\d*?)0*$|^0*\.0*(\d*?)0*$/', '$1$2$3', $number ) );
}

/**
 * Convert a number to a fixed precision.
 *
 * @param mixed $number    The numeric value to process.
 * @param int   $precision The precision to set.
 *
 * @return string The fixed precision value of number.
 */
function trader_set_precision( $number, int $precision ) : string
{
  $decimals = trader_get_decimals( $number );

  while ( $decimals > 0 && trader_get_precision( $number ) > $precision ) {
    $decimals--;
    $number = number_format( $number, $decimals, '.', '' );
  }

  return floatstr( $number );
}

/**
 * Get allocation value.
 *
 * @param mixed $portion The numeric value representing a portion of total.
 * @param mixed $total   The numeric value representing the total.
 *
 * @return string The allocation value of portion in total.
 */
function trader_get_allocation( $portion, $total ) : string
{
  // phpcs:ignore WordPress.PHP.StrictComparisons
  return $total == 0 ? '0' : floatstr( bcdiv( floatstr( $portion ), floatstr( $total ) ) );
}

/**
 * Get percentage value.
 *
 * @param mixed $portion  The numeric value representing a portion of total.
 * @param mixed $total    The numeric value representing the total.
 * @param int   $decimals The number of decimal points, default is 2.
 *
 * @return string The percentage value of portion in total.
 */
function trader_get_percentage( $portion, $total, int $decimals = 2 ) : string
{
  // phpcs:ignore WordPress.PHP.StrictComparisons
  return number_format( $total == 0 ? '0' : bcmul( 100, bcdiv( floatstr( $portion ), floatstr( $total ) ) ), $decimals );
}

/**
 * Get gain percentage value.
 *
 * @param mixed $result   The current numeric value.
 * @param mixed $original The previous numeric value.
 * @param int   $decimals The number of decimal points, default is 2.
 *
 * @return string The percentage value of gain compared to original.
 */
function trader_get_gain_perc( $result, $original, int $decimals = 2 ) : string
{
  // phpcs:ignore WordPress.PHP.StrictComparisons
  return number_format( $original == 0 ? '0' : bcmul( 100, bcadd( bcdiv( floatstr( $result ), floatstr( $original ) ), -1 ) ), $decimals );
}

/**
 * Standard Deviation.
 *
 * @param array|mixed array $values Array of real values.
 *
 * @return string The standard deviation.
 */
function trader_st_dev( array $values ) : string
{
  $length = count( $values );
  $mean   = array_sum( $values ) / $length;

  return floatstr(
    sqrt(
      array_sum(
        array_map(
          function ( $val ) use ( $mean )
          {
            return pow( $val - $mean, 2 );
          },
          $values
        )
      ) / $length
    )
  );
}
