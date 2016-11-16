<?php
/*
Plugin Name: Restrict Content Pro - MailChimp
Plugin URL: http://pippinsplugins.com/restrict-content-pro-mailchimp/
Description: Include a MailChimp signup option with your Restrict Content Pro registration form
Version: 1.2.1
Author: Pippin Williamson
Author URI: http://pippinsplugins.com
Contributors: Pippin Williamson
Text Domain: restrict-content-pro-mailchimp
*/

//
// Helper functions

// returns the Mailchimp API object
function rcp_mailchimp_get_api() {
	$rcp_mc_options = get_option( 'rcp_mailchimp_settings' );
	$api_key        = trim( $rcp_mc_options['mailchimp_api'] );

	if ( ! empty( $api_key ) ) {
		if ( ! class_exists( 'MCAPI' ) ) {
			require_once( 'mailchimp/MCAPI.class.php' );
		}

		return new MCAPI( $api_key );
	}

	return NULL;
}

// returns the MailChimp status of a user (either pending, subscribed, unsubscribed, or cleaned) or $default
function rcp_mailchimp_get_status_by_user_id( $user_id = 0, $default = 'unknown' ) {
	if ( empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}
	$user_data = get_userdata( $user_id );
	$email     = $user_data->user_email;

	return rcp_mailchimp_get_status_by_email( $email, $default );
}

// returns the MailChimp status of an email address (either pending, subscribed, unsubscribed, or cleaned) or $default
function rcp_mailchimp_get_status_by_email( $email = '', $default = 'unknown' ) {
	// default email address is the email address of the current user
	if ( empty( $email ) && is_user_logged_in() ) {
		$user_id   = get_current_user_id();
		$user_data = get_userdata( $user_id );
		$email     = $user_data->user_email;
	}

	if ( ! empty( $email ) ) {
		// load MailChimp API class
		$api            = rcp_mailchimp_get_api();
		$rcp_mc_options = get_option('rcp_mailchimp_settings');
		$list_id        = trim( $rcp_mc_options['mailchimp_list'] );

		if ( ! empty( $list_id ) && ! empty( $api ) ) {
			// get the lists that the email address is subscribed to
			$info = $api->listMemberInfo($list_id, $email);
			if ( ! empty( $info ) && ! empty( $info['data'] ) && ! empty( $info['data'][0] ) && ! empty( $info['data'][0]['status'] ) ) {
				return $info['data'][0]['status'];
			}
		}
	}

	return $default;
}

// adds an email to the MailChimp subscription list
function rcp_mailchimp_subscribe_email( $email = '' ) {
	// default email address is the email address of the current user
	if ( empty( $email ) && is_user_logged_in() ) {
		$user_id   = get_current_user_id();
		$user_data = get_userdata( $user_id );
		$email     = $user_data->user_email;
	}

	if ( ! empty( $email ) ) {
		$mailchimp_status = rcp_mailchimp_get_status_by_email( $email );

		// unsubscribed user cannot subscribe through the API
		if ( $mailchimp_status != 'unsubscribed' ) {
			$rcp_mc_options = get_option( 'rcp_mailchimp_settings' );
			$list_id        = trim( $rcp_mc_options['mailchimp_list'] );
			$api            = rcp_mailchimp_get_api();

			if ( ! empty( $list_id ) && ! empty( $api ) ) {
				$merge_vars = apply_filters( 'rcp_mailchimp_merge_vars', array(
					'FNAME' => isset( $_POST['rcp_user_first'] ) ? sanitize_text_field( $_POST['rcp_user_first'] ) : '',
					'LNAME' => isset( $_POST['rcp_user_last'] )  ? sanitize_text_field( $_POST['rcp_user_last'] )  : ''
				), $email, $list_id );

				if ( $api->listSubscribe( $list_id, $email, $merge_vars ) === true ) {
					return true;
				}
			}
		}
	}

	return false;
}


//
// Admin page

// settings page and view of MailChimp status in 'Edit member' page
include plugin_dir_path( __FILE__ ) . 'includes/admin.php';


//
// Front-end display

