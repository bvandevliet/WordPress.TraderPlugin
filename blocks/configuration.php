<?php

defined( 'ABSPATH' ) || exit;


/**
 * Dynamic block Configuration output.
 *
 * @param [type] $block_attributes
 * @param [type] $content
 */
function trader_dynamic_block_configuration_cb( $block_attributes, $content )
{
  /**
   * Check user capabilities.
   */
  $current_user = wp_get_current_user();
  if ( ! current_user_can( 'trader_manage_portfolio' ) ) {
    return;
  }

  if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
    /**
     * Process form data ..
     */
    if ( isset( $_POST['save-trader-configuration-nonce'] ) && wp_verify_nonce( $_POST['save-trader-configuration-nonce'], 'update-user_' . $current_user->ID ) ) {
      $errors = new WP_Error();

      if (
        isset( $_POST['assets'] ) && is_array( $_POST['assets'] ) &&
        isset( $_POST['weightings'] ) && is_array( $_POST['weightings'] )
      ) {
        $assets_weightings = array();

        $assets     = wp_unslash( $_POST['assets'] );
        $weightings = wp_unslash( $_POST['weightings'] );

        $length = min( count( $assets ), count( $weightings ) );

        $assets     = array_slice( $assets, 0, $length );
        $weightings = array_slice( $weightings, 0, $length );

        foreach ( $assets as $index => $asset ) {
          $asset     = strtoupper( sanitize_key( $asset ) );
          $weighting = is_numeric( $weightings[ $index ] ) ? trader_max( 0, floatstr( $weightings[ $index ] ) ) : 1;

          if ( '' !== $asset ) {
            $assets_weightings[ $asset ] = $weighting;
          }
        }
      }

      if ( ! $errors->has_errors() ) {
        update_user_meta( $current_user->ID, 'asset_weightings', $assets_weightings );
      }
    }
  }

  $assets_weightings = get_user_meta( $current_user->ID, 'asset_weightings', true );
  $assets_weightings = is_array( $assets_weightings ) ? $assets_weightings : array();
  ksort( $assets_weightings );
  arsort( $assets_weightings );

  ob_start();
  ?>

  <form action="<?php echo esc_attr( get_permalink() ); ?>" method="post">
    <?php wp_nonce_field( 'update-user_' . $current_user->ID, 'save-trader-configuration-nonce' ); ?>

    <?php if ( isset( $errors ) && is_wp_error( $errors ) && ! $errors->has_errors() ) : ?>
      <div class="updated notice is-dismissible"><p><?php esc_html_e( 'Configuration updated.', 'trader' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $errors ) && is_wp_error( $errors ) && $errors->has_errors() ) : ?>
      <div class="error"><p><?php echo implode( "</p>\n<p>", $errors->get_error_messages() ); ?></p></div>
    <?php endif; ?>

    <fieldset style="width:50%;">
      <legend>
        <?php
        _e(
          'Alternative asset allocation weighting factors.<br>
          Default is 1, set to 0 to exclude the asset (e.g. shitcoins).',
          'trader'
        );
        ?>
      </legend>

      <?php foreach ( array_merge( $assets_weightings, array( '' => 1 ) ) as $asset => $weighting ) : ?>
        <p class="form-row form-row-wide form-row-cloneable">
          <input type="text" class="input-text form-row-first" name="assets[]" autocomplete="off" value="<?php echo esc_attr( $asset ); ?>" />
          <input type="number" min="0" step=".01" class="input-number form-row-last" name="weightings[]" value="<?php echo esc_attr( $weighting ); ?>" default="1" />
        </p>
      <?php endforeach; ?>
    </fieldset>

    <p>
      <button type="submit" class="button" value="<?php esc_attr_e( 'Save changes', 'trader' ); ?>"><?php esc_html_e( 'Save changes', 'trader' ); ?></button>
    </p>

  </form>

  <?php
  return ob_get_clean();
}