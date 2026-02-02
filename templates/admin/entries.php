<?php
/**
 * Swish Forms Entries Admin Page
 *
 * @package SwishfolioCore
 */

use SwishfolioCore\Forms\FormEntryPostType;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Handle bulk actions.
if ( isset( $_POST['action'] ) && $_POST['action'] === 'delete' && ! empty( $_POST['entry_ids'] ) ) {
	if ( check_admin_referer( 'swish_forms_bulk_action' ) ) {
		$deleted = 0;
		foreach ( (array) $_POST['entry_ids'] as $entryId ) {
			if ( wp_delete_post( absint( $entryId ), true ) ) {
				$deleted++;
			}
		}
		echo '<div class="notice notice-success"><p>' . sprintf(
			/* translators: %d: number of entries deleted */
			esc_html__( '%d entries deleted.', 'swishfolio-core' ),
			$deleted
		) . '</p></div>';
	}
}

// Handle single delete.
if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['entry'] ) ) {
	if ( check_admin_referer( 'delete_entry_' . $_GET['entry'] ) ) {
		if ( wp_delete_post( absint( $_GET['entry'] ), true ) ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Entry deleted.', 'swishfolio-core' ) . '</p></div>';
		}
	}
}

// Handle CSV export.
if ( isset( $_GET['action'] ) && $_GET['action'] === 'export' ) {
	if ( check_admin_referer( 'swish_forms_export' ) ) {
		$entries = FormEntryPostType::getEntries( '', [ 'posts_per_page' => -1 ] );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=swish-forms-entries-' . date( 'Y-m-d' ) . '.csv' );

		$output = fopen( 'php://output', 'w' );

		// Header row.
		fputcsv( $output, [ 'ID', 'Email', 'Form Type', 'Date', 'Data' ] );

		foreach ( $entries as $entry ) {
			$email = get_post_meta( $entry->ID, FormEntryPostType::META_SUBMISSION_EMAIL, true );
			$formType = get_post_meta( $entry->ID, FormEntryPostType::META_FORM_TYPE, true );
			$data = get_post_meta( $entry->ID, FormEntryPostType::META_FORM_DATA, true );

			fputcsv( $output, [
				$entry->ID,
				$email,
				$formType,
				$entry->post_date,
				is_array( $data ) ? wp_json_encode( $data ) : $data,
			] );
		}

		fclose( $output );
		exit;
	}
}

// Pagination.
$paged = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
$perPage = 20;
$formIdFilter = isset( $_GET['form_id'] ) ? sanitize_text_field( $_GET['form_id'] ) : '';

$entries = FormEntryPostType::getEntries( $formIdFilter, [
	'posts_per_page' => $perPage,
	'paged'          => $paged,
] );

$totalEntries = FormEntryPostType::getEntryCount( $formIdFilter );
$totalPages = ceil( $totalEntries / $perPage );
?>
<div class="wrap swish-forms-admin">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Form Entries', 'swishfolio-core' ); ?></h1>

	<?php if ( $totalEntries > 0 ) : ?>
		<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'action', 'export' ), 'swish_forms_export' ) ); ?>" class="page-title-action">
			<?php esc_html_e( 'Export CSV', 'swishfolio-core' ); ?>
		</a>
	<?php endif; ?>

	<hr class="wp-header-end">

	<?php if ( empty( $entries ) ) : ?>
		<div class="swish-forms-empty-state">
			<p><?php esc_html_e( 'No form entries found.', 'swishfolio-core' ); ?></p>
		</div>
	<?php else : ?>
		<form method="post" id="swish-forms-entries-form">
			<?php wp_nonce_field( 'swish_forms_bulk_action' ); ?>
			<input type="hidden" name="action" value="delete">

			<div class="tablenav top">
				<div class="alignleft actions bulkactions">
					<select name="action2">
						<option value="-1"><?php esc_html_e( 'Bulk Actions', 'swishfolio-core' ); ?></option>
						<option value="delete"><?php esc_html_e( 'Delete', 'swishfolio-core' ); ?></option>
					</select>
					<input type="submit" class="button action" value="<?php esc_attr_e( 'Apply', 'swishfolio-core' ); ?>">
				</div>

				<?php if ( $totalPages > 1 ) : ?>
					<div class="tablenav-pages">
						<span class="displaying-num">
							<?php
							printf(
								/* translators: %s: number of items */
								esc_html( _n( '%s item', '%s items', $totalEntries, 'swishfolio-core' ) ),
								number_format_i18n( $totalEntries )
							);
							?>
						</span>
						<span class="pagination-links">
							<?php
							echo paginate_links( [
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
								'total'     => $totalPages,
								'current'   => $paged,
							] );
							?>
						</span>
					</div>
				<?php endif; ?>
			</div>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<td class="manage-column column-cb check-column">
							<input type="checkbox" id="cb-select-all">
						</td>
						<th class="manage-column"><?php esc_html_e( 'Email', 'swishfolio-core' ); ?></th>
						<th class="manage-column"><?php esc_html_e( 'Form Type', 'swishfolio-core' ); ?></th>
						<th class="manage-column"><?php esc_html_e( 'ESP Synced', 'swishfolio-core' ); ?></th>
						<th class="manage-column"><?php esc_html_e( 'Date', 'swishfolio-core' ); ?></th>
						<th class="manage-column"><?php esc_html_e( 'Actions', 'swishfolio-core' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $entries as $entry ) : ?>
						<?php
						$email = get_post_meta( $entry->ID, FormEntryPostType::META_SUBMISSION_EMAIL, true );
						$formType = get_post_meta( $entry->ID, FormEntryPostType::META_FORM_TYPE, true );
						$espSynced = get_post_meta( $entry->ID, FormEntryPostType::META_ESP_SYNCED, true );
						$formData = get_post_meta( $entry->ID, FormEntryPostType::META_FORM_DATA, true );
						?>
						<tr>
							<th class="check-column">
								<input type="checkbox" name="entry_ids[]" value="<?php echo esc_attr( $entry->ID ); ?>">
							</th>
							<td>
								<strong><?php echo esc_html( $email ?: '—' ); ?></strong>
								<button type="button" class="button-link swish-forms-view-details" data-entry="<?php echo esc_attr( $entry->ID ); ?>" data-data="<?php echo esc_attr( wp_json_encode( $formData ) ); ?>">
									<?php esc_html_e( 'View details', 'swishfolio-core' ); ?>
								</button>
							</td>
							<td><?php echo esc_html( ucfirst( $formType ) ); ?></td>
							<td>
								<?php if ( $espSynced ) : ?>
									<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
								<?php else : ?>
									<span class="dashicons dashicons-minus" style="color: #999;"></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( get_the_date( 'Y-m-d H:i', $entry ) ); ?></td>
							<td>
								<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'action' => 'delete', 'entry' => $entry->ID ] ), 'delete_entry_' . $entry->ID ) ); ?>" class="swish-forms-delete-entry" onclick="return confirm('<?php esc_attr_e( 'Delete this entry?', 'swishfolio-core' ); ?>');">
									<?php esc_html_e( 'Delete', 'swishfolio-core' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</form>

		<!-- Entry Details Modal -->
		<div id="swish-forms-entry-modal" class="swish-forms-modal" style="display: none;">
			<div class="swish-forms-modal-content">
				<span class="swish-forms-modal-close">&times;</span>
				<h2><?php esc_html_e( 'Entry Details', 'swishfolio-core' ); ?></h2>
				<div id="swish-forms-entry-details"></div>
			</div>
		</div>
	<?php endif; ?>
</div>
