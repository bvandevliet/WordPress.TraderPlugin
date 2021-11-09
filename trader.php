<?php

/**
 * @package           Trader
 * @author            Bob Vandevliet
 * @license           MIT
 *
 * @wordpress-plugin
 * Plugin Name:       Trader
 * Version:           2021.11.09
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

define( 'TRADER_PLUGIN_VERSION', '2021.11.09' );

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
 * Define plugin root.
 */
define( 'TRADER_ABSPATH', __DIR__ );


/**
 * Load configuration file if exists.
 */
if ( file_exists( __DIR__ . '/config.php' ) ) {
  require __DIR__ . '/config.php';
}

/**
 * Composer autoload.
 */
require __DIR__ . '/vendor/autoload.php';

/**
 * Load standalone core classes.
 */
require __DIR__ . '/inc/class-file-editor.php';
require __DIR__ . '/inc/class-wp-config-editor.php';

/**
 * Load core functions, these may rely only on native PHP.
 */
require __DIR__ . '/inc/hooks-security.php';
require __DIR__ . '/inc/functions-core.php';
require __DIR__ . '/inc/functions-math.php';

/**
 * Load core classes, these may rely on core functions.
 */
require __DIR__ . '/inc/class-config-editor.php';
require __DIR__ . '/inc/class-api-keys.php';

/**
 * Load metric resources.
 */
require __DIR__ . '/metrics/class-coinmarketcap.php';
require __DIR__ . '/metrics/class-coinmetrics.php';
require __DIR__ . '/metrics/class-alternative-me.php';

/**
 * Load trader objects.
 */
require __DIR__ . '/trader/class-asset.php';
require __DIR__ . '/trader/class-balance.php';

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
 * Load cron hooks.
 */
require __DIR__ . '/inc/hooks-cron.php';

/**
 * Load html element functions.
 */
require __DIR__ . '/inc/html-output.php';

/**
 * Load blocks.
 */
require __DIR__ . '/blocks/_register-blocks.php';


/**
 * Load WordPress admin pages.
 */
require __DIR__ . '/inc/class-admin-pages.php';
if ( is_admin() ) {
  \Trader\Admin_Pages::init();
}

/**
 * Register activation/deactivation/uninstall hooks.
 */
require __DIR__ . '/inc/class-trader-setup.php';
register_activation_hook( __FILE__, array( 'Trader_Setup', 'on_activation' ) );
register_deactivation_hook( __FILE__, array( 'Trader_Setup', 'on_deactivation' ) );
register_uninstall_hook( __FILE__, array( 'Trader_Setup', 'on_uninstall' ) );

/**
 * Enqueue styles and scripts
 */
add_action(
  'wp_enqueue_scripts',
  function ()
  {
    wp_enqueue_style( 'trader-plugin-styles', plugin_dir_url( __FILE__ ) . 'assets/css/style.css', array(), TRADER_PLUGIN_VERSION );
    wp_enqueue_script( 'trader-plugin-script-forms', plugin_dir_url( __FILE__ ) . 'assets/js/forms.js', array( 'jquery' ), TRADER_PLUGIN_VERSION, true );
  },
  100
);
