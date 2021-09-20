<?php

/**
 * @package           Trader
 * @author            Bob Vandevliet
 * @license           MIT
 *
 * @wordpress-plugin
 * Plugin Name:       Trader
 * Version:           xxxx.xx.xx
 * Requires at least: 5.7
 * Requires PHP:      7.4
 * Description:       Connects to exchange API's and provides blocks for rendering exchange data.
 * Author:            Bob Vandevliet
 * Author URI:        https://www.bvandevliet.nl/
 * License:           MIT
 * License URI:       https://www.gnu.org/licenses/gpl.html
 * Text Domain:       trader
//  * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;


/**
 * Increase execution times allowing some headroom to wait for order fills.
 */
ini_set( 'max_input_time', 299 );
ini_set( 'max_execution_time', 299 );
// @set_time_limit( 299 );

/**
 * Increase bcmath precision.
 */
bcscale( 24 );
ini_set( 'precision', 24 );


/**
 * Load configuration file.
 */
require __DIR__ . '/config.php';

/**
 * Composer autoload.
 */
require __DIR__ . '/vendor/autoload.php';

/**
 * Load core functions, these may rely only on native PHP.
 */
require __DIR__ . '/inc/hooks-security.php';
require __DIR__ . '/inc/functions-core.php';
require __DIR__ . '/inc/functions-math.php';

/**
 * Load metric resources.
 */
require __DIR__ . '/metrics/class-coinmarketcap.php';
require __DIR__ . '/metrics/class-coinmetrics.php';
require __DIR__ . '/metrics/class-alternative-me.php';

/**
 * Load exchange functions.
 */
require __DIR__ . '/exchanges/interface-exchange.php';
require __DIR__ . '/exchanges/class-bitvavo.php';

/**
 * Load trader classes and functions.
 */
require __DIR__ . '/trader/class-indicator.php';
require __DIR__ . '/trader/functions-trader.php';

/**
 * Load blocks.
 */
require __DIR__ . '/blocks/portfolio.php';
