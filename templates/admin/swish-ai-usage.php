<?php
/**
 * Swish AI → Usage.
 *
 * @package SwishfolioCore
 *
 * @var array  $usage  Counters and recent window.
 * @var string $notice Notice slug.
 */

if (! defined('ABSPATH')) {
    exit;
}

$counters = $usage['counters'];
?>
<div class="wrap sfcore-ai">
	<h1><?php esc_html_e('AI Usage', 'swishfolio-core'); ?></h1>

	<p class="sfcore-ai__lede">
		<?php esc_html_e('Token counts are recorded so you can see what your provider is charging you for. No prompt text and no content is ever stored.', 'swishfolio-core'); ?>
	</p>

	<?php if ((int) $counters['requests'] === 0) : ?>
		<p><?php esc_html_e('No AI requests yet.', 'swishfolio-core'); ?></p>
	<?php else : ?>
		<table class="widefat striped" style="max-width:44em;">
			<tbody>
				<?php
				foreach ([
					__('Requests', 'swishfolio-core')         => $counters['requests'],
					__('Input tokens', 'swishfolio-core')     => $counters['inputTokens'],
					__('Output tokens', 'swishfolio-core')    => $counters['outputTokens'],
					__('Changes proposed', 'swishfolio-core') => $counters['actionsProposed'],
					__('Changes applied', 'swishfolio-core')  => $counters['actionsAccepted'],
				] as $label => $value) :
					?>
					<tr>
						<th scope="row"><?php echo esc_html($label); ?></th>
						<td><?php echo esc_html(number_format_i18n((int) $value)); ?></td>
					</tr>
				<?php endforeach; ?>
				<?php if (! empty($counters['failures'])) : ?>
					<tr>
						<th scope="row"><?php esc_html_e('Rejected responses', 'swishfolio-core'); ?></th>
						<td>
							<?php
							$parts = [];
							foreach ((array) $counters['failures'] as $stage => $count) {
								$parts[] = $stage . ': ' . number_format_i18n((int) $count);
							}
							echo esc_html(implode(', ', $parts));
							?>
						</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
