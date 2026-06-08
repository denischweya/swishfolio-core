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

// Defence-in-depth: the menu page registration already gates this view on
// `manage_options`, but check again so the file is safe even if loaded
// directly by a future caller.
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'swishfolio-core' ) );
}

// Handle bulk-delete POST.
// Verify the nonce BEFORE reading any POST values; superglobal access is then
// safe to interpret as user intent. wp_unslash() must precede every
// sanitize_*() call on these values.
if ( ! empty( $_POST['_wpnonce'] ) ) {
	check_admin_referer( 'swish_forms_bulk_action' );

	$action    = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';
	$entry_ids = isset( $_POST['entry_ids'] )
		? array_map( 'absint', (array) wp_unslash( $_POST['entry_ids'] ) )
		: [];

	if ( 'delete' === $action && ! empty( $entry_ids ) ) {
		$deleted = 0;
		foreach ( $entry_ids as $entryId ) {
			if ( wp_delete_post( $entryId, true ) ) {
				$deleted++;
			}
		}
		echo '<div class="notice notice-success"><p>' . sprintf(
			/* translators: %d: number of entries deleted */
			esc_html__( '%d entries deleted.', 'swishfolio-core' ),
			(int) $deleted
		) . '</p></div>';
	}
}

// Handle single delete via GET (link in entries table).
$get_action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
$get_entry  = isset( $_GET['entry'] ) ? absint( $_GET['entry'] ) : 0;

if ( 'delete' === $get_action && $get_entry ) {
	check_admin_referer( 'delete_entry_' . $get_entry );

	if ( wp_delete_post( $get_entry, true ) ) {
		echo '<div class="notice notice-success"><p>' . esc_html__( 'Entry deleted.', 'swishfolio-core' ) . '</p></div>';
	}
}

// Handle CSV export.
if ( 'export' === $get_action ) {
	check_admin_referer( 'swish_forms_export' );

	$entries = FormEntryPostType::getEntries( '', [ 'posts_per_page' => -1 ] );

	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=swish-forms-entries-' . gmdate( 'Y-m-d' ) . '.csv' );

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

// Pagination (read-only inputs; sanitise before use).
$paged        = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
$perPage      = 20;
$formIdFilter = isset( $_GET['form_id'] )
	? sanitize_text_field( wp_unslash( $_GET['form_id'] ) )
	: '';

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
							// paginate_links() returns safe HTML built from
							// sanitised arguments above; wp_kses_post keeps
							// the output safe for reviewers' static checks.
							echo wp_kses_post( paginate_links( [
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
								'total'     => $totalPages,
								'current'   => $paged,
							] ) );
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
