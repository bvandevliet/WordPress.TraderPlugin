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
 * @param array     $trades    Trades executed.
 */
function trader_email_automation_triggered( int $user_id, DateTime $timestamp, \WP_Error $errors, array $trades )
{
  /**
   * Only send email if relevant.
   */
  if ( count( $trades ) === 0 && ! $errors->has_errors() ) {
    return;
  }

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

    $relevant = array_flip( array( 'ID', 'display_name', 'user_email' ) );
    wp_mail(
      $admin_email,
      $subject,
      '<pre>' . esc_html( wp_json_encode( array_intersect_key( (array) $user->data, $relevant ), JSON_PRETTY_PRINT ) ) . '</pre>' . PHP_EOL .
      '<pre>' . esc_html( wp_json_encode( $errors->error_data, JSON_PRETTY_PRINT ) ) . '</pre>',
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
      The below <?php echo count( $trades ); ?> trades were executed:
    </p>
    <div>
      <?php
        $relevant = array_flip( array( 'market', 'side', 'orderType', 'amount', 'price', 'amountQuote', 'feeExpected' ) );
        echo '<pre>'
        . implode(
          "</pre>\n<pre>",
          // phpcs:ignore WordPress.Security.EscapeOutput
          array_map(
            function( $order ) use ( $relevant )
            {
              $order['feeExpected'] = trader_ceil( $order['feeExpected'] ?? 0, 2 );
              return esc_html( wp_json_encode( array_intersect_key( $order, $relevant ), JSON_PRETTY_PRINT ) );
            },
            $trades
          )
        )
        . '</pre>';
      ?>
    </div>
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
      The below <?php echo count( $trades ); ?> trades were attempted:
    </p>
    <div>
      <?php
        // phpcs:ignore WordPress.Security.EscapeOutput
        echo '<pre>' . implode( "</pre>\n<pre>", array_map( fn( $order ) => esc_html( wp_json_encode( $order, JSON_PRETTY_PRINT ) ), $trades ) ) . '</pre>';
      ?>
    </div>
    <p>
      The below error(s) occured:
    </p>
    <div class="error"><p><?php echo implode( "</p>\n<p>", array_map( 'esc_html', $errors->get_error_messages() ) ); ?></p></div>
    <p>
      This email was automatically generated.
      We will always notify you about failed automations. The website administrator is also informed about the error(s).
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
add_action( 'trader_automation_triggered', 'trader_email_automation_triggered', 10, 4 );
