<?php

//
// Helper functions

// get an array of all MailChimp subscription lists
function rcp_get_mailchimp_lists() {
	$rcp_mc_options = get_option( 'rcp_mailchimp_settings' );

	if ( ! empty( $rcp_mc_options['mailchimp_api'] ) ) {
		$lists = array();
		$api = rcp_mailchimp_get_api();
		$list_data = $api->lists();
		if ( $list_data ) {
			foreach ( $list_data['data'] as $key => $list ) {
				$lists[ $key ]['id']   = $list['id'];
				$lists[ $key ]['name'] = $list['name'];
			}
		}
		return $lists;
	}
	return false;
}


//
// MailChimp settings page

// add settings page
function rcp_mailchimp_settings_menu() {
	add_submenu_page( 'rcp-members', __( 'Restrict Content Pro MailChimp Settings', 'restrict-content-pro-mailchimp' ), __( 'MailChimp', 'restrict-content-pro-mailchimp' ), 'manage_options', 'rcp-mailchimp', 'rcp_mailchimp_settings_page' );
}
add_action( 'admin_menu', 'rcp_mailchimp_settings_menu', 100 );

// register the plugin settings
function rcp_mailchimp_register_settings() {
	register_setting( 'rcp_mailchimp_settings_group', 'rcp_mailchimp_settings' );
}
add_action( 'admin_init', 'rcp_mailchimp_register_settings', 100 );

// content of the settings page
function rcp_mailchimp_settings_page() {
	$rcp_mc_options = get_option( 'rcp_mailchimp_settings' );
	$saved_list     = isset( $rcp_mc_options['mailchimp_list'] ) ? $rcp_mc_options['mailchimp_list'] : false;

	?>
	<div class="wrap">
		<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>
		<?php
		if ( ! isset( $_REQUEST['updated'] ) )
			$_REQUEST['updated'] = false;
		?>
		<?php if ( false !== $_REQUEST['updated'] ) : ?>
		<div class="updated fade"><p><strong><?php _e( 'Options saved', 'restrict-content-pro-mailchimp' ); ?></strong></p></div>
		<?php endif; ?>
		<form method="post" action="options.php" class="rcp_options_form">

			<?php settings_fields( 'rcp_mailchimp_settings_group' ); ?>
			<?php $lists = rcp_get_mailchimp_lists(); ?>

			<table class="form-table">

				<tr>
					<th>
						<label for="rcp_mailchimp_settings[mailchimp_api]"><?php _e( 'MailChimp API Key', 'restrict-content-pro-mailchimp' ); ?></label>
					</th>
					<td>
						<input class="regular-text" type="text" id="rcp_mailchimp_settings[mailchimp_api]" name="rcp_mailchimp_settings[mailchimp_api]" value="<?php if ( isset( $rcp_mc_options['mailchimp_api'] ) ) { echo $rcp_mc_options['mailchimp_api']; } ?>"/>
						<div class="description"><?php _e( 'Enter your MailChimp API key to enable a newsletter signup option with the registration form.', 'restrict-content-pro-mailchimp' ); ?></div>
					</td>
				</tr>
				<tr>
					<th>
						<label for="rcp_mailchimp_settings[mailchimp_list]"><?php _e( 'Newsletter List', 'restrict-content-pro-mailchimp' ); ?></label>
					</th>
					<td>
						<select id="rcp_mailchimp_settings[mailchimp_list]" name="rcp_mailchimp_settings[mailchimp_list]">
							<?php
								if ( $lists ) :
									foreach ( $lists as $list ) :
										echo '<option value="' . esc_attr( $list['id'] ) . '"' . selected( $saved_list, $list['id'], false ) . '>' . esc_html( $list['name'] ) . '</option>';
									endforeach;
								else :
							?>
							<option value="no list"><?php _e( 'no lists', 'restrict-content-pro-mailchimp' ); ?></option>
						<?php endif; ?>
						</select>
						<div class="description"><?php _e( 'Choose the list to subscribe users to', 'restrict-content-pro-mailchimp' ); ?></div>
					</td>
				</tr>
				<tr>
					<th>
						<label for="rcp_mailchimp_settings[signup_label]"><?php _e( 'Form Label', 'restrict-content-pro-mailchimp' ); ?></label>
					</th>
					<td>
						<input class="regular-text" type="text" id="rcp_mailchimp_settings[signup_label]" name="rcp_mailchimp_settings[signup_label]" value="<?php if ( isset( $rcp_mc_options['signup_label'] ) ) { echo $rcp_mc_options['signup_label']; } ?>"/>
						<div class="description"><?php _e( 'Enter the label to be shown on the "Signup for Newsletter" checkbox', 'restrict-content-pro-mailchimp' ); ?></div>
					</td>
				</tr>
			</table>
			<!-- save the options -->
			<p class="submit">
				<input type="submit" class="button-primary" value="<?php _e( 'Save Options', 'restrict-content-pro-mailchimp' ); ?>" />
			</p>

		</form>
	</div><!--end .wrap-->
	<?php
}

// adds the style of the settings page
function rcp_mailchimp_admin_styles() {
	wp_enqueue_style( 'rcp-admin', RCP_PLUGIN_DIR . 'includes/css/admin-styles.css' );
}
if ( isset( $_GET['page'] ) && ( $_GET['page'] == 'rcp-mailchimp' ) ) {
	add_action('admin_enqueue_scripts', 'rcp_mailchimp_admin_styles');
}

//
// Field in 'Edit member' page

// adds the field
function rcp_mailchimp_add_edit_member_field( $user_id ) {
	?>
	<tr valign="top">
		<th>Mailchimp status</th>
		<td><?php echo rcp_mailchimp_get_status_by_user_id( $user_id ); ?></td>
	</tr>
	<?php
}
add_action('rcp_edit_member_after', 'rcp_mailchimp_add_edit_member_field');
