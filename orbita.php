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
 * Version:         1.0.0
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

	if ( $users_vote_array && array_search( get_current_user_id(), $users_vote_array, true ) !== false ) {
		$already_voted = true;
	}
	if ( is_user_logged_in() && ! $already_voted ) {
		$additional_class = 'orbita-vote-can-vote';
	}
	if ( $already_voted ) {
		$additional_class = 'orbita-vote-already-voted';
	}

	$html  = '<button title="Votar" class="orbita-vote ' . $additional_class . '" data-url="' . admin_url( 'admin-ajax.php' ) . '" data-post-id="' . $post_id . '">';
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
	$html .= '      <a href="/orbita">Capa</a>';
	$html .= '      <a href="/orbita/guia-de-uso">Guia de uso</a>';
	$html .= '      <a href="/orbita/tudo">Arquivo</a>';
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
	$only_domain = preg_match( $regex, $external_url ) ? 'debate' : wp_parse_url( $external_url, PHP_URL_HOST );
	$count_key   = 'post_like_count';
	$count       = get_post_meta( $post_id, $count_key, true );

	if ( ! $count ) {
		$count = 'nenhum';
	}

	wp_timezone_string( 'America/Sao_Paulo' );
	$human_date = human_time_diff( strtotime( 'now' ), strtotime( get_the_date( 'm/d/Y H:i' ) ) );

	$votes_text = $count > 1 ? 'votos' : 'voto';

	$html  = '<article class="orbita-post">';
	$html .= orbita_get_vote_html( $post_id );
	$html .= '  <div class="orbita-post-infos">';
	$html .= '    <div class="orbita-post-title">';
	$html .= '          <a href="' . esc_url( $external_url ) . '" rel="ugc" title="' . get_the_title() . '">' . get_the_title() . '</a>';
	$html .= '          <div class="orbita-post-info">';
	$html .= '              <span class="orbita-post-domain">' . esc_url( $only_domain ) . '</span>';
	$html .= '          </div>';
	$html .= '          <div class="orbita-post-date">';
	$html .= '              <span data-votes-post-id="' . esc_attr( $post_id ) . '">' . $count . ' </span> ' . $votes_text . ' | por ' . get_the_author_meta( 'display_name', $orbita_post->post_author ) . ' ' . $human_date . ' atrás | <a href=" ' . get_permalink() . '">' . get_comments_number_text( 'sem comentários', '1 comentário', '% comentários' ) . '</a> | <button data-url="' . admin_url( 'admin-ajax.php' ) . '" data-post-id="' . esc_attr( $post_id ) . '" class="orbita-report-link">Reportar</button>';
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
			$orbita_post_content = sanitize_text_field( wp_unslash( $_POST['orbita_post_content'] ) );
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

		$admin_email = get_option( 'admin_email' );
		$headers     = array( 'Content-Type: text/html; charset=UTF-8' );
		$admin_url   = get_admin_url();
		$edit_url    = $admin_url . 'post.php?post=' . $post_id . '&action=edit';

		$send_email = wp_mail(
			$admin_email,
			"[Órbita] Novo post: '" . wp_unslash( $_POST['orbita_post_title']),
			'Link para editar: <a href="' . esc_url( $edit_url ) . '">Clique aqui para editar o post</a>',
			$headers
		);

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
	$html .= '          <textarea required id="orbita_post_title" name="orbita_post_title" value="' . $get_t . '" placeholder="Prefira títulos em português" rows="1" onkeyup="const charactersPerLine = this.offsetWidth / 10;this.rows = Math.round(this.value.length / charactersPerLine) + 1;" />';
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

/****************** Send emails for each comment made *********************/

/**
 * Comment Post
 *
 * @param Comment $comment_id Comment ID of a post.
 */
function orbita_comment_post( $comment_id ) {
	$comment     = get_comment( $comment_id );
	$admin_email = get_option( 'admin_email' );
	$headers     = array( 'Content-Type: text/html; charset=UTF-8' );
	$post_title  = get_the_title( $comment->comment_post_ID );
	$edit_url    = get_admin_url() . 'post.php?post=' . $comment->comment_post_ID . '&action=edit';

	$send_email = wp_mail(
		$admin_email,
		"[Manual] Novo comentário em '" . $post_title . "'",
		$comment->comment_content . '<br>' .
		'Comentado por: ' . $comment->comment_author . ' <' . $comment->comment_author_email . '><br><br>' .
		'Link para editar: <a href="' . $edit_url . '">Clique aqui para editar o comentário</a>',
		$headers
	);
};

add_action( 'comment_post', 'orbita_comment_post', 10, 3 );

/****************** Reporting comments and posts *********************/

/**
 * Comment Depth
 *
 * @param Comment_Depth $my_comment_id Comment ID of a comment.
 */
function orbita_get_comment_depth( $my_comment_id ) {
	$depth_level = 0;
	while ( $my_comment_id > 0 ) {
		$my_comment    = get_comment( $my_comment_id );
		$my_comment_id = $my_comment->comment_parent;
		$depth_level++;
	}
	return $depth_level;
}

