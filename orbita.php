<?php
/**
 * Órbita
 *
 * @package           orbita
 * @author            Gabriel Nunes, Clarissa R. Mendes
 * @copyright         2022 Manual do Usuário
 * @license           GPL-3.0
 *
 * @wordpress-plugin
 * Plugin Name:     Órbita
 * Plugin URI:      https://gnun.es
 * Description:     Órbita é o plugin para criar um sistema Hacker News-like para o Manual do Usuário
 * Version:         1.1.1
 * Author:          Gabriel Nunes
 * Author URI:      https://gnun.es
 * License:         GPL v3
 * License URI:     https://www.gnu.org/licenses/gpl-3.0.html
 **/

/*
Órbita is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

Órbita is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Órbita. If not, see https://www.gnu.org/licenses/gpl-3.0.html.
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue style file
 */
function orbita_enqueue_styles() {
	wp_enqueue_style( 'orbita', plugins_url( '/public/main.css', __FILE__ ), array(), '1', false );
}

add_action( 'wp_enqueue_scripts', 'orbita_enqueue_styles' );

/**
 * Enqueue script file
 */
function orbita_enqueue_scripts() {
	wp_enqueue_script( 'orbita', plugins_url( '/public/main.min.js', __FILE__ ), array(), '1', false );
	wp_localize_script( 'orbita', 'orbitaApi', array(
		'restURL' => rest_url(),
		'restNonce' => wp_create_nonce('wp_rest')
	));
}

add_action( 'wp_enqueue_scripts', 'orbita_enqueue_scripts' );

/**
 * Setup post type
 */
function orbita_setup_post_type() {
	register_post_type(
		'orbita_post',
		array(
			'labels'              => array(
				'name'          => __( 'Órbita' ),
				'singular_name' => __( 'Órbita' ),
			),
			'public'              => true,
			'show_ui'             => true,
			'hierarchical'        => true,
			'has_archive'         => false,
			'supports'            => array( 'title', 'custom-fields', 'author', 'comments', 'editor' ),
			'capability_type'     => 'post',
			'exclude_from_search' => true,
			'rewrite'             => array( 'slug' => 'orbita-post' ),
			'menu_icon'          => 'dashicons-marker',
			'menu_position'       => 8,
		)
	);

	register_taxonomy(
		'orbita_category',
		array( 'orbita_post' ),
		array(
			'labels'       => array(
				'name'          => __( 'Categorias' ),
				'singular_name' => __( 'Categoria' ),
			),
			'rewrite'      => array( 'slug' => 'categoria' ),
			'hierarchical' => true,
		)
	);
}
add_action( 'init', 'orbita_setup_post_type' );

/****************** Templates **********************/

/**
 * Load template
 *
 * @param Template $template Template to be loaded.
 */
function load_orbita_template( $template ) {
	global $post;

	if ( 'orbita_post' === $post->post_type && locate_template( array( 'single-orbita_post.php' ) ) !== $template ) {
		return plugin_dir_path( __FILE__ ) . 'single-orbita.php';
	}

	return $template;
}

add_filter( 'single_template', 'load_orbita_template' );

/****************** Shortcodes *********************/

/**
 * Sorting
 *
 * @param Points_A $a Points to be compare.
 * @param Points_B $b Points to be compare.
 */
function orbita_sort_by_points( $a, $b ) {
	return $a['points'] < $b['points'];
}

/**
 * Get vote
 *
 * @param Post_ID $post_id Post ID to verify the vote count.
 */
function orbita_get_vote_html( $post_id ) {
	$users_vote_key   = 'post_users_vote';
	$users_vote_array = get_post_meta( $post_id, $users_vote_key, true );
	$already_voted    = false;
	$additional_class = '';
	$title = "Votar";

	if ( $users_vote_array && array_search( get_current_user_id(), $users_vote_array, true ) !== false ) {
		$already_voted = true;
	}
	if ( is_user_logged_in() && ! $already_voted ) {
		$additional_class = 'orbita-vote-can-vote';
	}
	if ( $already_voted ) {
		$additional_class = 'orbita-vote-already-voted';
		$title = "Você já votou!";
	}

	$html  = '<button title="' . $title . '" class="orbita-vote ' . $additional_class . '" data-post-id="' . $post_id . '">⬆️';
	$html .= '</button>';

	return $html;
}

