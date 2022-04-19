<?php

defined( 'ABSPATH' ) || exit;


/**
 * Register the Trader block category.
 */
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

/**
 * Enqueue styles and scripts.
 *
 * HOW TO PROPERLY APPLY ALL THEME AND PLUGIN FRONT-END STYLES ALSO ON BLOCKS WHILE IN THE EDITOR ? !!
 */
add_action(
  'wp_enqueue_scripts',
  function ()
  {
    wp_enqueue_script( 'trader-plugin-script-ajax', TRADER_URL . 'blocks/js/ajax.js', array( 'jquery' ), TRADER_PLUGIN_VERSION, false );
    wp_localize_script(
      'trader-plugin-script-ajax',
      'ajax_obj',
      array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'trader_ajax' ),
      )
    );
  },
  100
);

/**
 * Register dynamic block types.
 */
add_action(
  'init',
  function ()
  {
    /**
     * Dynamic blocks to be registered.
     */
    $blocks = array(
      'trader/portfolio'       => array(
        'title'       => 'Portfolio',
        'description' => 'Shows current and rebalanced portfolio asset allocations.',
        'icon'        => 'chart-pie',
      ),
      'trader/rebalance-form'  => array(
        'title'       => 'Rebalance form',
        'description' => 'A form to configure and trigger portfolio rebalance.',
        'icon'        => 'admin-settings',
      ),
      'trader/configuration'   => array(
        'title'       => 'Configuration form',
        'description' => 'A form to configure additional rebalance parameters.',
        'icon'        => 'admin-settings',
      ),
      'trader/balance-summary' => array(
        'title'       => 'Balance summary',
        'description' => 'Shows basic balance information.',
        'icon'        => 'money-alt',
      ),
      'trader/onchain-summary' => array(
        'title'       => 'Onchain summary',
        'description' => 'Shows basic onchain indicator information.',
        'icon'        => 'chart-line',
      ),
      'trader/edit-account'    => array(
        'title'       => 'Edit account form',
        'description' => 'A form to edit account details.',
        'icon'        => 'admin-users',
      ),
      'trader/exchange-apis'   => array(
        'title'       => 'Exchange API keys',
        'description' => 'A form to edit API keys for the current user\'s exchanges.',
        'icon'        => 'admin-network',
      ),
    );

    /**
     * Register global dynamic blocks script and instantiate global `trader_dynamic_blocks` variable.
     */
    wp_register_script( 'trader-dynamic-blocks-editor-js', TRADER_URL . 'blocks/_dynamic-blocks.js', array(), TRADER_PLUGIN_VERSION, true );

    $trader_dynamic_blocks = array();

    /**
     * Register dynamic blocks.
     */
    foreach ( $blocks as $block_type => $args ) {
      $block_name = explode( '/', $block_type )[1];

      require_once __DIR__ . '/block-' . $block_name . '.php';

      $trader_dynamic_blocks[] = $block_type;

      register_block_type(
        $block_type,
        wp_parse_args(
          $args,
          array(
            'api_version'     => 2,
            'category'        => 'trader',
            'editor_script'   => 'trader-dynamic-blocks-editor-js',
            // 'script'          => 'trader-dynamic-block-' . $block_type . '-js',
            // 'style'           => 'trader-dynamic-block-' . $block_type . '-css',
            'render_callback' => 'trader_dynamic_block_' . str_replace( '-', '_', $block_name ) . '_cb',
          )
        )
      );
    }

    wp_add_inline_script(
      'trader-dynamic-blocks-editor-js',
      'window.trader_dynamic_blocks = ' . wp_json_encode( $trader_dynamic_blocks ) . ';',
      'before'
    );
  }
);
