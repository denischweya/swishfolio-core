<?php
/**
 * Swish Forms Email Settings Admin Page
 *
 * @package SwishfolioCore
 */

use SwishfolioCore\Services\EncryptionService;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$encryption = new EncryptionService();
$settings = get_option( 'swish_forms_email_settings', [] );

// Handle form submission.
if ( isset( $_POST['swish_forms_email_submit'] ) ) {
	if ( check_admin_referer( 'swish_forms_email_settings' ) ) {
		$newSettings = [
			'from_email'       => sanitize_email( $_POST['from_email'] ?? '' ),
			'from_name'        => sanitize_text_field( $_POST['from_name'] ?? '' ),
			'smtp_enabled'     => isset( $_POST['smtp_enabled'] ),
			'smtp_host'        => sanitize_text_field( $_POST['smtp_host'] ?? '' ),
			'smtp_port'        => absint( $_POST['smtp_port'] ?? 587 ),
			'smtp_encryption'  => sanitize_text_field( $_POST['smtp_encryption'] ?? 'tls' ),
			'smtp_username'    => sanitize_text_field( $_POST['smtp_username'] ?? '' ),
			'smtp_password'    => ! empty( $_POST['smtp_password'] ) ? $encryption->encrypt( $_POST['smtp_password'] ) : ( $settings['smtp_password'] ?? '' ),
		];

		update_option( 'swish_forms_email_settings', $newSettings );
		$settings = $newSettings;

		echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', 'swishfolio-core' ) . '</p></div>';
	}
}

// Get current values.
$fromEmail = $settings['from_email'] ?? get_option( 'admin_email' );
$fromName = $settings['from_name'] ?? get_option( 'blogname' );
$smtpEnabled = $settings['smtp_enabled'] ?? false;
$smtpHost = $settings['smtp_host'] ?? '';
$smtpPort = $settings['smtp_port'] ?? 587;
$smtpEncryption = $settings['smtp_encryption'] ?? 'tls';
$smtpUsername = $settings['smtp_username'] ?? '';
$smtpPasswordSet = ! empty( $settings['smtp_password'] );
?>
<div class="wrap swish-forms-admin">
	<h1><?php esc_html_e( 'Email Settings', 'swishfolio-core' ); ?></h1>

	<form method="post" action="">
		<?php wp_nonce_field( 'swish_forms_email_settings' ); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="from_email"><?php esc_html_e( 'From Email', 'swishfolio-core' ); ?></label>
					</th>
					<td>
						<input name="from_email" type="email" id="from_email" value="<?php echo esc_attr( $fromEmail ); ?>" class="regular-text">
						<p class="description"><?php esc_html_e( 'Email address used as the sender for contact form emails.', 'swishfolio-core' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="from_name"><?php esc_html_e( 'From Name', 'swishfolio-core' ); ?></label>
					</th>
					<td>
						<input name="from_name" type="text" id="from_name" value="<?php echo esc_attr( $fromName ); ?>" class="regular-text">
						<p class="description"><?php esc_html_e( 'Name displayed as the sender.', 'swishfolio-core' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'SMTP Configuration', 'swishfolio-core' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Enable custom SMTP to improve email deliverability.', 'swishfolio-core' ); ?></p>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable SMTP', 'swishfolio-core' ); ?></th>
					<td>
						<label for="smtp_enabled">
							<input name="smtp_enabled" type="checkbox" id="smtp_enabled" value="1" <?php checked( $smtpEnabled ); ?>>
							<?php esc_html_e( 'Use custom SMTP server', 'swishfolio-core' ); ?>
						</label>
					</td>
				</tr>
				<tr class="smtp-setting">
					<th scope="row">
						<label for="smtp_host"><?php esc_html_e( 'SMTP Host', 'swishfolio-core' ); ?></label>
					</th>
					<td>
						<input name="smtp_host" type="text" id="smtp_host" value="<?php echo esc_attr( $smtpHost ); ?>" class="regular-text" placeholder="smtp.example.com">
					</td>
				</tr>
				<tr class="smtp-setting">
					<th scope="row">
						<label for="smtp_port"><?php esc_html_e( 'SMTP Port', 'swishfolio-core' ); ?></label>
					</th>
					<td>
						<input name="smtp_port" type="number" id="smtp_port" value="<?php echo esc_attr( $smtpPort ); ?>" class="small-text" min="1" max="65535">
						<p class="description"><?php esc_html_e( 'Common ports: 25, 465 (SSL), 587 (TLS)', 'swishfolio-core' ); ?></p>
					</td>
				</tr>
				<tr class="smtp-setting">
					<th scope="row">
						<label for="smtp_encryption"><?php esc_html_e( 'Encryption', 'swishfolio-core' ); ?></label>
					</th>
					<td>
						<select name="smtp_encryption" id="smtp_encryption">
							<option value="none" <?php selected( $smtpEncryption, 'none' ); ?>><?php esc_html_e( 'None', 'swishfolio-core' ); ?></option>
							<option value="ssl" <?php selected( $smtpEncryption, 'ssl' ); ?>>SSL</option>
							<option value="tls" <?php selected( $smtpEncryption, 'tls' ); ?>>TLS</option>
						</select>
					</td>
				</tr>
				<tr class="smtp-setting">
					<th scope="row">
						<label for="smtp_username"><?php esc_html_e( 'SMTP Username', 'swishfolio-core' ); ?></label>
					</th>
					<td>
						<input name="smtp_username" type="text" id="smtp_username" value="<?php echo esc_attr( $smtpUsername ); ?>" class="regular-text" autocomplete="off">
					</td>
				</tr>
				<tr class="smtp-setting">
					<th scope="row">
						<label for="smtp_password"><?php esc_html_e( 'SMTP Password', 'swishfolio-core' ); ?></label>
					</th>
					<td>
						<input name="smtp_password" type="password" id="smtp_password" value="" class="regular-text" autocomplete="new-password">
						<?php if ( $smtpPasswordSet ) : ?>
							<p class="description"><?php esc_html_e( 'Password is set. Leave blank to keep current password.', 'swishfolio-core' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Test Email', 'swishfolio-core' ); ?></h2>
		<p>
			<input type="email" id="test_email_address" placeholder="<?php esc_attr_e( 'Enter email address', 'swishfolio-core' ); ?>" class="regular-text">
			<button type="button" id="send_test_email" class="button"><?php esc_html_e( 'Send Test Email', 'swishfolio-core' ); ?></button>
			<span id="test_email_result"></span>
		</p>

		<p class="submit">
			<input type="submit" name="swish_forms_email_submit" class="button-primary" value="<?php esc_attr_e( 'Save Settings', 'swishfolio-core' ); ?>">
		</p>
	</form>
</div>

<script>
jQuery(document).ready(function($) {
	function toggleSmtpSettings() {
		if ($('#smtp_enabled').is(':checked')) {
			$('.smtp-setting').show();
		} else {
			$('.smtp-setting').hide();
		}
	}

	toggleSmtpSettings();
	$('#smtp_enabled').on('change', toggleSmtpSettings);
});
</script>
