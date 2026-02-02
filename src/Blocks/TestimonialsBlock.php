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
		$displayMode   = $attributes['displayMode'] ?? 'grid';
		$columns       = $attributes['columns'] ?? 3;
		$postsPerPage  = $attributes['postsPerPage'] ?? 6;
		$showRating    = $attributes['showRating'] ?? true;
		$showCompany   = $attributes['showCompany'] ?? true;
		$showAvatar    = $attributes['showAvatar'] ?? true;
		$autoplay      = $attributes['autoplay'] ?? false;
		$autoplaySpeed = $attributes['autoplaySpeed'] ?? 5000;

		$query_args = array(
			'post_type'      => 'sfcore_testimonial',
			'posts_per_page' => $postsPerPage,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$testimonials = new \WP_Query( $query_args );

		$wrapper_class = 'wp-block-swishfolio-core-testimonials sfcore-testimonials';
		$wrapper_class .= ' sfcore-testimonials--' . $displayMode;
		if ( $displayMode === 'grid' ) {
			$wrapper_class .= ' sfcore-testimonials--cols-' . $columns;
		}

		$wrapper_attrs = '';
		if ( $displayMode === 'carousel' ) {
			$wrapper_attrs = sprintf(
				'data-autoplay="%s" data-autoplay-speed="%d"',
				$autoplay ? 'true' : 'false',
				$autoplaySpeed
			);
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( $wrapper_class ); ?>" <?php echo $wrapper_attrs; ?>>
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
						?>
						<article class="sfcore-testimonials__item">
							<blockquote class="sfcore-testimonials__quote">
								<?php if ( $showRating && $rating > 0 ) : ?>
									<div class="sfcore-testimonials__rating" aria-label="<?php printf( esc_attr__( '%d out of 5 stars', 'swishfolio-core' ), $rating ); ?>">
										<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
											<span class="sfcore-testimonials__star <?php echo $i <= $rating ? 'is-filled' : ''; ?>">★</span>
										<?php endfor; ?>
									</div>
								<?php endif; ?>

								<div class="sfcore-testimonials__content">
									<?php the_content(); ?>
								</div>

								<footer class="sfcore-testimonials__footer">
									<?php if ( $showAvatar && has_post_thumbnail() ) : ?>
										<div class="sfcore-testimonials__avatar">
											<?php the_post_thumbnail( 'thumbnail' ); ?>
										</div>
									<?php endif; ?>
									<div class="sfcore-testimonials__author">
										<cite class="sfcore-testimonials__name"><?php echo esc_html( $author_name ); ?></cite>
										<?php if ( $author_title || ( $showCompany && $company ) ) : ?>
											<span class="sfcore-testimonials__title">
												<?php
												$title_parts = array();
												if ( $author_title ) {
													$title_parts[] = $author_title;
												}
												if ( $showCompany && $company ) {
													$title_parts[] = $company;
												}
												echo esc_html( implode( ', ', $title_parts ) );
												?>
											</span>
										<?php endif; ?>
									</div>
								</footer>
							</blockquote>
						</article>
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
