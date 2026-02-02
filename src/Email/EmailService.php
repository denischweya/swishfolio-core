<?php
/**
 * Email Service
 *
 * Handles sending emails with SMTP support.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Email;

use SwishfolioCore\Services\EncryptionService;

class EmailService {
	/**
	 * Encryption service.
	 *
	 * @var EncryptionService
	 */
	private EncryptionService $encryption;

	/**
	 * Email settings.
	 *
	 * @var array
	 */
	private array $settings;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->encryption = new EncryptionService();
		$this->settings = get_option( 'swish_forms_email_settings', [] );

		// Hook into contact form submission.
		add_action( 'swish_forms_contact_submitted', [ $this, 'sendContactEmail' ], 10, 6 );

		// Configure SMTP if enabled.
		if ( ! empty( $this->settings['smtp_enabled'] ) ) {
			add_action( 'phpmailer_init', [ $this, 'configureSMTP' ] );
		}

		// Handle test email AJAX.
		add_action( 'wp_ajax_swish_forms_send_test_email', [ $this, 'handleTestEmail' ] );
	}

	/**
	 * Send contact form email.
	 *
	 * @param int    $entryId          Entry ID.
	 * @param string $recipientEmail   Recipient email.
	 * @param string $subject          Email subject.
	 * @param array  $fields           Form field values.
	 * @param string $senderEmail      Submitter email.
	 * @param array  $fieldDefinitions Field definitions with labels.
	 * @return bool
	 */
	public function sendContactEmail( int $entryId, string $recipientEmail, string $subject, array $fields, string $senderEmail, array $fieldDefinitions = [] ): bool {
		if ( empty( $recipientEmail ) || ! is_email( $recipientEmail ) ) {
			return false;
		}

		$fromEmail = $this->settings['from_email'] ?? get_option( 'admin_email' );
		$fromName = $this->settings['from_name'] ?? get_option( 'blogname' );

		// Build email content.
		$message = $this->buildEmailContent( $fields, $senderEmail, $entryId, $fieldDefinitions );

		// Set headers.
		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $fromName . ' <' . $fromEmail . '>',
		];

		// Add reply-to if we have sender email.
		if ( ! empty( $senderEmail ) && is_email( $senderEmail ) ) {
			$headers[] = 'Reply-To: ' . $senderEmail;
		}

		return wp_mail( $recipientEmail, $subject, $message, $headers );
	}

	/**
	 * Build email content from form fields.
	 *
	 * @param array  $fields           Form field values.
	 * @param string $senderEmail      Submitter email.
	 * @param int    $entryId          Entry ID.
	 * @param array  $fieldDefinitions Field definitions with labels.
	 * @return string
	 */
	private function buildEmailContent( array $fields, string $senderEmail, int $entryId, array $fieldDefinitions = [] ): string {
		$siteName = get_bloginfo( 'name' );
		$siteUrl = home_url();

		// Build a map of field IDs to labels.
		$fieldLabels = [];
		foreach ( $fieldDefinitions as $fieldDef ) {
			if ( isset( $fieldDef['id'] ) && isset( $fieldDef['label'] ) ) {
				$fieldLabels[ $fieldDef['id'] ] = $fieldDef['label'];
			}
		}

		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<style>
				body {
					font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
					line-height: 1.6;
					color: #000;
					background: #f5f5f5;
					margin: 0;
					padding: 20px;
				}
				.container {
					max-width: 600px;
					margin: 0 auto;
					background: #FFFDF5;
					border: 4px solid #000;
					box-shadow: 8px 8px 0 0 #000;
				}
				.header {
					background: #FFD93D;
					padding: 20px 30px;
					border-bottom: 4px solid #000;
				}
				.header h1 {
					margin: 0;
					font-size: 24px;
					font-weight: 900;
					text-transform: uppercase;
					letter-spacing: 0.05em;
				}
				.content {
					padding: 30px;
				}
				.field {
					margin-bottom: 20px;
					border-bottom: 2px solid #eee;
					padding-bottom: 15px;
				}
				.field:last-child {
					border-bottom: none;
					margin-bottom: 0;
				}
				.field-label {
					font-weight: 700;
					text-transform: uppercase;
					letter-spacing: 0.02em;
					font-size: 12px;
					color: #666;
					margin-bottom: 5px;
				}
				.field-value {
					font-size: 16px;
					color: #000;
					white-space: pre-wrap;
				}
				.footer {
					background: #f5f5f5;
					padding: 15px 30px;
					border-top: 4px solid #000;
					font-size: 12px;
					color: #666;
				}
			</style>
		</head>
		<body>
			<div class="container">
				<div class="header">
					<h1><?php esc_html_e( 'New Contact Form Submission', 'swishfolio-core' ); ?></h1>
				</div>
				<div class="content">
					<?php foreach ( $fields as $fieldId => $value ) : ?>
						<?php if ( ! empty( $value ) || $value === '0' ) : ?>
							<?php
							// Get the human-readable label, fallback to formatted field ID.
							$label = isset( $fieldLabels[ $fieldId ] ) ? $fieldLabels[ $fieldId ] : ucfirst( str_replace( [ '_', '-' ], ' ', $fieldId ) );
							?>
							<div class="field">
								<div class="field-label"><?php echo esc_html( $label ); ?></div>
								<div class="field-value"><?php echo esc_html( is_array( $value ) ? implode( ', ', $value ) : $value ); ?></div>
							</div>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
				<div class="footer">
					<?php
					printf(
						/* translators: 1: site name, 2: entry ID, 3: date/time */
						esc_html__( 'Submitted via %1$s | Entry #%2$d | %3$s', 'swishfolio-core' ),
						esc_html( $siteName ),
						(int) $entryId,
						esc_html( current_time( 'F j, Y g:i a' ) )
					);
					?>
				</div>
			</div>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Configure PHPMailer for SMTP.
	 *
	 * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance.
	 * @return void
	 */
	public function configureSMTP( $phpmailer ): void {
		$host = $this->settings['smtp_host'] ?? '';
		$port = $this->settings['smtp_port'] ?? 587;
		$encryption = $this->settings['smtp_encryption'] ?? 'tls';
		$username = $this->settings['smtp_username'] ?? '';
		$password = $this->settings['smtp_password'] ?? '';

		if ( empty( $host ) ) {
			return;
		}

		// Decrypt password if encrypted.
		if ( ! empty( $password ) && $this->encryption->isEncrypted( $password ) ) {
			$password = $this->encryption->decrypt( $password );
		}

		$phpmailer->isSMTP();
		$phpmailer->Host = $host;
		$phpmailer->Port = (int) $port;

		if ( $encryption !== 'none' ) {
			$phpmailer->SMTPSecure = $encryption;
		}

		if ( ! empty( $username ) && ! empty( $password ) ) {
			$phpmailer->SMTPAuth = true;
			$phpmailer->Username = $username;
			$phpmailer->Password = $password;
		}
	}

	/**
	 * Handle test email AJAX request.
	 *
	 * @return void
	 */
	public function handleTestEmail(): void {
		check_ajax_referer( 'swish_forms_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'swishfolio-core' ) ] );
		}

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

		if ( ! is_email( $email ) ) {
			wp_send_json_error( [ 'message' => __( 'Please enter a valid email address.', 'swishfolio-core' ) ] );
		}

		$fromEmail = $this->settings['from_email'] ?? get_option( 'admin_email' );
		$fromName = $this->settings['from_name'] ?? get_option( 'blogname' );

		$subject = sprintf(
			/* translators: %s: site name */
			__( 'Test Email from %s', 'swishfolio-core' ),
			get_bloginfo( 'name' )
		);

		$message = sprintf(
			/* translators: 1: site name, 2: date/time */
			__( 'This is a test email from %1$s sent on %2$s. If you received this, your email settings are working correctly!', 'swishfolio-core' ),
			get_bloginfo( 'name' ),
			current_time( 'F j, Y g:i a' )
		);

		$headers = [
			'Content-Type: text/plain; charset=UTF-8',
			'From: ' . $fromName . ' <' . $fromEmail . '>',
		];

		$sent = wp_mail( $email, $subject, $message, $headers );

		if ( $sent ) {
			wp_send_json_success( [ 'message' => __( 'Test email sent successfully!', 'swishfolio-core' ) ] );
		} else {
			wp_send_json_error( [ 'message' => __( 'Failed to send test email. Please check your settings.', 'swishfolio-core' ) ] );
		}
	}

	/**
	 * Send email.
	 *
	 * @param string       $to      Recipient email.
	 * @param string       $subject Email subject.
	 * @param string       $message Email message.
	 * @param array|string $headers Optional headers.
	 * @return bool
	 */
	public function send( string $to, string $subject, string $message, $headers = [] ): bool {
		return wp_mail( $to, $subject, $message, $headers );
	}
}
