<?php
/**
 * Portfolio Block
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\Blocks;

use SwishfolioCore\Abstracts\AbstractBlock;
use SwishfolioCore\Contracts\RenderableInterface;
use SwishfolioCore\PostTypes\ProjectPostType;

class PortfolioBlock extends AbstractBlock implements RenderableInterface {
	/**
	 * Get the block name.
	 *
	 * @return string
	 */
	public function getName(): string {
		return 'portfolio';
	}

	/**
	 * Render the block.
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content    Block content.
	 * @return string
	 */
	public function render( array $attributes, string $content ): string {
		// Layout settings.
		$layout            = $attributes['layout'] ?? 'grid';
		$cardStyle         = $attributes['cardStyle'] ?? 'default';
		$columns           = $attributes['columns'] ?? 3;
		$gap               = $attributes['gap'] ?? 24;
		$postsPerPage      = $attributes['postsPerPage'] ?? 6;
		$aspectRatio       = $attributes['imageAspectRatio'] ?? 'landscape';
		$hoverEffect       = $attributes['hoverEffect'] ?? 'lift';

		// Query settings.
		$categories        = $attributes['categories'] ?? [];
		$projectTypes      = $attributes['projectTypes'] ?? [];
		$orderBy           = $attributes['orderBy'] ?? 'date';
		$order             = $attributes['order'] ?? 'DESC';

		// Filter settings.
		$showFilters       = $attributes['showFilters'] ?? false;
		$filterTaxonomy    = $attributes['filterTaxonomy'] ?? 'category';

		// Featured project (hero, rendered above the grid). Independent of the
		// chosen layout — when set, the project always appears as a hero, and
		// the remaining projects render in whatever layout the user picked.
		$featuredProjectId = (int) ( $attributes['featuredProjectId'] ?? 0 );
		$hasFeatured       = ( $featuredProjectId > 0 );

		// Build query args.
		$query_args = array(
			'post_type'      => 'sfcore_project',
			'posts_per_page' => $postsPerPage,
			'orderby'        => $orderBy,
			'order'          => $order,
		);

		// Tax query.
		$tax_query = array();
		if ( ! empty( $categories ) ) {
			$tax_query[] = array(
				'taxonomy' => 'sfcore_project_cat',
				'field'    => 'term_id',
				'terms'    => $categories,
			);
		}
		if ( ! empty( $projectTypes ) ) {
			$tax_query[] = array(
				'taxonomy' => 'sfcore_project_type',
				'field'    => 'term_id',
				'terms'    => $projectTypes,
			);
		}
		if ( ! empty( $tax_query ) ) {
			if ( count( $tax_query ) > 1 ) {
				$tax_query['relation'] = 'AND';
			}
			$query_args['tax_query'] = $tax_query;
		}

		// Deduplicate the featured project from the grid below.
		if ( $hasFeatured ) {
			$query_args['post__not_in'] = array( $featuredProjectId );
		}

		$projects = new \WP_Query( $query_args );

		// Get terms for filters.
		$filter_taxonomy_slug = $filterTaxonomy === 'type' ? 'sfcore_project_type' : 'sfcore_project_cat';
		$filter_terms = get_terms( array(
			'taxonomy'   => $filter_taxonomy_slug,
			'hide_empty' => true,
		) );

		// Wrapper classes — taxonomy style class lets descendant pills react in CSS.
		$taxonomyStyle = $attributes['taxonomyStyle'] ?? 'plain';
		$wrapper_classes = array(
			'wp-block-swishfolio-core-portfolio',
			'sfcore-portfolio',
			'sfcore-portfolio--layout-' . $layout,
			'sfcore-portfolio--style-' . $cardStyle,
			'sfcore-portfolio--cols-' . $columns,
			'sfcore-portfolio--hover-' . $hoverEffect,
			'sfcore-portfolio--tax-' . $taxonomyStyle,
		);

		// CSS custom properties for gap, columns, taxonomy color, title typography, image radius.
		$custom_styles_parts = array(
			'--portfolio-gap: ' . intval( $gap ) . 'px',
			'--portfolio-columns: ' . intval( $columns ),
		);
		$taxonomyColor = $attributes['taxonomyColor'] ?? '';
		if ( $taxonomyColor ) {
			$custom_styles_parts[] = '--sf-portfolio-tax-color: ' . sanitize_hex_color( $taxonomyColor ) ?: $taxonomyColor;
		}
		$titleFontSize = $attributes['titleFontSize'] ?? '';
		if ( $titleFontSize ) {
			$custom_styles_parts[] = '--sf-portfolio-title-size: ' . esc_attr( $titleFontSize );
		}
		$titleFontWeight = $attributes['titleFontWeight'] ?? '';
		if ( $titleFontWeight ) {
			$custom_styles_parts[] = '--sf-portfolio-title-weight: ' . intval( $titleFontWeight );
		}
		$imageBorderRadius = (int) ( $attributes['imageBorderRadius'] ?? 0 );
		if ( $imageBorderRadius > 0 ) {
			$custom_styles_parts[] = '--sf-portfolio-image-radius: ' . $imageBorderRadius . 'px';
		}
		$cardContentBackground = $attributes['cardContentBackground'] ?? '';
		if ( $cardContentBackground ) {
			$custom_styles_parts[] = '--sf-portfolio-content-bg: ' . esc_attr( $cardContentBackground );
		}
		$custom_styles = implode( '; ', $custom_styles_parts );

		ob_start();
		?>
		<div class="<?php echo esc_attr( implode( ' ', $wrapper_classes ) ); ?>" style="<?php echo esc_attr( $custom_styles ); ?>">
			<?php if ( $showFilters && ! empty( $filter_terms ) && ! is_wp_error( $filter_terms ) ) : ?>
				<div class="sfcore-portfolio__filters">
					<button class="sfcore-portfolio__filter is-active" data-filter="*" type="button">
						<?php esc_html_e( 'All', 'swishfolio-core' ); ?>
					</button>
					<?php foreach ( $filter_terms as $term ) : ?>
						<button class="sfcore-portfolio__filter" data-filter="<?php echo esc_attr( $term->slug ); ?>" type="button">
							<?php echo esc_html( $term->name ); ?>
						</button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( $hasFeatured ) : ?>
				<?php
				$featured_post = get_post( $featuredProjectId );
				if ( $featured_post && $featured_post->post_type === 'sfcore_project' ) :
					global $post;
					$post = $featured_post;
					setup_postdata( $post );
					?>
					<div class="sfcore-portfolio__featured">
						<?php echo $this->renderCard( $featured_post->ID, $attributes, true ); ?>
					</div>
					<?php
					wp_reset_postdata();
				endif;
				?>
			<?php endif; ?>

			<div class="sfcore-portfolio__grid">
				<?php if ( $projects->have_posts() ) : ?>
					<?php while ( $projects->have_posts() ) : $projects->the_post(); ?>
						<?php echo $this->renderCard( get_the_ID(), $attributes, false ); ?>
					<?php endwhile; ?>
					<?php wp_reset_postdata(); ?>
				<?php elseif ( ! $hasFeatured ) : ?>
					<p class="sfcore-portfolio__no-results">
						<?php esc_html_e( 'No projects found.', 'swishfolio-core' ); ?>
					</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render a single project card. Assumes setup_postdata() has already been
	 * called so template tags like the_title() work.
	 *
	 * @param int   $post_id     Project post ID.
	 * @param array $attributes  Block attributes.
	 * @param bool  $is_featured Whether this card is the featured hero.
	 * @return string
	 */
	private function renderCard( int $post_id, array $attributes, bool $is_featured ): string {
		$cardStyle         = $attributes['cardStyle'] ?? 'default';
		$aspectRatio       = $attributes['imageAspectRatio'] ?? 'landscape';
		$showExcerpt       = $attributes['showExcerpt'] ?? true;
		$showCategory      = $attributes['showCategory'] ?? true;
		$showProjectType   = $attributes['showProjectType'] ?? false;
		$showStatus        = $attributes['showStatus'] ?? false;
		$showCompletionDate = $attributes['showCompletionDate'] ?? false;
		$showSkills        = $attributes['showSkills'] ?? false;
		$showClient        = $attributes['showClient'] ?? false;
		$showClientLabel   = $attributes['showClientLabel'] ?? true;
		$showProjectUrl    = $attributes['showProjectUrl'] ?? false;
		$showViewButton    = $attributes['showViewButton'] ?? true;
		$viewButtonText    = $attributes['viewButtonText'] ?? __( 'View Project', 'swishfolio-core' );

		// Get taxonomies.
		$project_cats        = get_the_terms( $post_id, 'sfcore_project_cat' );
		$project_types_terms = get_the_terms( $post_id, 'sfcore_project_type' );

		// Build filter classes.
		$filter_classes = '';
		if ( $project_cats && ! is_wp_error( $project_cats ) ) {
			foreach ( $project_cats as $cat ) {
				$filter_classes .= ' filter-' . $cat->slug;
			}
		}
		if ( $project_types_terms && ! is_wp_error( $project_types_terms ) ) {
			foreach ( $project_types_terms as $type ) {
				$filter_classes .= ' filter-' . $type->slug;
			}
		}

		// Get custom fields.
		$completion_date = get_post_meta( $post_id, '_sfcore_completion_date', true );
		$project_status  = get_post_meta( $post_id, '_sfcore_project_status', true );
		$skills          = get_post_meta( $post_id, '_sfcore_skills', true );
		$client_name     = get_post_meta( $post_id, '_sfcore_client_name', true );
		$project_url     = get_post_meta( $post_id, '_sfcore_project_url', true );

		// Format status.
		$status_labels = ProjectPostType::getStatusOptions();
		$status_label  = isset( $status_labels[ $project_status ] ) ? $status_labels[ $project_status ] : '';

		// Format date.
		$formatted_date = '';
		if ( $completion_date ) {
			$date_obj = \DateTime::createFromFormat( 'Y-m-d', $completion_date );
			if ( $date_obj ) {
				$formatted_date = $date_obj->format( get_option( 'date_format' ) );
			}
		}

		// Names + skills.
		$cat_names = array();
		if ( $project_cats && ! is_wp_error( $project_cats ) ) {
			foreach ( $project_cats as $cat ) {
				$cat_names[] = $cat->name;
			}
		}
		$type_names = array();
		if ( $project_types_terms && ! is_wp_error( $project_types_terms ) ) {
			foreach ( $project_types_terms as $type ) {
				$type_names[] = $type->name;
			}
		}
		$skills_array = is_array( $skills ) ? $skills : array();

		$article_class = 'sfcore-portfolio__item' . esc_attr( $filter_classes );
		if ( $is_featured ) {
			$article_class .= ' sfcore-portfolio__item--featured';
		}

		ob_start();
		?>
		<article class="<?php echo esc_attr( $article_class ); ?>">
			<div class="sfcore-portfolio__card">
				<?php if ( $cardStyle !== 'minimal' ) : ?>
					<div class="sfcore-portfolio__image sfcore-portfolio__image--<?php echo esc_attr( $aspectRatio ); ?>">
						<a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" class="sfcore-portfolio__image-link">
							<?php if ( has_post_thumbnail( $post_id ) ) : ?>
								<?php echo get_the_post_thumbnail( $post_id, $is_featured ? 'full' : 'large' ); ?>
							<?php else : ?>
								<div class="sfcore-portfolio__placeholder">
									<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
										<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
										<circle cx="8.5" cy="8.5" r="1.5"/>
										<polyline points="21 15 16 10 5 21"/>
									</svg>
								</div>
							<?php endif; ?>
							<?php if ( $cardStyle === 'overlay' ) : ?>
								<div class="sfcore-portfolio__overlay">
									<div class="sfcore-portfolio__overlay-content">
										<h3 class="sfcore-portfolio__title"><?php echo esc_html( get_the_title( $post_id ) ); ?></h3>
										<?php if ( $showViewButton ) : ?>
											<span class="sfcore-portfolio__btn">
												<?php echo esc_html( $viewButtonText ); ?>
												<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
													<line x1="5" y1="12" x2="19" y2="12"></line>
													<polyline points="12 5 19 12 12 19"></polyline>
												</svg>
											</span>
										<?php endif; ?>
									</div>
								</div>
							<?php endif; ?>
						</a>

						<?php if ( $showStatus && $status_label ) : ?>
							<span class="sfcore-portfolio__status sfcore-portfolio__status--<?php echo esc_attr( $project_status ); ?>">
								<?php echo esc_html( $status_label ); ?>
							</span>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php if ( $cardStyle !== 'overlay' ) : ?>
					<div class="sfcore-portfolio__content">
						<?php if ( $showCategory && ! empty( $cat_names ) ) : ?>
							<div class="sfcore-portfolio__taxonomies">
								<?php foreach ( $cat_names as $cat_name ) : ?>
									<span class="sfcore-portfolio__badge sfcore-portfolio__badge--category">
										<?php echo esc_html( $cat_name ); ?>
									</span>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>

						<?php if ( $showProjectType && ! empty( $type_names ) ) : ?>
							<div class="sfcore-portfolio__taxonomies sfcore-portfolio__taxonomies--types">
								<?php foreach ( $type_names as $type_name ) : ?>
									<span class="sfcore-portfolio__badge sfcore-portfolio__badge--type">
										<?php echo esc_html( $type_name ); ?>
									</span>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>

						<h3 class="sfcore-portfolio__title">
							<a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"><?php echo esc_html( get_the_title( $post_id ) ); ?></a>
						</h3>

						<?php if ( $showExcerpt && has_excerpt( $post_id ) ) : ?>
							<p class="sfcore-portfolio__excerpt"><?php echo esc_html( get_the_excerpt( $post_id ) ); ?></p>
						<?php endif; ?>

						<?php if ( $showClient && $client_name ) : ?>
							<div class="sfcore-portfolio__meta-item">
								<?php if ( $showClientLabel ) : ?>
									<span class="sfcore-portfolio__meta-label"><?php esc_html_e( 'Client', 'swishfolio-core' ); ?></span>
								<?php endif; ?>
								<span class="sfcore-portfolio__meta-value"><?php echo esc_html( $client_name ); ?></span>
							</div>
						<?php endif; ?>

						<?php if ( $showCompletionDate && $formatted_date ) : ?>
							<div class="sfcore-portfolio__meta-item">
								<span class="sfcore-portfolio__meta-label"><?php esc_html_e( 'Completed', 'swishfolio-core' ); ?></span>
								<span class="sfcore-portfolio__meta-value"><?php echo esc_html( $formatted_date ); ?></span>
							</div>
						<?php endif; ?>

						<?php if ( $showSkills && ! empty( $skills_array ) ) : ?>
							<div class="sfcore-portfolio__skills">
								<?php foreach ( $skills_array as $skill ) : ?>
									<span class="sfcore-portfolio__skill"><?php echo esc_html( $skill ); ?></span>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>

						<div class="sfcore-portfolio__actions">
							<?php if ( $showViewButton ) : ?>
								<a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" class="sfcore-portfolio__btn sfcore-portfolio__btn--primary">
									<?php echo esc_html( $viewButtonText ); ?>
									<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
										<line x1="5" y1="12" x2="19" y2="12"></line>
										<polyline points="12 5 19 12 12 19"></polyline>
									</svg>
								</a>
							<?php endif; ?>

							<?php if ( $showProjectUrl && $project_url ) : ?>
								<a href="<?php echo esc_url( $project_url ); ?>" class="sfcore-portfolio__btn sfcore-portfolio__btn--secondary" target="_blank" rel="noopener noreferrer">
									<?php esc_html_e( 'Live Site', 'swishfolio-core' ); ?>
									<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
										<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
										<polyline points="15 3 21 3 21 9"></polyline>
										<line x1="10" y1="14" x2="21" y2="3"></line>
									</svg>
								</a>
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</article>
		<?php
		return ob_get_clean();
	}
}
