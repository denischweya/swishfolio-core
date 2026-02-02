<?php
/**
 * Swish Forms Dashboard Admin Page
 *
 * @package SwishfolioCore
 */

use SwishfolioCore\Forms\FormEntryPostType;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$totalEntries = FormEntryPostType::getEntryCount();
$recentEntries = FormEntryPostType::getEntries( '', [ 'posts_per_page' => 5 ] );
?>
<div class="wrap swish-forms-admin">
	<h1><?php esc_html_e( 'Swish Forms', 'swishfolio-core' ); ?></h1>

	<div class="swish-forms-dashboard">
		<!-- Stats Cards -->
		<div class="swish-forms-stats">
			<div class="swish-forms-stat-card">
				<span class="stat-number"><?php echo esc_html( $totalEntries ); ?></span>
				<span class="stat-label"><?php esc_html_e( 'Total Submissions', 'swishfolio-core' ); ?></span>
			</div>
		</div>

		<!-- Quick Links -->
		<div class="swish-forms-quick-links">
			<h2><?php esc_html_e( 'Quick Links', 'swishfolio-core' ); ?></h2>
			<div class="quick-links-grid">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=swish-forms-entries' ) ); ?>" class="quick-link-card">
					<span class="dashicons dashicons-list-view"></span>
					<span class="quick-link-title"><?php esc_html_e( 'View Entries', 'swishfolio-core' ); ?></span>
					<span class="quick-link-desc"><?php esc_html_e( 'See all form submissions', 'swishfolio-core' ); ?></span>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=swish-forms-email' ) ); ?>" class="quick-link-card">
					<span class="dashicons dashicons-email-alt"></span>
					<span class="quick-link-title"><?php esc_html_e( 'Email Settings', 'swishfolio-core' ); ?></span>
					<span class="quick-link-desc"><?php esc_html_e( 'Configure SMTP settings', 'swishfolio-core' ); ?></span>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=swish-forms-integrations' ) ); ?>" class="quick-link-card">
					<span class="dashicons dashicons-admin-plugins"></span>
					<span class="quick-link-title"><?php esc_html_e( 'Integrations', 'swishfolio-core' ); ?></span>
					<span class="quick-link-desc"><?php esc_html_e( 'Connect email providers', 'swishfolio-core' ); ?></span>
				</a>
			</div>
		</div>

		<!-- Recent Entries -->
		<div class="swish-forms-recent">
			<h2><?php esc_html_e( 'Recent Submissions', 'swishfolio-core' ); ?></h2>
			<?php if ( ! empty( $recentEntries ) ) : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Email', 'swishfolio-core' ); ?></th>
							<th><?php esc_html_e( 'Form Type', 'swishfolio-core' ); ?></th>
							<th><?php esc_html_e( 'Date', 'swishfolio-core' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $recentEntries as $entry ) : ?>
							<?php
							$email = get_post_meta( $entry->ID, FormEntryPostType::META_SUBMISSION_EMAIL, true );
							$formType = get_post_meta( $entry->ID, FormEntryPostType::META_FORM_TYPE, true );
							?>
							<tr>
								<td><?php echo esc_html( $email ?: '—' ); ?></td>
								<td><?php echo esc_html( ucfirst( $formType ) ); ?></td>
								<td><?php echo esc_html( get_the_date( '', $entry ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=swish-forms-entries' ) ); ?>">
						<?php esc_html_e( 'View all entries →', 'swishfolio-core' ); ?>
					</a>
				</p>
			<?php else : ?>
				<p><?php esc_html_e( 'No submissions yet. Add a Swish Form block to start collecting entries.', 'swishfolio-core' ); ?></p>
			<?php endif; ?>
		</div>

		<!-- How to Use -->
		<div class="swish-forms-help">
			<h2><?php esc_html_e( 'Getting Started', 'swishfolio-core' ); ?></h2>
			<ol>
				<li><?php esc_html_e( 'Add a "Swish Form" block to any page or post', 'swishfolio-core' ); ?></li>
				<li><?php esc_html_e( 'Choose form type: Contact or Subscription', 'swishfolio-core' ); ?></li>
				<li><?php esc_html_e( 'Add your form fields', 'swishfolio-core' ); ?></li>
				<li><?php esc_html_e( 'Configure email settings for contact forms', 'swishfolio-core' ); ?></li>
				<li><?php esc_html_e( 'Connect an email provider for subscription forms', 'swishfolio-core' ); ?></li>
			</ol>
		</div>
	</div>
</div>
