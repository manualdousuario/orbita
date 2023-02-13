<?php
/**
 * Órbita
 *
 * @package           orbita
 * @author            Gabriel Nunes, Clarissa R. Mendes
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
			$count        = get_post_meta( get_the_id(), 'post_like_count', true );

			if ( ! $count ) {
				$count = 'nenhum';
			}
			$votes_text = $count > 1 ? 'votos' : 'voto';

			?>

		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

			<header class="entry-header">
				<?php

				wp_timezone_string( 'America/Sao_Paulo' );
				$date = get_the_date( 'd/m/Y H:i' );

				echo do_shortcode( '[orbita-header]' );

				if ( $external_url ) :
					the_title( '<h1 class="entry-title">🔗 <a href="' . esc_url( $external_url ) . '" rel="ugc">', '</a></h1>' );
				else :
					the_title( '<h1 class="entry-title">', '</h1>' );
				endif;
				?>
				<div class="entry-meta orbita-meta">
					<?php echo do_shortcode( '[orbita-vote]' ); ?><span data-votes-post-id="<?php the_ID(); ?>"><?php echo esc_html( $count ); ?></span> <?php echo esc_html( $votes_text ); ?> | <?php echo esc_html( get_the_author_meta( 'display_name', $post->post_author ) ); ?> em <?php echo esc_html( $date ); ?>
				</div>
			</header>

			<div class="entry-content">
				<?php esc_textarea( the_content() ); ?>
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
get_sidebar();
get_footer();