/**
 * Report Button
 *
 * @param Comment_Reply $comment_reply_link Reply link.
 * @param Args          $args Args.
 * @param Comment       $comment Comment.
 * @param Post          $post Post.
 */
function orbita_add_report_button_to_reply_link( $comment_reply_link, $args, $comment, $post ) {
	$comment_id = $comment->comment_ID;
	$class      = 'orbita-report-link';

	$pattern            = '#(<a.+class=.+comment-(reply|login)-l(i|o)(.*)[^>]+>)(.+)(</a>)#msiU';
	$replacement        = '$0 <button data-url="' . admin_url( 'admin-ajax.php' ) . '" data-comment-id="' . $comment_id . '" class="' . $class . '">Reportar</button>';
	$comment_reply_link = preg_replace( $pattern, $replacement, $comment_reply_link );

	return $comment_reply_link;
}

/**
 * Report Button to Content
 *
 * @param Comment_Reply $comment_content Content of comment.
 * @param Comment       $comment Comment.
 * @param Args          $args Args.
 */
function orbita_add_report_button_to_content( $comment_content, $comment, $args ) {
	$depth                 = orbita_get_comment_depth( $comment->comment_ID );
	$thread_comments_depth = get_option( 'thread_comments_depth' );

	if ( $depth < $thread_comments_depth ) {
		return $comment_content;
	}

	$comment_id = $comment->comment_ID;
	$class      = 'orbita-report-link';

	$comment_content .= '<br /><br /><button data-url="' . admin_url( 'admin-ajax.php' ) . '" data-comment-id="' . $comment_id . '" class="' . $class . '">Reportar</button>';

	return $comment_content;
}

add_filter( 'comment_reply_link', 'orbita_add_report_button_to_reply_link', 10, 4 );
add_filter( 'get_comment_text', 'orbita_add_report_button_to_content', 10, 4 );

/**
 * Report Comment or Post
 */
function orbita_report_comment_or_post() {
	if ( isset( $_POST['orbita_nonce'] ) ) {
		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['orbita_nonce'] ) ), 'orbita_nonce' ) ) {
			return;
		}
	}

	if ( ! $_POST ) {
		return;
	}

	if ( ! isset( $_POST['post_id'] ) ) {
		$report_post_id = '';
	} else {
		$report_post_id = sanitize_text_field( wp_unslash( $_POST['post_id'] ) );
	}

	if ( ! isset( $_POST['comment_id'] ) ) {
		$comment_post_id = '';
	} else {
		$comment_post_id = sanitize_text_field( wp_unslash( $_POST['comment_id'] ) );
	}

	$admin_url          = get_admin_url();
	$edit_url           = '';
	$report_type        = '';
	$report_id          = '';
	$report_description = '';
	$current_user       = wp_get_current_user();
	$user               = $current_user->user_login ? $current_user->user_login : 'um Usuário anônimo';
	$post               = get_post( $report_post_id );
	$comment            = get_comment( $comment_post_id );

	if ( isset( $post ) ) {
		$edit_url           = $admin_url . 'post.php?post=' . sanitize_text_field( wp_unslash( $_POST['post_id'] ) ) . '&action=edit';
		$report_id          = sanitize_text_field( wp_unslash( $_POST['post_id'] ) );
		$report_type        = 'Post';
		$report_description = "O post '" . $post->post_title . "' no Órbita foi denunciado por '" . $user . "'.";
	} elseif ( isset( $comment ) ) {
		$edit_url           = $admin_url . 'comment.php?action=editcomment&c=' . sanitize_text_field( wp_unslash( $_POST['comment_id'] ) );
		$post               = get_post( $comment->comment_post_ID );
		$report_id          = sanitize_text_field( wp_unslash( $_POST['comment_id'] ) );
		$report_type        = 'Comentário';
		$report_description = "Um comentário feito em '" . $post->post_title . "' foi denunciado por '" . $user . "'";
	}

	$admin_email = get_option( 'admin_email' );
	$headers     = array( 'Content-Type: text/html; charset=UTF-8' );

	$send_email = wp_mail(
		$admin_email,
		'[Órbita] Nova denúncia de ' . $report_type . ' #' . $report_id,
		$report_description . '<br><br>' .
		'Link para editar: <a href="' . $edit_url . '">Clique aqui para editar o ' . $report_type . '</a>',
		$headers
	);

	if ( $send_email ) {
		echo wp_json_encode( array( 'success' => true ) );
	} else {
		echo wp_json_encode( array( 'success' => false ) );
	}

	wp_die();
}

add_action( 'wp_ajax_nopriv_orbita_report', 'orbita_report_comment_or_post' );
add_action( 'wp_ajax_orbita_report', 'orbita_report_comment_or_post' );

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
	if ( ! is_user_logged_in() ) {
		return false;
	}

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
	if ( isset( $_POST['orbita_nonce'] ) ) {
		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['orbita_nonce'] ) ), 'orbita_nonce' ) ) {
			return;
		}
	}

	if ( ! is_user_logged_in() ) {
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

	wp_die();
}

add_action( 'wp_ajax_nopriv_orbita_update_post_likes', 'orbita_update_post_likes' );
add_action( 'wp_ajax_orbita_update_post_likes', 'orbita_update_post_likes' );
