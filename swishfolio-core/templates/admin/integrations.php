<?php
/**
 * Swish Forms Integrations Admin Page
 *
 * @package SwishfolioCore
 */

use SwishfolioCore\Services\EncryptionService;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$encryption = new EncryptionService();
$settings = get_option( 'swish_forms_esp_settings', [] );

// Available providers.
$providers = [
	'mailchimp'       => [
		'name'   => 'Mailchimp',
		'fields' => [
			'api_key' => __( 'API Key', 'swishfolio-core' ),
		],
		'docs'   => 'https://mailchimp.com/help/about-api-keys/',
	],
	'convertkit'      => [
		'name'   => 'ConvertKit',
		'fields' => [
			'api_key'    => __( 'API Key', 'swishfolio-core' ),
			'api_secret' => __( 'API Secret', 'swishfolio-core' ),
		],
		'docs'   => 'https://app.convertkit.com/account_settings/advanced_settings',
	],
	'klaviyo'         => [
		'name'   => 'Klaviyo',
		'fields' => [
			'api_key' => __( 'Private API Key', 'swishfolio-core' ),
		],
		'docs'   => 'https://help.klaviyo.com/hc/en-us/articles/115005062267',
	],
	'activecampaign'  => [
		'name'   => 'ActiveCampaign',
		'fields' => [
			'api_url' => __( 'API URL', 'swishfolio-core' ),
			'api_key' => __( 'API Key', 'swishfolio-core' ),
		],
		'docs'   => 'https://help.activecampaign.com/hc/en-us/articles/207317590',
	],
	'brevo'           => [
		'name'   => 'Brevo (Sendinblue)',
		'fields' => [
			'api_key' => __( 'API Key', 'swishfolio-core' ),
		],
		'docs'   => 'https://developers.brevo.com/docs/getting-started',
	],
];

// Handle form submission.
if ( isset( $_POST['swish_forms_esp_submit'] ) ) {
	if ( check_admin_referer( 'swish_forms_esp_settings' ) ) {
		$activeProvider = sanitize_text_field( $_POST['active_provider'] ?? 'none' );

		$newSettings = [
			'active_provider' => $activeProvider,
		];

		// Save each provider's credentials.
		foreach ( $providers as $slug => $provider ) {
			$providerSettings = [];
			foreach ( array_keys( $provider['fields'] ) as $field ) {
				$fieldName = $slug . '_' . $field;
				$value = $_POST[ $fieldName ] ?? '';

				// Only update if not empty, otherwise keep existing.
				if ( ! empty( $value ) ) {
					$providerSettings[ $field ] = $encryption->encrypt( $value );
				} elseif ( isset( $settings[ $slug ][ $field ] ) ) {
					$providerSettings[ $field ] = $settings[ $slug ][ $field ];
				}
			}

			// Save list ID if set.
			$listIdField = $slug . '_list_id';
			if ( isset( $_POST[ $listIdField ] ) ) {
				$providerSettings['list_id'] = sanitize_text_field( $_POST[ $listIdField ] );
			}

			$newSettings[ $slug ] = $providerSettings;
		}

		update_option( 'swish_forms_esp_settings', $newSettings );
		$settings = $newSettings;

		echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', 'swishfolio-core' ) . '</p></div>';
	}
}

