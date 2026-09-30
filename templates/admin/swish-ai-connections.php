<?php
/**
 * Swish AI → Connections.
 *
 * @package SwishfolioCore
 *
 * @var array  $settings  Public settings (never a key).
 * @var array  $providers Available provider instances, keyed by slug.
 * @var array  $models    Provider slug => model id => label.
 * @var string $notice    Notice slug.
 */

if (! defined('ABSPATH')) {
    exit;
}

use SwishfolioCore\Admin\SwishAiMenu;

$notice_text = SwishAiMenu::noticeText($notice);
?>
<div class="wrap sfcore-ai">
	<h1><?php esc_html_e('Swish AI', 'swishfolio-core'); ?></h1>

	<?php if ($notice_text !== null) : ?>
		<div class="notice notice-<?php echo esc_attr($notice_text['type']); ?> is-dismissible">
			<p><?php echo esc_html($notice_text['message']); ?></p>
		</div>
	<?php endif; ?>

	<p class="sfcore-ai__lede">
		<?php esc_html_e('Connect your own AI account. Swishfolio sells no AI credits — you pay OpenAI or Anthropic directly for what you use, and your key never leaves this site except to reach the provider you chose.', 'swishfolio-core'); ?>
	</p>

	<div class="sfcore-ai__cards">
		<?php foreach ($providers as $slug => $provider) : ?>
			<?php
			$state       = $settings['providers'][$slug];
			$is_active   = $settings['active'] === $slug;
			$needs_url   = in_array('base_url', $provider->getRequiredFields(), true);
			$has_models  = ! empty($models[$slug]);
			?>
			<div class="sfcore-ai-card<?php echo $is_active ? ' is-active' : ''; ?>">
				<div class="sfcore-ai-card__head">
					<h2><?php echo esc_html($provider->getLabel()); ?></h2>
					<?php if ($state['connected']) : ?>
						<span class="sfcore-ai-badge sfcore-ai-badge--ok">
							<?php esc_html_e('Connected', 'swishfolio-core'); ?>
						</span>
					<?php else : ?>
						<span class="sfcore-ai-badge">
							<?php esc_html_e('Not connected', 'swishfolio-core'); ?>
						</span>
					<?php endif; ?>
					<?php if ($is_active) : ?>
						<span class="sfcore-ai-badge sfcore-ai-badge--active">
							<?php esc_html_e('In use', 'swishfolio-core'); ?>
						</span>
					<?php endif; ?>
				</div>

				<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr(SwishAiMenu::ACTION_CONNECT); ?>" />
					<input type="hidden" name="provider" value="<?php echo esc_attr($slug); ?>" />
					<?php wp_nonce_field(SwishAiMenu::NONCE); ?>

					<p>
						<label for="key-<?php echo esc_attr($slug); ?>">
							<strong><?php esc_html_e('API key', 'swishfolio-core'); ?></strong>
						</label><br />
						<input type="password" class="regular-text" autocomplete="off"
							id="key-<?php echo esc_attr($slug); ?>" name="api_key"
							placeholder="<?php echo $state['connected']
								? esc_attr__('Stored. Leave blank to keep it.', 'swishfolio-core')
								: esc_attr__('Paste your key', 'swishfolio-core'); ?>" />
					</p>

					<?php if ($needs_url) : ?>
						<p>
							<label for="url-<?php echo esc_attr($slug); ?>">
								<strong><?php esc_html_e('Base URL', 'swishfolio-core'); ?></strong>
							</label><br />
							<input type="url" class="regular-text" id="url-<?php echo esc_attr($slug); ?>"
								name="base_url" value="<?php echo esc_attr($state['baseUrl']); ?>"
								placeholder="https://openrouter.ai/api/v1" />
						</p>
					<?php endif; ?>

					<p>
						<label for="model-<?php echo esc_attr($slug); ?>">
							<strong><?php esc_html_e('Model', 'swishfolio-core'); ?></strong>
						</label><br />
						<?php if ($has_models) : ?>
							<select id="model-<?php echo esc_attr($slug); ?>" name="model"
								class="sfcore-ai-model" data-provider="<?php echo esc_attr($slug); ?>">
								<?php foreach ($models[$slug] as $model_id => $model_label) : ?>
									<option value="<?php echo esc_attr($model_id); ?>"
										<?php selected($state['model'], $model_id); ?>>
										<?php echo esc_html($model_label); ?>
									</option>
								<?php endforeach; ?>
							</select>
						<?php else : ?>
							<input type="text" class="regular-text sfcore-ai-model"
								id="model-<?php echo esc_attr($slug); ?>" name="model"
								data-provider="<?php echo esc_attr($slug); ?>"
								value="<?php echo esc_attr($state['model']); ?>"
								placeholder="<?php esc_attr_e('Model name for this endpoint', 'swishfolio-core'); ?>" />
						<?php endif; ?>
					</p>

					<p class="sfcore-ai-card__actions">
						<button type="submit" class="button button-primary">
							<?php echo $state['connected']
								? esc_html__('Save', 'swishfolio-core')
								: esc_html__('Connect', 'swishfolio-core'); ?>
						</button>
					</p>
				</form>

				<?php if ($state['connected']) : ?>
					<p class="sfcore-ai-card__actions">
						<?php foreach ([
							SwishAiMenu::ACTION_TEST     => __('Test connection', 'swishfolio-core'),
							SwishAiMenu::ACTION_ACTIVATE => __('Use this provider', 'swishfolio-core'),
						] as $action => $label) : ?>
							<?php if ($action === SwishAiMenu::ACTION_ACTIVATE && $is_active) { continue; } ?>
							<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sfcore-ai-inline">
								<input type="hidden" name="action" value="<?php echo esc_attr($action); ?>" />
								<input type="hidden" name="provider" value="<?php echo esc_attr($slug); ?>" />
								<?php wp_nonce_field(SwishAiMenu::NONCE); ?>
								<button type="submit" class="button"><?php echo esc_html($label); ?></button>
							</form>
						<?php endforeach; ?>

						<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sfcore-ai-inline">
							<input type="hidden" name="action" value="<?php echo esc_attr(SwishAiMenu::ACTION_DISCONNECT); ?>" />
							<input type="hidden" name="provider" value="<?php echo esc_attr($slug); ?>" />
							<?php wp_nonce_field(SwishAiMenu::NONCE); ?>
							<button type="submit" class="button button-link-delete sfcore-ai-disconnect">
								<?php esc_html_e('Disconnect', 'swishfolio-core'); ?>
							</button>
						</form>
					</p>

					<?php if ($state['lastValidatedAt'] > 0) : ?>
						<p class="description">
							<?php
							printf(
								/* translators: %s: human-readable time difference. */
								esc_html__('Last tested %s ago.', 'swishfolio-core'),
								esc_html(human_time_diff($state['lastValidatedAt']))
							);
							?>
							<?php if ($state['status'] === 'invalid') : ?>
								<strong><?php esc_html_e('The key was rejected.', 'swishfolio-core'); ?></strong>
							<?php endif; ?>
						</p>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>

	<h2><?php esc_html_e('Enable AI', 'swishfolio-core'); ?></h2>

	<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
		<input type="hidden" name="action" value="<?php echo esc_attr(SwishAiMenu::ACTION_TOGGLE); ?>" />
		<?php wp_nonce_field(SwishAiMenu::NONCE); ?>

		<div class="notice notice-info sfcore-ai__disclosure">
			<p><strong><?php esc_html_e('What leaves your site', 'swishfolio-core'); ?></strong></p>
			<ul>
				<li><?php esc_html_e('The attributes and text of the blocks you select.', 'swishfolio-core'); ?></li>
				<li><?php esc_html_e('Neighbouring block names — not their content.', 'swishfolio-core'); ?></li>
				<li><?php esc_html_e('Your theme\'s design token names, and your brand context.', 'swishfolio-core'); ?></li>
				<li><?php esc_html_e('The instruction you type.', 'swishfolio-core'); ?></li>
			</ul>
			<p>
				<strong><?php esc_html_e('Nothing is sent unless you ask for it.', 'swishfolio-core'); ?></strong>
				<?php esc_html_e('No background or scheduled requests. Your key is stored encrypted on this site and goes only to the provider you connected.', 'swishfolio-core'); ?>
			</p>
		</div>

		<p>
			<label>
				<input type="checkbox" name="enabled" value="1" <?php checked($settings['enabled']); ?> />
				<?php esc_html_e('Allow AI editing and generation on this site', 'swishfolio-core'); ?>
			</label>
		</p>
		<p>
			<label>
				<input type="checkbox" name="acknowledged" value="1" <?php checked($settings['enabled']); ?> />
				<?php esc_html_e('I understand the data above is sent to my chosen AI provider.', 'swishfolio-core'); ?>
			</label>
		</p>

		<?php submit_button(__('Save', 'swishfolio-core')); ?>
	</form>
</div>