/**
 * Get Header
 */
function orbita_get_header_html() {
	$html  = '<div class="orbita-header">';
	$html .= '  <a href="/orbita/postar/" class="orbita-post-button">Postar</a>';
	$html .= '  <div>';
	$html .= '      <a href="/orbita">Populares</a>';
	$html .= '      <a href="/orbita/tudo">Links novos</a>';
	$html .= '      <a href="/orbita/guia-de-uso">Guia de uso</a>';
	$html .= '  </div>';
	$html .= '</div>';

	return $html;
}

/**
 * Get post
 *
 * @param Post_ID $post_id Post ID to show the post.
 */
function orbita_get_post_html( $post_id ) {
	global $post;
	$post = get_post( $post_id, OBJECT );
	setup_postdata( $post );

	$external_url = get_post_meta( $post_id, 'external_url', true );
	if ( ! $external_url ) {
		$external_url = get_permalink();
	}
	$regex       = '/manualdousuario.net\/orbita/i';
	$only_domain = preg_match( $regex, $external_url ) ? 'debate' : wp_parse_url( str_replace('www.', '', $external_url), PHP_URL_HOST );
	$count_key   = 'post_like_count';
	$count       = get_post_meta( $post_id, $count_key, true );

	if ( ! $count ) {
		$count = 'nenhum';
	}

	wp_timezone_string( 'America/Sao_Paulo' );
	$human_date = human_time_diff(get_the_time('U'), current_time( 'timestamp' ));

	$votes_text = ($count > 1 && $count != 'nenhum') ? 'votos' : 'voto';

	$html  = '<article class="orbita-post">';
	$html .= orbita_get_vote_html( $post_id );
	$html .= '  <div class="orbita-post-infos">';
	$html .= '    <div class="orbita-post-title">';
	$html .= '          <a href="' . esc_url( $external_url ) . '" rel="ugc" title="' . get_the_title() . '">' . get_the_title() . '</a>';
	$html .= '          <div class="orbita-post-info">';
	$html .= '              <span class="orbita-post-domain">' . $only_domain . '</span>';
	$html .= '          </div>';
	$html .= '          <div class="orbita-post-date">';
	$html .= '              <span data-votes-post-id="' . esc_attr( $post_id ) . '">' . $count . ' </span> ' . $votes_text . ' | por ' . get_the_author_meta( 'display_name', $orbita_post->post_author ) . ' ' . $human_date . ' atrás | <a href=" ' . get_permalink() . '#comments">' . get_comments_number_text( 'sem comentários', '1 comentário', '% comentários' ) . '</a>';
	$html .= '          </div>';
	$html .= '      </div>';
	$html .= '</div>';
	$html .= '</article>';

	return $html;
}

/**
 * Ranking Calculator
 *
 * @param Args    $args Args.
 * @param Comment $comment_points Comment points.
 * @param Vote    $vote_points Vote points.
 */
function orbita_ranking_calculator( $args, $comment_points, $vote_points ) {
	$query       = new WP_Query( $args );
	$posts_array = array();

	if ( ! $query->have_posts() ) {
		return $posts_array;
	}

	while ( $query->have_posts() ) :
		$query->the_post();
		$today     = new DateTime( 'NOW' );
		$post_date = new DateTime( get_the_date( 'Y-m-d H:i:s' ) );

		$difference         = $today->diff( $post_date );
		$difference_days    = $difference->d * 24;
		$difference_hours   = $difference->h;
		$difference_minutes = $difference->i / 60;

		$time_elapsed = $difference_days + $difference_hours + $difference_minutes;

		$points_comments = get_comments_number();
		$points_votes    = get_post_meta( get_the_id(), 'post_like_count', true );
		$invisible_votes = get_post_meta( get_the_id(), 'invisible_votes', true );
		$total_points    = ( (int) $points_comments * (int) $comment_points ) + ( (int) $points_votes * (int) $vote_points ) + ( (int) $invisible_votes * (int) $vote_points );

		if ( $total_points > 0 ) {
			$total_points = $total_points - ( $time_elapsed / 10 );
		}

		$posts_array[] = array(
			'id'     => get_the_id(),
			'points' => $total_points,
		);

	endwhile;

	wp_reset_query();

	return $posts_array;
}

