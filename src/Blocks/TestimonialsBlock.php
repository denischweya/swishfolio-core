<?php
/**
 * Testimonials Block
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Blocks;

use SwishfolioCore\Abstracts\AbstractBlock;
use SwishfolioCore\Contracts\RenderableInterface;

class TestimonialsBlock extends AbstractBlock implements RenderableInterface {
	/**
	 * Get the block name.
	 *
	 * @return string
	 */
	public function getName(): string {
		return 'testimonials';
	}

	/**
	 * Render the block.
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content    Block content.
	 * @return string
	 */
	public function render( array $attributes, string $content ): string {
		$displayMode      = $attributes['displayMode'] ?? 'grid';
		$columns          = $attributes['columns'] ?? 3;
		$postsPerPage     = $attributes['postsPerPage'] ?? 6;
		$showRating       = $attributes['showRating'] ?? true;
		$showCompany      = $attributes['showCompany'] ?? true;
		$showAvatar       = $attributes['showAvatar'] ?? true;
		$autoplay         = $attributes['autoplay'] ?? false;
		$autoplaySpeed    = $attributes['autoplaySpeed'] ?? 5000;
		$cardStyle        = $attributes['cardStyle'] ?? 'card';
		$cardBgColor      = $attributes['cardBackgroundColor'] ?? '';
		$cardBorderColor  = $attributes['cardBorderColor'] ?? '';
		$cardBorderWidth  = $attributes['cardBorderWidth'] ?? 3;
		$cardBorderRadius = $attributes['cardBorderRadius'] ?? 0;
		$cardShadow       = $attributes['cardShadow'] ?? 'medium';
		$textColor        = $attributes['textColor'] ?? '';

		$query_args = array(
			'post_type'      => 'sfcore_testimonial',
			'posts_per_page' => $postsPerPage,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$testimonials = new \WP_Query( $query_args );

		// Build wrapper classes.
		$wrapper_class = 'wp-block-swishfolio-core-testimonials sfcore-testimonials';
		$wrapper_class .= ' sfcore-testimonials--' . $displayMode;
		$wrapper_class .= ' sfcore-testimonials--style-' . $cardStyle;
		$wrapper_class .= ' sfcore-testimonials--shadow-' . $cardShadow;
		if ( $displayMode === 'grid' ) {
			$wrapper_class .= ' sfcore-testimonials--cols-' . $columns;
		}

		// Build carousel data attributes.
		$wrapper_attrs = '';
		if ( $displayMode === 'carousel' ) {
			$wrapper_attrs = sprintf(
				'data-autoplay="%s" data-autoplay-speed="%d"',
				$autoplay ? 'true' : 'false',
				$autoplaySpeed
			);
		}

		// Build CSS custom properties for style controls.
		$style_parts = array();
		if ( $cardBgColor ) {
			$style_parts[] = '--testimonial-bg: ' . esc_attr( $cardBgColor );
		}
		if ( $cardBorderColor ) {
			$style_parts[] = '--testimonial-border-color: ' . esc_attr( $cardBorderColor );
		}
		$style_parts[] = '--testimonial-border-width: ' . intval( $cardBorderWidth ) . 'px';
		$style_parts[] = '--testimonial-border-radius: ' . intval( $cardBorderRadius ) . 'px';
		if ( $textColor ) {
			$style_parts[] = '--testimonial-text-color: ' . esc_attr( $textColor );
		}
		$custom_styles = implode( '; ', $style_parts );

		ob_start();
		?>
		<div class="<?php echo esc_attr( $wrapper_class ); ?>" <?php echo $wrapper_attrs; ?><?php if ( $custom_styles ) : ?> style="<?php echo esc_attr( $custom_styles ); ?>"<?php endif; ?>>
			<div class="sfcore-testimonials__container">
				<?php if ( $testimonials->have_posts() ) : ?>
					<?php while ( $testimonials->have_posts() ) : $testimonials->the_post(); ?>
						<?php
						$author_name  = get_post_meta( get_the_ID(), '_sfcore_author_name', true );
						$author_title = get_post_meta( get_the_ID(), '_sfcore_author_title', true );
						$company      = get_post_meta( get_the_ID(), '_sfcore_company', true );
						$rating       = get_post_meta( get_the_ID(), '_sfcore_rating', true );
						$rating       = $rating ? intval( $rating ) : 5;

						// Use post title as author name if meta is empty.
						if ( empty( $author_name ) ) {
							$author_name = get_the_title();
						}

						// Pre-compute shared HTML fragments.
						$rating_html = '';
						if ( $showRating && $rating > 0 ) {
							$rating_html = '<div class="sfcore-testimonials__rating" aria-label="' . sprintf( esc_attr__( '%d out of 5 stars', 'swishfolio-core' ), $rating ) . '">';
							for ( $i = 1; $i <= 5; $i++ ) {
								$rating_html .= '<span class="sfcore-testimonials__star ' . ( $i <= $rating ? 'is-filled' : '' ) . '">&#9733;</span>';
							}
							$rating_html .= '</div>';
						}

						$avatar_html = '';
						if ( $showAvatar && has_post_thumbnail() ) {
							$avatar_html = '<div class="sfcore-testimonials__avatar">' . get_the_post_thumbnail( get_the_ID(), 'thumbnail' ) . '</div>';
						}

						$avatar_large_html = '';
						if ( $showAvatar && has_post_thumbnail() ) {
							$avatar_large_html = '<div class="sfcore-testimonials__avatar sfcore-testimonials__avatar--large">' . get_the_post_thumbnail( get_the_ID(), 'thumbnail' ) . '</div>';
						}

						$author_info_html = '<div class="sfcore-testimonials__author">';
						$author_info_html .= '<cite class="sfcore-testimonials__name">' . esc_html( $author_name ) . '</cite>';
						$title_parts = array();
						if ( $author_title ) {
							$title_parts[] = $author_title;
						}
						if ( $showCompany && $company ) {
							$title_parts[] = $company;
						}
						if ( ! empty( $title_parts ) ) {
							$author_info_html .= '<span class="sfcore-testimonials__title">' . esc_html( implode( ', ', $title_parts ) ) . '</span>';
						}
						$author_info_html .= '</div>';

						ob_start();
						the_content();
						$content_html = ob_get_clean();

						// Render variant-specific HTML.
						switch ( $cardStyle ) :
							case 'bubble':
								?>
								<article class="sfcore-testimonials__item">
									<div class="sfcore-testimonials__bubble">
										<?php echo $rating_html; ?>
										<div class="sfcore-testimonials__content">
											<?php echo $content_html; ?>
										</div>
									</div>
									<div class="sfcore-testimonials__bubble-author">
										<?php echo $avatar_html; ?>
										<?php echo $author_info_html; ?>
									</div>
								</article>
								<?php
								break;

							case 'centered':
								?>
								<article class="sfcore-testimonials__item">
									<blockquote class="sfcore-testimonials__quote sfcore-testimonials__quote--centered">
										<?php echo $avatar_large_html; ?>
										<?php echo $rating_html; ?>
										<div class="sfcore-testimonials__content">
											<?php echo $content_html; ?>
										</div>
										<footer class="sfcore-testimonials__footer sfcore-testimonials__footer--centered">
											<?php echo $author_info_html; ?>
										</footer>
									</blockquote>
								</article>
								<?php
								break;

							case 'minimal':
								?>
								<article class="sfcore-testimonials__item">
									<blockquote class="sfcore-testimonials__quote">
										<?php echo $rating_html; ?>
										<div class="sfcore-testimonials__content">
											<?php echo $content_html; ?>
										</div>
										<footer class="sfcore-testimonials__footer">
											<?php echo $avatar_html; ?>
											<?php echo $author_info_html; ?>
										</footer>
									</blockquote>
								</article>
								<?php
								break;

							case 'card':
							default:
								?>
								<article class="sfcore-testimonials__item">
									<blockquote class="sfcore-testimonials__quote">
										<?php echo $rating_html; ?>
										<div class="sfcore-testimonials__content">
											<?php echo $content_html; ?>
										</div>
										<footer class="sfcore-testimonials__footer">
											<?php echo $avatar_html; ?>
											<?php echo $author_info_html; ?>
										</footer>
									</blockquote>
								</article>
								<?php
								break;
						endswitch;
						?>
					<?php endwhile; ?>
					<?php wp_reset_postdata(); ?>
				<?php else : ?>
					<p class="sfcore-testimonials__no-results">
						<?php esc_html_e( 'No testimonials found.', 'swishfolio-core' ); ?>
					</p>
				<?php endif; ?>
			</div>

			<?php if ( $displayMode === 'carousel' && $testimonials->post_count > 1 ) : ?>
				<div class="sfcore-testimonials__nav">
					<button class="sfcore-testimonials__prev" aria-label="<?php esc_attr_e( 'Previous', 'swishfolio-core' ); ?>">
						<span aria-hidden="true">←</span>
					</button>
					<button class="sfcore-testimonials__next" aria-label="<?php esc_attr_e( 'Next', 'swishfolio-core' ); ?>">
						<span aria-hidden="true">→</span>
					</button>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
