<?php

defined( 'ABSPATH' ) || exit;


/**
 * Fires on each triggered automation.
 *
 * HOW TO TRANSLATE USER SPECIFIC ? !!
 *
 * @param int       $user_id   The ID of the user to which the automation belongs.
 * @param DateTime  $timestamp The timestamp of when the automation was triggered.
 * @param \WP_Error $errors    Errors if any.
 */
function trader_email_automation_triggered( int $user_id, DateTime $timestamp, \WP_Error $errors )
{
  /**
   * Initiate default email arguments.
   */
  $admin_email   = get_option( 'admin_email' );
  $user          = get_userdata( $user_id );
  $email_headers = array(
    // Use the admin email domainname to fix the subdomain email issue.
    'From: ' . get_bloginfo( 'name' ) . ' <no-reply' . get_option( 'trader_general_from_email', $admin_email ) . '>',
    // Allow for html markup.
    'Content-Type: text/html; charset=UTF-8',
  );
  $subject       = 'Rebalance triggered';

  /**
   * Make sure the administrator is informed in case of errors.
   */
  if ( $errors->has_errors() ) {
    // Change the subject.
    $subject = 'Rebalance failed';

    wp_mail(
      $admin_email,
      $subject,
      // phpcs:ignore WordPress.PHP.DevelopmentFunctions
      '<pre>' . esc_html( print_r( $user, true ) ) . '</pre><pre>' . esc_html( print_r( $errors->error_data, true ) ) . '</pre>',
      $email_headers
    );
  }

  /**
   * Bail if user opted-out for this.
   */
  if ( ! $errors->has_errors() && ! empty( get_user_meta( $user_id, 'trader_optout_email_automation_triggered', true ) ) ) {
    return;
  }

  ob_start();
  ?>
  <p>Dear <?php echo esc_html( $user->first_name ); ?>,</p>
  <?php

  if ( ! $errors->has_errors() ) {
    ?>
    <p>
      An automatic portfolio rebalance was triggered at
      <?php echo esc_html( $timestamp->setTimezone( wp_timezone() )->format( 'Y-m-d H:i:s' ) ); ?>
      and executed successfully.
    </p>
    <p>
      <?php
      /**
       * MAKE DYNAMIC USING A SPECIFICALLY ASSIGNED ACCOUNT PAGE !!
       * NOW THE THEME MUST PROVIDE A TEMPLATE FOR THE "/account" PAGE.
       */
      ?>
      This email was automatically generated.
      If you wish to opt-out, please update your notification preferences in <a href="<?php echo esc_attr( home_url( 'account' ) ); ?>" target="_blank" rel="noopener">your account</a>.
    </p>
    <?php

  } else {
    ?>
    <p>
      An automatic portfolio rebalance was triggered at
      <?php echo esc_html( $timestamp->setTimezone( wp_timezone() )->format( 'Y-m-d H:i:s' ) ); ?>
      but failed.
      We will try again within an hour.
    </p>
    <p>
      The below errors occured.
    </p>
      <div class="error"><p><?php echo implode( "</p>\n<p>", array_map( 'esc_html', $errors->get_error_messages() ) ); ?></p></div>
    <p>
      This email was automatically generated.
      We will always notify you about failed automations. The website administrator is also informed about this error.
    </p>
    <?php
  }

  ?>
  <p>
    Happy trading!
  </p>
  <?php

  wp_mail( $user->user_email, $subject, ob_get_clean(), $email_headers );
}
add_action( 'trader_automation_triggered', 'trader_email_automation_triggered', 10, 3 );
