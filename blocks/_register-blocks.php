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
      'trader/portfolio' => array(
        'title'       => 'Portfolio',
        'description' => 'Shows current portfolio asset allocations.',
        'icon'        => 'editor-ol',
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
    wp_add_inline_script(
      'trader-dynamic-blocks-editor-js',
      'window.trader_dynamic_blocks = [];',
      'before'
    );

    /**
     * Register dynamic blocks.
     */
    foreach ( $blocks as $block_type => $args ) {
      wp_add_inline_script(
        'trader-dynamic-blocks-editor-js',
        'window.trader_dynamic_blocks.push(\'' . esc_js( $block_type ) . '\');',
        'before'
      );
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
            'render_callback' => 'trader_dynamic_block_' . explode( '/', $block_type )[1] . '_cb',
          )
        )
      );
    }
  }
);