/**
 * Ranking
 *
 * @param Atts    $atts Attributes.
 * @param Content $content Content.
 * @param Tag     $tag Tag.
 */
function orbita_ranking_shortcode( $atts = array(), $content = null, $tag = '' ) {
	$atts = array_change_key_case( (array) $atts, CASE_LOWER );

	$orbita_rank_atts = shortcode_atts(
		array(
			'days'           => '5',
			'vote-points'    => 1,
			'comment-points' => 2,
		),
		$atts,
		$tag
	);

	$args_orbita = array(
		'post_type'      => 'orbita_post',
		'posts_per_page' => -1,
		'date_query'     => array(
			'after' => $orbita_rank_atts['days'] . ' days ago',
		),
	);

	$orbita_posts_array = orbita_ranking_calculator(
		$args_orbita, 
		$orbita_rank_atts['comment-points'],
		$orbita_rank_atts['vote-points']
	);

	$args_blog = array(
		'posts_per_page' => 20,
		'meta_query'     => array(
			array(
				'key'   => 'orbita_featured',
				'value' => '1',
			),
		),
	);

	$blog_posts_array = orbita_ranking_calculator( 
		$args_blog, 
		$orbita_rank_atts['comment-points'], 
		$orbita_rank_atts['vote-points'] 
	);

	$posts_array = array_merge( $orbita_posts_array, $blog_posts_array );

	usort( $posts_array, 'orbita_sort_by_points' );
	$posts_array = array_slice( $posts_array, 0, 30 );

	$html = '<div class="orbita-ranking">';

	$html .= orbita_get_header_html();

	foreach ( $posts_array as $post ) {
		$html .= orbita_get_post_html( $post['id'] );
	}

	$html .= '</div>';

	wp_reset_query();

	return $html;
}

/**
 * Posts
 *
 * @param Atts    $atts Attributes.
 * @param Content $content Content.
 * @param Tag     $tag Tag.
 */
function orbita_posts_shortcode( $atts = array(), $content = null, $tag = '' ) {
	$atts  = array_change_key_case( (array) $atts, CASE_LOWER );
	$paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
	$html  = orbita_get_header_html();

	$orbita_posts_atts = shortcode_atts(
		array(
			'latest' => false,
		),
		$atts,
		$tag
	);

	$args = array(
		'post_type'      => 'orbita_post',
		'posts_per_page' => 30,
		'paged'          => $paged,
	);

	if ( true === $orbita_posts_atts['latest'] ) {
		$args['date_query'] = array(
			'after' => '2 days ago',
		);
	}

	$query = new WP_Query( $args );

	if ( $query->have_posts() ) :
		while ( $query->have_posts() ) :
			$query->the_post();
			$html .= orbita_get_post_html( get_the_id() );
		endwhile;

		$paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;

		$html .= get_previous_posts_link( '&laquo; mais recentes' );
		if ( $paged > 1 ) {
			$html .= '&nbsp;&nbsp;';
		}
		$html .= get_next_posts_link( 'mais antigos &raquo;', $query->max_num_pages );
	endif;

	wp_reset_query();

	return $html;
}

/**
 * Form
 */
