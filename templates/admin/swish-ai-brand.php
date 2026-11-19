<?php
/**
 * Swish AI → Brand & Context.
 *
 * @package SwishfolioCore
 *
 * @var array  $brand  Stored brand context.
 * @var string $notice Notice slug.
 */

if (! defined('ABSPATH')) {
    exit;
}

use SwishfolioCore\Admin\SwishAiMenu;
use SwishfolioCore\AI\Settings;

$notice_text = SwishAiMenu::noticeText($notice);

$style_labels = [
    'minimalist'  => __('Minimalist & clean', 'swishfolio-core'),
    'tech-modern' => __('Tech & modern (SaaS)', 'swishfolio-core'),
    'corporate'   => __('Corporate & serious', 'swishfolio-core'),
    'brutalist'   => __('Bold & brutalist', 'swishfolio-core'),
    'luxury'      => __('Luxury & elegant', 'swishfolio-core'),
    'organic'     => __('Organic & soft', 'swishfolio-core'),
];

$shape_labels = [
    'sharp'   => __('Sharp (square)', 'swishfolio-core'),
    'soft'    => __('Soft (slightly rounded)', 'swishfolio-core'),
    'rounded' => __('Rounded', 'swishfolio-core'),
    'pill'    => __('Pill (very round)', 'swishfolio-core'),
];

$tone_labels = [
    'professional' => __('Professional & formal', 'swishfolio-core'),
    'friendly'     => __('Friendly & casual', 'swishfolio-core'),
    'persuasive'   => __('Persuasive & sales-oriented', 'swishfolio-core'),
    'educational'  => __('Educational & informative', 'swishfolio-core'),
    'enthusiastic' => __('Enthusiastic & energetic', 'swishfolio-core'),
];
?>
<div class="wrap sfcore-ai">
	<h1><?php esc_html_e('Brand & Context', 'swishfolio-core'); ?></h1>

	<?php if ($notice_text !== null) : ?>
		<div class="notice notice-<?php echo esc_attr($notice_text['type']); ?> is-dismissible">
			<p><?php echo esc_html($notice_text['message']); ?></p>
		</div>
	<?php endif; ?>

	<p class="sfcore-ai__lede">
		<?php esc_html_e('This context is added to every generation prompt, so the AI writes for your business rather than in generic filler. Colours, spacing and fonts are not set here — those come from your theme\'s design tokens, which the AI is already required to use.', 'swishfolio-core'); ?>
	</p>

	<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
		<input type="hidden" name="action" value="<?php echo esc_attr(SwishAiMenu::ACTION_BRAND); ?>" />
		<?php wp_nonce_field(SwishAiMenu::NONCE); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><label for="siteName"><?php esc_html_e('Business name', 'swishfolio-core'); ?></label></th>
					<td>
						<input type="text" class="regular-text" id="siteName" name="siteName"
							value="<?php echo esc_attr($brand['siteName']); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="siteDescription"><?php esc_html_e('What you do', 'swishfolio-core'); ?></label></th>
					<td>
						<textarea id="siteDescription" name="siteDescription" class="large-text" rows="5"
							maxlength="<?php echo esc_attr(Settings::DESCRIPTION_LIMIT); ?>"
							data-counter="siteDescription-counter"><?php echo esc_textarea($brand['siteDescription']); ?></textarea>
						<p class="description" id="siteDescription-counter">
							<?php echo esc_html(strlen($brand['siteDescription'])); ?>/<?php echo esc_html(Settings::DESCRIPTION_LIMIT); ?>
						</p>
						<p class="description">
							<?php esc_html_e('Who you serve, what you sell, and anything the AI must always respect. The single highest-leverage field on this page.', 'swishfolio-core'); ?>
						</p>
					</td>
				</tr>
				<?php
				foreach ([
					['designStyle', __('Design style', 'swishfolio-core'), $style_labels],
					['blockShapes', __('Block shapes', 'swishfolio-core'), $shape_labels],
					['copywritingTone', __('Copywriting tone', 'swishfolio-core'), $tone_labels],
				] as $field) :
					list($name, $label, $options) = $field;
					?>
					<tr>
						<th scope="row"><label for="<?php echo esc_attr($name); ?>"><?php echo esc_html($label); ?></label></th>
						<td>
							<select id="<?php echo esc_attr($name); ?>" name="<?php echo esc_attr($name); ?>" class="regular-text">
								<option value=""><?php esc_html_e('— No preference —', 'swishfolio-core'); ?></option>
								<?php foreach ($options as $value => $option_label) : ?>
									<option value="<?php echo esc_attr($value); ?>" <?php selected($brand[$name], $value); ?>>
										<?php echo esc_html($option_label); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php submit_button(__('Save brand context', 'swishfolio-core')); ?>
	</form>
</div>
