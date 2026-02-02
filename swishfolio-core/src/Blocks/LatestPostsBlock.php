<?php
/**
 * Latest Posts Block
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Blocks;

use SwishfolioCore\Abstracts\AbstractBlock;
use SwishfolioCore\Contracts\RenderableInterface;

class LatestPostsBlock extends AbstractBlock implements RenderableInterface {
	/**
	 * Get the block name.
	 *
	 * @return string
	 */
	public function getName(): string {
		return 'latest-posts';
	}

	/**
	 * Render the block.
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content    Block content.
	 * @return string
	 */
	public function render( array $attributes, string $content ): string {
		$columns        = $attributes['columns'] ?? 3;
		$postsPerPage   = $attributes['postsPerPage'] ?? 3;
		$categories     = $attributes['categories'] ?? [];
		$showDate       = $attributes['showDate'] ?? true;
		$showAuthor     = $attributes['showAuthor'] ?? false;
		$showExcerpt    = $attributes['showExcerpt'] ?? true;
		$excerptLength  = $attributes['excerptLength'] ?? 20;
		$showImage      = $attributes['showFeaturedImage'] ?? true;
		$aspectRatio    = $attributes['imageAspectRatio'] ?? 'landscape';

		$query_args = array(
			'post_type'      => 'post',
			'posts_per_page' => $postsPerPage,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( ! empty( $categories ) ) {
			$query_args['category__in'] = $categories;
		}

		$posts = new \WP_Query( $query_args );

		ob_start();
		?>
		<div class="wp-block-swishfolio-core-latest-posts sfcore-latest-posts sfcore-latest-posts--cols-<?php echo esc_attr( $columns ); ?>">
			<div class="sfcore-latest-posts__grid">
				<?php if ( $posts->have_posts() ) : ?>
					<?php while ( $posts->have_posts() ) : $posts->the_post(); ?>
						<article class="sfcore-latest-posts__item">
							<a href="<?php the_permalink(); ?>" class="sfcore-latest-posts__link">
								<?php if ( $showImage ) : ?>
									<div class="sfcore-latest-posts__image sfcore-latest-posts__image--<?php echo esc_attr( $aspectRatio ); ?>">
										<?php if ( has_post_thumbnail() ) : ?>
											<?php the_post_thumbnail( 'medium_large' ); ?>
										<?php else : ?>
											<div class="sfcore-latest-posts__placeholder"></div>
										<?php endif; ?>
									</div>
								<?php endif; ?>
								<div class="sfcore-latest-posts__content">
									<?php if ( $showDate || $showAuthor ) : ?>
										<div class="sfcore-latest-posts__meta">
											<?php if ( $showDate ) : ?>
												<time class="sfcore-latest-posts__date" datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
													<?php echo esc_html( get_the_date() ); ?>
												</time>
											<?php endif; ?>
											<?php if ( $showAuthor ) : ?>
												<span class="sfcore-latest-posts__author">
													<?php echo esc_html( get_the_author() ); ?>
												</span>
											<?php endif; ?>
										</div>
									<?php endif; ?>
									<h3 class="sfcore-latest-posts__title"><?php the_title(); ?></h3>
									<?php if ( $showExcerpt ) : ?>
										<p class="sfcore-latest-posts__excerpt">
											<?php echo esc_html( wp_trim_words( get_the_excerpt(), $excerptLength, '...' ) ); ?>
										</p>
									<?php endif; ?>
								</div>
							</a>
						</article>
					<?php endwhile; ?>
					<?php wp_reset_postdata(); ?>
				<?php else : ?>
					<p class="sfcore-latest-posts__no-results">
						<?php esc_html_e( 'No posts found.', 'swishfolio-core' ); ?>
					</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