function orbita_form_shortcode() {
	if ( isset( $_POST['orbita_nonce'] ) ) {
		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['orbita_nonce'] ) ), 'orbita_nonce' ) ) {
			return;
		}
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! is_user_logged_in() ) {
		$html = 'Para iniciar debates na Órbita, <a href="' . wp_login_url( home_url( '/orbita/postar' ) ) . '">faça login</a> ou <a href="' . wp_registration_url() . '">cadastre-se gratuitamente</a>.';
		return $html;
	}

	if ( $_POST && isset( $_POST['orbita_post_title'] ) ) {

		$already_posted = get_page_by_title( wp_unslash( $_POST['orbita_post_title'] ), OBJECT, 'orbita_post' );

		if ( get_current_user_id() === $already_posted->ID && $already_posted->post_author ) {
			$html = 'Parece que este post <a href="' . home_url( '/?p=' . $already_posted->ID ) . '">já existe</a>.';
			return $html;
		}

		$default_category = get_term_by( 'slug', 'link', 'orbita_category' );

		if ( ! isset( $_POST['orbita_post_content'] ) ) {
			$orbita_post_content = '';
		} else {
			$orbita_post_content = $_POST['orbita_post_content'];
		}

		if ( ! isset( $_POST['orbita_post_url'] ) ) {
			$orbita_post_url = '';
		} else {
			$orbita_post_url = sanitize_text_field( wp_unslash( $_POST['orbita_post_url'] ) );
		}

		$post    = array(
			'post_title'   => wp_unslash( $_POST['orbita_post_title'] ),
			'post_content' => $orbita_post_content,
			'tax_input'    => array(
				'orbita_category' => array( $default_category->term_id ),
			),
			'meta_input'   => array(
				'external_url' => $orbita_post_url,
			),
			'post_status'  => 'publish',
			'post_type'    => 'orbita_post',
		);
		$post_id = wp_insert_post( $post );

		orbita_increase_post_like( $post_id );

		$html = orbita_get_header_html();

		$html .= 'Tudo certo! Agora você pode <a href="' . home_url( '/?p=' . $post_id ) . '">acessar seu post</a>.';

		return $html;
	}

	$html = orbita_get_header_html();

	if ( ! isset( $_GET['t'] ) ) {
		$get_t = '';
	} else {
		$get_t = sanitize_text_field( wp_unslash( $_GET['t'] ) );
	}

	if ( ! isset( $_GET['u'] ) ) {
		$get_u = '';
	} else {
		$get_u = sanitize_text_field( wp_unslash( $_GET['u'] ) );
	}

	$html .= '<div class="orbita-form">';
	$html .= '  <form id="new_post" name="new_post" method="post"  enctype="multipart/form-data">';
	$html .= '      <input type="hidden" name="orbita_nonce" value="' . wp_create_nonce( 'orbita_nonce' ) . '">';
	$html .= '      <div class="orbita-form-control">';
	$html .= '          <label for="orbita_post_title">Título</label>';
	$html .= '          <textarea required type="text" class="orbita-post-title-textarea" id="orbita_post_title" name="orbita_post_title" value="' . $get_t . '" rows="1" placeholder="Prefira títulos em português"></textarea>';
	$html .= '      </div>';
	$html .= '      <div class="orbita-form-control">';
	$html .= '          <p>Deixe o link vazio para iniciar uma discussão (que pode ser uma dúvida, por exemplo). Se você enviar um comentário ele irá aparecer no topo.</p>';
	$html .= '      </div>';
	$html .= '      <div class="orbita-form-control">';
	$html .= '          <label for="orbita_post_url">Link</label>';
	$html .= '          <input type="url" id="orbita_post_url" name="orbita_post_url" placeholder="https://" value="' . $get_u . '">';
	$html .= '      </div>';
	$html .= '      <div class="orbita-form-control">';
	$html .= '          <label for="orbita_post_content">Comentário</label>';
	$html .= '          <textarea rows="5" id="orbita_post_content" name="orbita_post_content"></textarea>';
	$html .= '      </div>';
	$html .= '      <div class="orbita-form-control">';
	$html .= '          <p>Antes de postar, leia nossas <a href="https://manualdousuario.net/doc-comentarios/" target="_blank" rel="noreferrer noopener">dicas e orientações para comentários</a>.</p>';
	$html .= '      </div>';
	$html .= '      <input type="submit" value="Publicar">';
	$html .= '  </form>';
	$html .= '</div>';

	$html .= '<div class="orbita-bookmarklet ctx-atencao">';
	$html .= '  Se preferir, pode usar nosso bookmarklet! Arraste o botão abaixo para a sua barra de favoritos e clique nele quando quiser compartilhar um link.<br>';
	$html .= '  <a onclick="return false" href="javascript:window.location=%22https://manualdousuario.net/orbita/postar?u=%22+encodeURIComponent(document.location)+%22&t=%22+encodeURIComponent(document.title)">postar no Órbita</a>';
	$html .= '</div>';

	return $html;
}

