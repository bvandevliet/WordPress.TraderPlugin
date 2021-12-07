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
      'trader/portfolio'     => array(
        'title'       => 'Portfolio',
        'description' => 'Shows current portfolio asset allocations.',
        'icon'        => 'editor-ol',
      ),
      'trader/configuration' => array(
        'title'       => 'Configuration',
        'description' => 'A form to configure trading parameters.',
        'icon'        => 'admin-generic',
      ),
      'trader/edit-account'  => array(
        'title'       => 'Edit account',
        'description' => 'A form to edit account details.',
        'icon'        => 'admin-users',
      ),
      'trader/exchange-apis' => array(
        'title'       => 'Exchange API keys',
        'description' => 'A form to edit API keys for the current user\'s exchanges.',
        'icon'        => 'admin-network',
      ),
    );

    /**
     * Register global dynamic blocks script and instantiate global `trader_dynamic_blocks` variable.
     */
    wp_register_script(
      'trader-dynamic-blocks-editor-js',
      plugins_url( '_dynamic-blocks.js', __FILE__ ),
      array(),
      '1',
      true
    );

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
