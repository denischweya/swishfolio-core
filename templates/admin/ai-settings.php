<?php
/**
 * AI settings admin screen template.
 *
 * @package SwishfolioCore
 *
 * @var array  $settings  Public settings (never the API key).
 * @var array  $usage     Usage counters and recent window.
 * @var array  $providers Available providers, keyed by slug.
 * @var string $notice    Notice slug from the redirect.
 */

if (! defined('ABSPATH')) {
    exit;
}

use SwishfolioCore\Admin\AISettingsPage;

$counters = $usage['counters'];
?>
<div class="wrap sfcore-ai-settings">
	<h1><?php esc_html_e('AI Settings', 'swishfolio-core'); ?></h1>

	<?php if ($notice === 'saved') : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e('AI settings saved.', 'swishfolio-core'); ?></p>
		</div>
	<?php elseif ($notice === 'consent-required') : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php esc_html_e('AI was not enabled: you must acknowledge that content will be sent to your AI provider.', 'swishfolio-core'); ?></p>
		</div>
	<?php endif; ?>

	<div class="notice notice-info">
		<h2 style="margin-top:0.6em;"><?php esc_html_e('What leaves your site', 'swishfolio-core'); ?></h2>
		<p>
			<?php esc_html_e('AI editing and generation send data to a third-party service that you choose and pay for. Before you enable it, understand exactly what is sent:', 'swishfolio-core'); ?>
		</p>
		<ul style="list-style:disc;margin-left:2em;">
			<li><?php esc_html_e('The attributes and text content of the blocks you selected.', 'swishfolio-core'); ?></li>
			<li><?php esc_html_e('The names of neighbouring blocks — not their content.', 'swishfolio-core'); ?></li>
			<li><?php esc_html_e('Your theme\'s design token names: colour, spacing and font size slugs from theme.json.', 'swishfolio-core'); ?></li>
			<li><?php esc_html_e('The instruction you type.', 'swishfolio-core'); ?></li>
		</ul>
		<p>
			<strong><?php esc_html_e('Nothing is sent unless you ask for it.', 'swishfolio-core'); ?></strong>
			<?php esc_html_e('No request is made in the background, on a schedule, or on page load. Your API key is stored on this site encrypted and is never transmitted to the plugin author. This site records request counts, token usage and timings — never your prompts or your content.', 'swishfolio-core'); ?>
		</p>
		<p>
			<?php esc_html_e('Your AI provider\'s own terms and privacy policy govern what they do with what is sent. Review them before enabling this.', 'swishfolio-core'); ?>
		</p>
	</div>

	<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
		<input type="hidden" name="action" value="<?php echo esc_attr(AISettingsPage::ACTION); ?>" />
		<?php wp_nonce_field(AISettingsPage::NONCE); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><label for="sfcore-ai-provider"><?php esc_html_e('Provider', 'swishfolio-core'); ?></label></th>
					<td>
						<select name="provider" id="sfcore-ai-provider">
							<?php foreach ($providers as $slug => $provider) : ?>
								<option value="<?php echo esc_attr($slug); ?>" <?php selected($settings['provider'], $slug); ?>>
									<?php echo esc_html($provider->getLabel()); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="sfcore-ai-model"><?php esc_html_e('Model', 'swishfolio-core'); ?></label></th>
					<td>
						<input type="text" class="regular-text" name="model" id="sfcore-ai-model"
							value="<?php echo esc_attr($settings['model']); ?>" />
						<p class="description"><?php esc_html_e('For example gpt-4o. An OpenAI-compatible endpoint decides its own model names.', 'swishfolio-core'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="sfcore-ai-base-url"><?php esc_html_e('Base URL', 'swishfolio-core'); ?></label></th>
					<td>
						<input type="url" class="regular-text" name="base_url" id="sfcore-ai-base-url"
							value="<?php echo esc_attr($settings['base_url']); ?>" placeholder="https://api.openai.com/v1" />
						<p class="description"><?php esc_html_e('Only used by the OpenAI-compatible provider. Leave blank otherwise.', 'swishfolio-core'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="sfcore-ai-key"><?php esc_html_e('API key', 'swishfolio-core'); ?></label></th>
					<td>
						<input type="password" class="regular-text" name="api_key" id="sfcore-ai-key"
							autocomplete="off"
							placeholder="<?php echo $settings['hasKey']
								? esc_attr__('A key is stored. Leave blank to keep it.', 'swishfolio-core')
								: esc_attr__('No key stored yet.', 'swishfolio-core'); ?>" />
						<p class="description">
							<?php esc_html_e('Stored encrypted on this site. It is never returned to the browser once saved, and never sent anywhere except your chosen provider.', 'swishfolio-core'); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Enable AI', 'swishfolio-core'); ?></th>
					<td>
						<fieldset>
							<label for="sfcore-ai-enabled">
								<input type="checkbox" name="enabled" id="sfcore-ai-enabled" value="1"
									<?php checked($settings['enabled']); ?> />
								<?php esc_html_e('Allow AI editing and generation on this site', 'swishfolio-core'); ?>
							</label>
							<br />
							<label for="sfcore-ai-ack">
								<input type="checkbox" name="acknowledged" id="sfcore-ai-ack" value="1"
									<?php checked($settings['enabled']); ?> />
								<?php esc_html_e('I understand that the data described above will be sent to my chosen AI provider.', 'swishfolio-core'); ?>
							</label>
							<p class="description">
								<?php esc_html_e('Both boxes are required to turn AI on. Unticking "Allow AI editing" disables the AI endpoints immediately.', 'swishfolio-core'); ?>
							</p>
						</fieldset>
					</td>
				</tr>
			</tbody>
		</table>

		<?php submit_button(__('Save AI settings', 'swishfolio-core')); ?>
	</form>

	<h2><?php esc_html_e('Usage', 'swishfolio-core'); ?></h2>
	<?php if ((int) $counters['requests'] === 0) : ?>
		<p><?php esc_html_e('No AI requests yet.', 'swishfolio-core'); ?></p>
	<?php else : ?>
		<table class="widefat striped" style="max-width:40em;">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e('Requests', 'swishfolio-core'); ?></th>
					<td><?php echo esc_html(number_format_i18n((int) $counters['requests'])); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Input tokens', 'swishfolio-core'); ?></th>
					<td><?php echo esc_html(number_format_i18n((int) $counters['inputTokens'])); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Output tokens', 'swishfolio-core'); ?></th>
					<td><?php echo esc_html(number_format_i18n((int) $counters['outputTokens'])); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Changes proposed', 'swishfolio-core'); ?></th>
					<td><?php echo esc_html(number_format_i18n((int) $counters['actionsProposed'])); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Changes applied', 'swishfolio-core'); ?></th>
					<td><?php echo esc_html(number_format_i18n((int) $counters['actionsAccepted'])); ?></td>
				</tr>
				<?php if (! empty($counters['failures'])) : ?>
					<tr>
						<th scope="row"><?php esc_html_e('Rejected responses', 'swishfolio-core'); ?></th>
						<td>
							<?php
							$parts = [];
							foreach ((array) $counters['failures'] as $stage => $count) {
								$parts[] = esc_html($stage) . ': ' . esc_html(number_format_i18n((int) $count));
							}
							echo wp_kses_post(implode(', ', $parts));
							?>
						</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
		<p class="description">
			<?php esc_html_e('Token counts are recorded so AI costs can be measured. No prompt or content text is stored.', 'swishfolio-core'); ?>
		</p>
	<?php endif; ?>
</div>
