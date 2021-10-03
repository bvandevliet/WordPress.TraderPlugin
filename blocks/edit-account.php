<?php

defined( 'ABSPATH' ) || exit;


/**
 * Dynamic block Edit account output.
 *
 * @param [type] $block_attributes
 * @param [type] $content
 */
function trader_dynamic_block_edit_account_cb( $block_attributes, $content )
{
  /**
   * Check user capabilities.
   */
  $current_user = wp_get_current_user();
  if ( 0 >= $current_user->ID ) {
    return;
  }

  @nocache_headers();

  if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
    /**
     * Process form data ..
     */
    if ( isset( $_POST['save-account-details-nonce'] ) && wp_verify_nonce( $_POST['save-account-details-nonce'], 'update-user_' . $current_user->ID ) ) {
      $errors = new WP_Error();

      // $_POST user data.
      $first_name   = ! empty( $_POST['account_first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['account_first_name'] ) ) : '';
      $last_name    = ! empty( $_POST['account_last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['account_last_name'] ) ) : '';
      $email        = ! empty( $_POST['account_email'] ) ? sanitize_email( wp_unslash( $_POST['account_email'] ) ) : '';
      $pass_current = ! empty( $_POST['pass_current'] ) ? $_POST['pass_current'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
      $pass1        = ! empty( $_POST['pass1'] ) ? $_POST['pass1'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
      $pass2        = ! empty( $_POST['pass2'] ) ? $_POST['pass2'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
      $save_pass    = true;

      // New user data.
      $user               = new stdClass();
      $user->ID           = $current_user->ID;
      $user->first_name   = $first_name;
      $user->last_name    = $last_name;
      $user->nickname     = $first_name . ' ' . $last_name;
      $user->display_name = $first_name . ' ' . $last_name;

      // Handle required fields.
      $required_fields = array(
        'account_first_name' => __( 'First name', 'trader' ),
        'account_last_name'  => __( 'Last name', 'trader' ),
        'account_email'      => __( 'Email address', 'trader' ),
      );

      foreach ( $required_fields as $field_key => $field_name ) {
        if ( empty( $_POST[ $field_key ] ) ) {
          /* translators: %s: Field name. */
          $errors->add( sprintf( __( '%s is a required field.', 'trader' ), '<strong>' . esc_html( $field_name ) . '</strong>' ), array( 'form-field' => $field_key ) );
        }
      }

      if ( is_email( $email ) ) {
        $user->user_email = $email;
      } else {
        $errors->add( 'invalid_email', __( 'Please provide a valid email address.', 'trader' ), array( 'form-field' => 'account_email' ) );
      }

      if ( ! empty( $pass_current ) && empty( $pass1 ) && empty( $pass2 ) ) {
        $errors->add( 'pass', __( 'Please fill out all password fields.', 'trader' ), array( 'form-field' => 'pass1' ) );
        $save_pass = false;
      } elseif ( ! empty( $pass1 ) && empty( $pass_current ) ) {
        $errors->add( 'pass', __( 'Please enter your current password.', 'trader' ), array( 'form-field' => 'pass_current' ) );
        $save_pass = false;
      } elseif ( ! empty( $pass1 ) && empty( $pass2 ) ) {
        $errors->add( 'pass', __( 'Please re-enter your password.', 'trader' ), array( 'form-field' => 'pass2' ) );
        $save_pass = false;
      } elseif ( ( ! empty( $pass1 ) || ! empty( $pass2 ) ) && $pass1 !== $pass2 ) {
        $errors->add( 'pass', __( 'New passwords do not match.', 'trader' ), array( 'form-field' => 'pass2' ) );
        $save_pass = false;
      } elseif ( ! empty( $pass1 ) && ! wp_check_password( $pass_current, $current_user->user_pass, $current_user->ID ) ) {
        $errors->add( 'pass', __( 'Your current password is incorrect.', 'trader' ), array( 'form-field' => 'pass_current' ) );
        $save_pass = false;
      }
      if ( $pass1 && $save_pass ) {
        $user->user_pass = $pass1;
      }

      // Update the user.
      // $errors = edit_user( $user_id );

      if ( ! $errors->has_errors() ) {
        wp_update_user( $user );

        // headers already sent ..
        // wp_safe_redirect( add_query_arg( 'account_updated', true, get_permalink() ) );
        // exit;
      }
    }
  }

  ob_start();
  ?>

  <form action="<?php echo esc_attr( get_permalink() ); ?>" method="post">
    <?php wp_nonce_field( 'update-user_' . $current_user->ID, 'save-account-details-nonce' ); ?>

    <?php // if ( isset( $_GET['account_updated'] ) ) : // headers already sent .. ?>
    <?php if ( isset( $errors ) && is_wp_error( $errors ) && ! $errors->has_errors() ) : ?>
      <div class="updated notice is-dismissible"><p><?php esc_html_e( 'Account updated.', 'trader' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $errors ) && is_wp_error( $errors ) && $errors->has_errors() ) : ?>
      <div class="error"><p><?php echo implode( "</p>\n<p>", $errors->get_error_messages() ); ?></p></div>
    <?php endif; ?>

    <fieldset>
      <legend><?php esc_html_e( 'Personal details', 'trader' ); ?></legend>

      <p class="form-row form-row-first">
        <label for="account_first_name"><?php esc_html_e( 'First name', 'trader' ); ?>&nbsp;<span class="required">*</span></label>
        <input type="text" class="input-text" name="account_first_name" id="account_first_name" autocomplete="given-name" value="<?php echo esc_attr( $current_user->first_name ); ?>" />
      </p>
      <p class="form-row form-row-last">
        <label for="account_last_name"><?php esc_html_e( 'Last name', 'trader' ); ?>&nbsp;<span class="required">*</span></label>
        <input type="text" class="input-text" name="account_last_name" id="account_last_name" autocomplete="family-name" value="<?php echo esc_attr( $current_user->last_name ); ?>" />
      </p>
      <div class="clear"></div>
      <p class="form-row form-row-wide">
        <label for="account_email"><?php esc_html_e( 'Email address', 'trader' ); ?>&nbsp;<span class="required">*</span></label>
        <input type="email" class="input-text" name="account_email" id="account_email" autocomplete="email" value="<?php echo esc_attr( $current_user->user_email ); ?>" />
      </p>
    </fieldset>

    <fieldset>
      <legend><?php esc_html_e( 'Password change', 'trader' ); ?></legend>

      <p class="form-row form-row-wide">
        <label for="pass_current"><?php esc_html_e( 'Current password', 'trader' ); ?></label>
        <input type="password" class="input-text" name="pass_current" id="pass_current" autocomplete="off" />
      </p>
      <p class="form-row form-row-wide">
        <label for="pass1"><?php esc_html_e( 'New password', 'trader' ); ?></label>
        <input type="password" class="input-text" name="pass1" id="pass1" autocomplete="off" />
      </p>
      <p class="form-row form-row-wide">
        <label for="pass2"><?php esc_html_e( 'Confirm new password', 'trader' ); ?></label>
        <input type="password" class="input-text" name="pass2" id="pass2" autocomplete="off" />
      </p>
    </fieldset>

    <p>
      <button type="submit" class="button" value="<?php esc_attr_e( 'Save changes', 'trader' ); ?>"><?php esc_html_e( 'Save changes', 'trader' ); ?></button>
    </p>

  </form>

  <?php
  return ob_get_clean();
}