/**
 * Header
 */
function orbita_header_shortcode() {
	$html = orbita_get_header_html();
	return $html;
}

/**
 * Vote
 */
function orbita_vote_shortcode() {
	$html = orbita_get_vote_html( get_the_ID() );
	return $html;
}

/**
 * Shortcodes Init
 */
function orbita_shortcodes_init() {
	add_shortcode( 'orbita-form', 'orbita_form_shortcode' );
	add_shortcode( 'orbita-ranking', 'orbita_ranking_shortcode' );
	add_shortcode( 'orbita-posts', 'orbita_posts_shortcode' );
	add_shortcode( 'orbita-header', 'orbita_header_shortcode' );
	add_shortcode( 'orbita-vote', 'orbita_vote_shortcode' );
}

add_action( 'init', 'orbita_shortcodes_init' );

/****************** Plugin activation and deactivation *********************/

/**
 * Activate plugin
 */
function orbita_activate() {
	orbita_setup_post_type();
	orbita_shortcodes_init();
	flush_rewrite_rules();
}

register_activation_hook( __FILE__, 'orbita_activate' );

/**
 * Deactivate plugin
 */
function orbita_deactivate() {
	unregister_post_type( 'orbita_post' );
	remove_shortcode( 'orbita' );
	flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'orbita_deactivate' );

/****************** AJAX function *********************/

/**
 * Increase Post Vote
 *
 * @param Post_ID $post_id Post ID to increase votes.
 */
function orbita_increase_post_like( $post_id ) {
	$users_vote_key   = 'post_users_vote';
	$users_vote_array = get_post_meta( $post_id, $users_vote_key, true );

	if ( $users_vote_array && array_search( get_current_user_id(), $users_vote_array, true ) !== false ) {
		return false;
	}

	if ( ! $users_vote_array ) {
		$users_vote_array = array( get_current_user_id() );
		delete_post_meta( $post_id, $users_vote_key );
		add_post_meta( $post_id, $users_vote_key, $users_vote_array );
	} else {
		$users_vote_array[] = get_current_user_id();
		update_post_meta( $post_id, $users_vote_key, $users_vote_array );
	}

	$count_key = 'post_like_count';
	$count     = get_post_meta( $post_id, $count_key, true );

	if ( '' === $count ) {
		$count = 1;
		delete_post_meta( $post_id, $count_key );
		add_post_meta( $post_id, $count_key, '1' );
	} else {
		$count++;
		update_post_meta( $post_id, $count_key, $count );
	}

	return $count;
}

/**
 * Update votes
 */
function orbita_update_post_likes() {
	if ( ! get_current_user_id() ) {
		return;
	}
	
	if ( ! $_POST ) {
		return;
	}

	if ( ! isset( $_POST['post_id'] ) ) {
		$update_post_likes_id = '';
	} else {
		$update_post_likes_id = sanitize_text_field( wp_unslash( $_POST['post_id'] ) );
	}

	$count = orbita_increase_post_like( $update_post_likes_id );

	if ( $count ) {
		echo wp_json_encode(
			array(
				'success' => true,
				'count'   => $count,
			)
		);
	} else {
		echo wp_json_encode( array( 'success' => false ) );
	}

	die();
}

add_action('rest_api_init', function() {
	register_rest_route('orbitaApi/v1', '/likes/', array(
		'methods' => 'POST',
		'callback' => 'orbita_update_post_likes'
	));
});