$activeProvider = $settings['active_provider'] ?? 'none';
?>
<div class="wrap swish-forms-admin">
	<h1><?php esc_html_e( 'Email Provider Integrations', 'swishfolio-core' ); ?></h1>

	<p><?php esc_html_e( 'Connect your email marketing platform to sync subscription form submissions.', 'swishfolio-core' ); ?></p>

	<form method="post" action="">
		<?php wp_nonce_field( 'swish_forms_esp_settings' ); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="active_provider"><?php esc_html_e( 'Active Provider', 'swishfolio-core' ); ?></label>
					</th>
					<td>
						<select name="active_provider" id="active_provider">
							<option value="none" <?php selected( $activeProvider, 'none' ); ?>><?php esc_html_e( 'None', 'swishfolio-core' ); ?></option>
							<?php foreach ( $providers as $slug => $provider ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $activeProvider, $slug ); ?>>
									<?php echo esc_html( $provider['name'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Select the email provider to use for subscription forms.', 'swishfolio-core' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>

		<?php foreach ( $providers as $slug => $provider ) : ?>
			<?php
			$providerSettings = $settings[ $slug ] ?? [];
			$isActive = $activeProvider === $slug;
			?>
			<div class="swish-forms-provider-settings" id="provider-<?php echo esc_attr( $slug ); ?>" style="<?php echo $isActive ? '' : 'display: none;'; ?>">
				<h2>
					<?php echo esc_html( $provider['name'] ); ?>
					<a href="<?php echo esc_url( $provider['docs'] ); ?>" target="_blank" rel="noopener" class="page-title-action">
						<?php esc_html_e( 'Documentation', 'swishfolio-core' ); ?>
					</a>
				</h2>

				<table class="form-table" role="presentation">
					<tbody>
						<?php foreach ( $provider['fields'] as $field => $label ) : ?>
							<?php
							$fieldName = $slug . '_' . $field;
							$hasValue = ! empty( $providerSettings[ $field ] );
							$inputType = strpos( $field, 'secret' ) !== false || strpos( $field, 'key' ) !== false ? 'password' : 'text';
							?>
							<tr>
								<th scope="row">
									<label for="<?php echo esc_attr( $fieldName ); ?>"><?php echo esc_html( $label ); ?></label>
								</th>
								<td>
									<input name="<?php echo esc_attr( $fieldName ); ?>" type="<?php echo esc_attr( $inputType ); ?>" id="<?php echo esc_attr( $fieldName ); ?>" value="" class="regular-text" autocomplete="new-password">
									<?php if ( $hasValue ) : ?>
										<p class="description"><?php esc_html_e( 'Value is set. Leave blank to keep current value.', 'swishfolio-core' ); ?></p>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
						<tr>
							<th scope="row">
								<label for="<?php echo esc_attr( $slug ); ?>_list_id"><?php esc_html_e( 'Default List/Audience', 'swishfolio-core' ); ?></label>
							</th>
							<td>
								<input name="<?php echo esc_attr( $slug ); ?>_list_id" type="text" id="<?php echo esc_attr( $slug ); ?>_list_id" value="<?php echo esc_attr( $providerSettings['list_id'] ?? '' ); ?>" class="regular-text">
								<button type="button" class="button swish-forms-fetch-lists" data-provider="<?php echo esc_attr( $slug ); ?>">
									<?php esc_html_e( 'Fetch Lists', 'swishfolio-core' ); ?>
								</button>
								<p class="description"><?php esc_html_e( 'Enter the list/audience ID or click Fetch Lists after saving credentials.', 'swishfolio-core' ); ?></p>
								<div class="swish-forms-lists-result" id="<?php echo esc_attr( $slug ); ?>-lists-result"></div>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Connection Test', 'swishfolio-core' ); ?></th>
							<td>
								<button type="button" class="button swish-forms-test-connection" data-provider="<?php echo esc_attr( $slug ); ?>">
									<?php esc_html_e( 'Test Connection', 'swishfolio-core' ); ?>
								</button>
								<span class="swish-forms-test-result" id="<?php echo esc_attr( $slug ); ?>-test-result"></span>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		<?php endforeach; ?>

		<p class="submit">
			<input type="submit" name="swish_forms_esp_submit" class="button-primary" value="<?php esc_attr_e( 'Save Settings', 'swishfolio-core' ); ?>">
		</p>
	</form>
</div>

<script>
jQuery(document).ready(function($) {
	$('#active_provider').on('change', function() {
		var provider = $(this).val();
		$('.swish-forms-provider-settings').hide();
		if (provider !== 'none') {
			$('#provider-' + provider).show();
		}
	});
});
</script>