// displays the mailchimp checkbox, used in registration and profile editor pages
function rcp_mailchimp_fields() {
	$rcp_mc_options = get_option('rcp_mailchimp_settings');
	$mailchimp_status = rcp_mailchimp_get_status_by_email();

	$show_checkbox = apply_filters( 'rcp_mailchimp_can_update_subscription', true );
	$show_checkbox = $show_checkbox && ! empty( $rcp_mc_options['mailchimp_api'] );
	$show_checkbox = $show_checkbox && ! empty( $rcp_mc_options['mailchimp_list'] );
	// unsubscribed users cannot use API to subscribe back
	$show_checkbox = $show_checkbox && ( $mailchimp_status != 'unsubscribed' );
	// TODO: check if the user verified his email address before managing his subscription, otherwise people could manage newsletter subscription of others

	if ( $show_checkbox ) {
		$checked_status = array( 'subscribed', 'pending' );
		$checkbox_attr  = checked( in_array( $mailchimp_status, $checked_status ) || ! is_user_logged_in(), true, false);
		$checkbox_attr .= disabled( $mailchimp_status, 'pending', false);
		?>
		<p>
			<input name="rcp_mailchimp_signup" id="rcp_mailchimp_signup" type="checkbox" <?php echo $checkbox_attr; ?>/>
			<label for="rcp_mailchimp_signup"><?php echo isset( $rcp_mc_options['signup_label'] ) ? $rcp_mc_options['signup_label'] : __( 'Signup for our newsletter', 'restrict-content-pro-mailchimp' ); ?></label>
			<?php
			if ( $mailchimp_status == 'pending' ) {
				echo '<i>' . __( 'Your subscription to the mailing list is pending. Please check your mailbox.', 'restrict-content-pro-mailchimp' ) . '</i>';
			}
			?>
		</p>
		<?php
	}
}
add_action( 'rcp_before_registration_submit_field', 'rcp_mailchimp_fields', 100 );
add_action( 'rcp_profile_editor_after', 'rcp_mailchimp_fields', 100 );

// process registration form subscribing the user to MailChimp list
function rcp_mailchimp_signup_on_registration( $posted, $user_id ) {
	if ( isset( $posted['rcp_mailchimp_signup'] ) ) {
		$email = '';
		if ( ! is_user_logged_in() ) {
			$email = $posted['rcp_user_email'];
		}
		rcp_subscribe_email( $email );
		update_user_meta( $user_id, 'rcp_subscribed_to_mailchimp', 'yes' );
	} else {
		update_user_meta( $user_id, 'rcp_subscribed_to_mailchimp', 'no' );
	}
}
add_action( 'rcp_form_processing', 'rcp_mailchimp_signup_on_registration', 10, 2 );

// process profile editor form changing MailChimp subscription usermeta value
function rcp_mailchimp_signup_on_edit_profile( $updated, $user_id, $posted ) {
	$mailchimp_signup = isset( $posted['rcp_mailchimp_signup'] ) ? 'yes' : 'no';
	update_user_meta( $user_id, 'rcp_subscribed_to_mailchimp',  $mailchimp_signup );
	return $updated;
}
add_filter( 'rcp_edit_profile_update_user', 'rcp_mailchimp_signup_on_edit_profile', 10, 3 );

// post-process profile editor form updating MailChimp user
function rcp_mailchimp_check_for_profile_edit( $user_id, $new_data, $old_data ) {
	$rcp_mc_options = get_option( 'rcp_mailchimp_settings' );
	$list_id        = trim( $rcp_mc_options['mailchimp_list'] );
	$api            = rcp_mailchimp_get_api();

	if ( ! empty( $list_id ) && ! empty( $api ) ) {
		// update the email address of the MailChimp user if it has changed
		$old_email = $old_data->user_email;
		$new_email = $new_data['user_email'];
		if ( $old_email != $new_email ) {
			$merge_vars = array( 'EMAIL' => $new_email );
			$api->listUpdateMember( $list_id, $old_email, $merge_vars );
		}

		// update the MailChimp subscription status if it has changed
		$mailchimp_status = rcp_mailchimp_get_status_by_email( $new_email );
		$wp_status        = get_user_meta( $user_id, 'rcp_subscribed_to_mailchimp', true ) == 'yes';
		$can_update       = apply_filters( 'rcp_mailchimp_can_update_subscription', true );
		$can_update       = $can_update && $mailchimp_status != 'unsubscribed'; // unsubscribed user cannot subscribe through the API
		$can_update       = $can_update && $mailchimp_status != 'pending'; // wait until user become subscribed
		// TODO: check if the user verified his email address before managing his subscription, otherwise people could manage newsletter subscription of others
		if ( $can_update && $wp_status != ( $mailchimp_status == 'subscribed' ) ) {
			if ( $wp_status ) {
				// subscribe the user to the list
				rcp_mailchimp_subscribe_email( $new_email );
			} else {
				// unsubscribe the user from the list
				$api->listUnsubscribe( $list_id, $new_email );
			}
		}
	}
}
add_action( 'rcp_user_profile_updated', 'rcp_mailchimp_check_for_profile_edit', 10, 3 );
