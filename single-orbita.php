<?php
/**
 * Órbita
 *
 * @package           orbita
 * @author            Gabriel Nunes, Rodrigo Ghedin, Clarissa Mendes, Renan Altendorf
 * @copyright         2022 Manual do Usuário
 * @license           GPL-3.0
 **/

get_header();
?>

<div id="primary" class="content-area">
	<main id="main" class="site-main">

		<?php
		while ( have_posts() ) :
			the_post();

			$external_url = get_post_meta( get_the_id(), 'external_url', true );
			$attach_file = get_post_meta( get_the_id(), 'attach_file', true );
			$only_domain  = wp_parse_url( str_replace( 'www.', '', $external_url ), PHP_URL_HOST );
			$count        = get_post_meta( get_the_id(), 'post_like_count', true );

			if ( ! $count ) {
				$count = 'nenhum';
			}
			$votes_text = $count > 1 ? 'votos' : 'voto';

			if ( ! $external_url ) {
				$get_post_id = get_the_ID();
				$get_term    = get_term_by( 'name', 'conversas', 'orbita_category' );
				if(isset($get_term->term_id)) {
					$get_term_id = $get_term->term_id;

					wp_set_object_terms( $get_post_id, $get_term_id, 'orbita_category' );
				}
			}

			?>

		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

			<header class="entry-header">
				<?php

				wp_timezone_string( 'America/Sao_Paulo' );
				$date = get_the_date( 'd/m/Y H:i' );

				$author_display_name = esc_html( get_the_author_meta( 'display_name', $post->post_author ) );
				$author_nickname = esc_html( get_the_author_meta( 'nickname', $post->post_author ) );

				echo do_shortcode( '[orbita-header]' );

				if ( $external_url ) :
					$separator = '?';
					if(strpos($external_url, '?') !== false) :
						$separator = '&';
					endif;
					the_title( '<h1 class="entry-title orbita-post-title"><a href="' . esc_url( $external_url ) . $separator . 'utm_source=ManualdoUsuarioNet&utm_medium=Orbita" rel="ugc">', '</a>&nbsp;' . orbita_link_options( $external_url, get_the_title() ) . '<span class="domain">' .  $only_domain . '</span></h1>' );
				else :
					the_title( '<h1 class="entry-title orbita-post-title">', '</h1>' );
				endif;
				?>

				<div class="entry-meta orbita-meta">
					<?php echo do_shortcode( '[orbita-vote]' ); ?><span data-votes-post-id="<?php the_ID(); ?>"><?php echo esc_html( $count ); ?></span> <?php echo esc_html( $votes_text ); ?> · <?php echo $author_display_name; ?> · <?php echo esc_html( $date ); ?>
				</div>
			</header>

			<div class="entry-content">
				<?php
					if( $external_url ) {
						$providers = ['youtube.com', 'youtu.be', 'vimeo.com', 'dailymotion.com', 'dai.ly'];
						foreach($providers as $provider) {
							if( strpos( $only_domain, $provider ) !== false ) {
								?>
									<div class="orbita-oembed orbita-oembed-16by9">
										<?php echo wp_oembed_get( $external_url ); ?>
									</div>
								<?php
								break;
							}
						}
					} else if( $attach_file == 1 ) {
						if ( has_post_thumbnail() ) {
							$large_image_url = wp_get_attachment_image_src( get_post_thumbnail_id(), 'large' );
							$full_image_url = wp_get_attachment_image_src( get_post_thumbnail_id(), 'full' );
							if ( ! empty( $large_image_url[0] ) ) {
								printf( '<a target="_blank" href="%1$s" alt="%2$s">%3$s</a>',
									esc_url( $full_image_url[0] ),
									esc_attr( get_the_title() ),
									dez_post_thumbnail()
								);
							}
						}	
					}
					esc_textarea( the_content() );
				?>
			</div>

			<footer class="entry-footer">
				<p><a href="/orbita">&laquo; Voltar ao índice de links</a></p>
			</footer>

		</article>
			<?php
			// If comments are open or we have at least one comment, load up the comment template.
			if ( comments_open() || get_comments_number() ) :
				comments_template();
			endif;

		endwhile; // End of the loop.
		?>

	</main><!-- #main -->
</div><!-- #primary -->

<?php
get_footer();
