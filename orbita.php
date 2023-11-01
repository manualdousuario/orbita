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
 * Version:         1.8.4
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
 * Define plugin version constant
 */
define( 'ORBITA_VERSION', '1.8.4' );

/**
 * Enqueue style file
 */
function orbita_enqueue_styles() {
	wp_register_style( 'orbita', plugins_url( '/public/main.css', __FILE__ ), array(), ORBITA_VERSION, 'all' );
}

add_action( 'wp_enqueue_scripts', 'orbita_enqueue_styles' );

/**
 * Enqueue script file
 */
function orbita_enqueue_scripts() {
	wp_register_script( 'orbita', plugins_url( '/public/main.min.js', __FILE__ ), array(), ORBITA_VERSION, true );
	wp_localize_script(
		'orbita',
		'orbitaApi',
		array(
			'restURL'   => rest_url(),
			'restNonce' => wp_create_nonce( 'wp_rest' ),
		)
	);
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
			'has_archive'         => true,
			'supports'            => array( 'title', 'custom-fields', 'author', 'comments', 'editor', 'thumbnail', 'wpcom-markdown' ),
			'capability_type'     => 'orbita',
			'capabilities' => [
				'publish_posts'       => 'publish_orbitas',
				'edit_posts'          => 'edit_orbitas',
				'edit_others_posts'   => 'edit_others_orbitas',
				'delete_posts'        => 'delete_orbitas',
				'delete_others_posts' => 'delete_others_orbitas',
				'read_private_posts'  => 'read_private_orbitas',
				'edit_post'           => 'edit_orbita',
				'delete_post'         => 'delete_orbita',
				'read_post'           => 'read_orbita',
			],
			'exclude_from_search' => false,
			'rewrite'             => array( 'slug' => 'orbita-post' ),
			'menu_icon'           => 'dashicons-marker',
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
			'rewrite'      => array( 'slug' => 'orbita-category' ),
			'hierarchical' => true,
		)
	);
}
add_action( 'init', 'orbita_setup_post_type' );

/**
 * Setup capabilities for user groups
 */
function orbita_setup_administrator_capabilities() {
	$role = get_role('administrator');
	$role->add_cap('publish_orbitas');
	$role->add_cap('edit_orbitas');
	$role->add_cap('edit_others_orbitas');
	$role->add_cap('delete_orbitas');
	$role->add_cap('delete_others_orbitas');
	$role->add_cap('read_private_orbitas');
	$role->add_cap('edit_orbita');
	$role->add_cap('delete_orbita');
	$role->add_cap('read_orbita');
}
add_action('admin_init', 'orbita_setup_administrator_capabilities');

function orbita_setup_subscriber_capabilities() {
	$role = get_role('subscriber');
	$role->add_cap('publish_orbitas');
}
add_action('admin_init', 'orbita_setup_subscriber_capabilities');

/****************** Third Party Support *********************/

/**
 * The SEO Framework
 */
include_once ABSPATH . 'wp-admin/includes/plugin.php';
if( is_plugin_active( 'autodescription/autodescription.php' ) ) {

	function orbita_tsf_default_ogimage( $details )
	{
		global $post;

		if( is_single() ) {
			$post_type = get_post_type();

			if( $post_type == 'orbita_post' ) {
				$image_id  = get_post_thumbnail_id( $post->ID );
				if ( empty( $image_id ) ) {
					if( file_exists( plugin_dir_path(__FILE__) . 'assets/ogimage.png' ) ) {
						$width = 1280;
						$height = 720;
					
						foreach($details as &$detail) {
							$detail['url'] = plugin_dir_url(__FILE__) . 'assets/ogimage.png';
							$detail['width'] = $width;
							$detail['height'] = $height;
						}
					}
				}
			}
		}
		
		return $details;
	}
	add_filter('the_seo_framework_image_details', 'orbita_tsf_default_ogimage', 10, 2);
}

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
	$title            = 'Votar';

	if ( $users_vote_array && array_search( get_current_user_id(), $users_vote_array, true ) !== false ) {
		$already_voted = true;
	}
	if ( is_user_logged_in() && ! $already_voted ) {
		$additional_class = 'orbita-vote-can-vote';
	}
	if ( $already_voted ) {
		$additional_class = 'orbita-vote-already-voted';
		$title            = 'Você já votou!';
	}

	$html  = '<button title="' . $title . '" class="orbita-vote-button ' . $additional_class . '" data-post-id="' . $post_id . '">';
	$html .= '    <img src="' . plugin_dir_url(__FILE__) . 'assets/vote.svg" alt="Votar" width="32" height="32" />';
	$html .= '</button>';

	return $html;
}

/**
 * Get Header
 */
function orbita_get_header_html() {
	wp_enqueue_style( 'orbita' );
	wp_enqueue_script( 'orbita' );

	$html  = '<div class="orbita-header">';
	$html .= '  <a href="/orbita/postar/" class="orbita-post-button">Postar</a>';
	$html .= '  <div>';
	$html .= '      <select onchange="this.options[this.selectedIndex].value && (window.location = this.options[this.selectedIndex].value);">';
	$html .= '          <option value="/orbita">Populares</option>';
	$html .= '          <option value="/orbita/tudo">Tudo</option>';
	$html .= '          <option value="/guia-de-uso">Guia de Uso</option>';
	$html .= '      </select>';
	$html .= '  </div>';
	$html .= '  <div>';
	$html .= '      <a href="https://t.me/orbitafeed" class="telegram" alt="Canal no Telegram"><img src="' . plugin_dir_url(__FILE__) . 'assets/telegram.svg" width="32" height="32" /></a>';
	$html .= '      <a href="/feed/?post_type=orbita_post" class="rss" alt="Feed RSS"><img src="' . plugin_dir_url(__FILE__) . 'assets/rss.svg" width="32" height="32" /></a>';
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
	$only_domain = strpos($external_url, wp_parse_url( str_replace( 'www.', '', get_bloginfo('url') ), PHP_URL_HOST ) . '/orbita') !== false ? '' : '<span class="domain">' . wp_parse_url( str_replace( 'www.', '', $external_url ), PHP_URL_HOST ) . '</span>';
	$count       = get_post_meta( $post_id, 'post_like_count', true );
	if ( ! $count ) {
		$count = '0';
	}

	wp_timezone_string( 'America/Sao_Paulo' );
	$human_date = human_time_diff( get_the_time( 'U' ), current_time( 'timestamp' ) );

	$post_author_id = get_post_field( 'post_author', $post_id );
	if ( get_userdata( $post_author_id ) == false ) {
		return;
	}

	$separator = '?';
	if(strpos($external_url, '?') !== false) {
		$separator = '&';
	}
	$html  = '<li>';
	$html .= '    <div class="vote">';
	$html .=          orbita_get_vote_html( $post_id );
	$html .= '        <div class="count" data-votes-post-id="' . esc_attr( $post_id ) . '">' . $count . ' </div>';
	$html .= '    </div>';
	$html .= '    <div class="meta">';
	$html .= '        <div class="title">';
	$html .= '            <div class="link">';
	$html .= '                <a href="' . esc_url( $external_url ) . $separator . 'utm_source=ManualdoUsuarioNet&utm_medium=Orbita" rel="ugc" title="' . get_the_title() . '">' . get_the_title() . '</a>';
	$html .= '            </div>';
	$html .=              orbita_link_options( $external_url, get_the_title() );
	$html .=              $only_domain;
	$html .= '        </div>';
	$html .= '        <div class="data">';
	$html .=              get_the_author_meta( 'nickname', $post_author_id ) . ' · ' . $human_date;
	$html .= '            <span class="comments">· <a href=" ' . get_permalink() . '"> ' . get_comments_number_text( 'sem comentários', '1 comentário', '% comentários' ) . '</a></span>';
	$html .= '        </div>';
	$html .= '    </div>';
	$html .= '</li>';

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
			$total_points = $total_points - ( $time_elapsed / 3 );
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
			'limit'          => 20,
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
		'post__not_in'   => get_option( 'sticky_posts' ),
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
		'post__not_in'  => get_option( 'sticky_posts' ),
	);

	$blog_posts_array = orbita_ranking_calculator(
		$args_blog,
		$orbita_rank_atts['comment-points'],
		$orbita_rank_atts['vote-points']
	);

	$posts_array = array_merge( $orbita_posts_array, $blog_posts_array );

	usort( $posts_array, 'orbita_sort_by_points' );
	$posts_array = array_slice( $posts_array, 0, $orbita_rank_atts['limit'] );

	$html = '<div class="orbita-ranking">';
	$html .= orbita_get_header_html();
	$html .= '<ol class="orbita-list">';

	foreach ( $posts_array as $post ) {
		$html .= orbita_get_post_html( $post['id'] );
	}

	$html .= '</ol>';

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
		'posts_per_page' => 10,
		'paged'          => $paged,
		'post__not_in'   => get_option( 'sticky_posts' ),
	);

	if ( true === $orbita_posts_atts['latest'] ) {
		$args['date_query'] = array(
			'after' => '2 days ago',
		);
	}

	$query = new WP_Query( $args );

	if ( $query->have_posts() ) :
		$html .= '<ul class="orbita-list">';

		while ( $query->have_posts() ) :
			$query->the_post();
			$html .= orbita_get_post_html( get_the_id() );
		endwhile;

		$html .= '</ul>';

		$paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;

		$html .= '<nav class="navigation posts-navigation orbita-navigation" aria-label="Posts"><div class="nav-links">';
		$html .= '<h2 class="screen-reader-text">Navegação por posts</h2>';
		$html .= '<div class="nav-previous">'. get_previous_posts_link( '&laquo; Tópicos mais recentes' ) .'</div>';
		$html .= '<div class="nav-next">'. get_next_posts_link( 'Tópicos mais antigos &raquo;', $query->max_num_pages ) .'</div>';
		$html .= '</div></nav>';
	endif;

	wp_reset_query();

	return $html;
}

/**
 * Link options
 *
 * @param URL    $url Content.
 * @param Title    $title Content.
 */
function orbita_link_options( $url = '', $title = '' ) {
	$html = null;
	$options = [];

	if( $url ) {
		$url = esc_url( $url );
		$publishers = [
			[
				"url" => "ft.com/",
				"paywall" => "https://leiaisso.net/"
			], 
			[
				"url" => "bloomberg.com/",
				"paywall" => "https://archive.ph/submit/?url="
			], 
			[
				"url" => "folha.uol.com.br/",
				"paywall" => "https://leiaisso.net/"
			], 
			[
				"url" => "uol.com.br/",
				"paywall" => "https://leiaisso.net/"
			], 
			[
				"url" => "oglobo.globo.com/",
				"paywall" => "https://leiaisso.net/"
			], 
			[
				"url" => "estadao.com.br/",
				"paywall" => "https://archive.ph/submit/?url="
			], 
			[
				"url" => "nytimes.com/",
				"paywall" => "https://archive.ph/submit/?url="
			], 
			[
				"url" => "washingtonpost.com/",
				"paywall" => "https://leiaisso.net/"
			], 
			[
				"url" => "wsj.com/",
				"paywall" => "https://archive.ph/submit/?url="
			], 
			[
				"url" => "medium.com/",
				"paywall" => "https://scribe.rip/"
			], 
			[
				"url" => "veja.abril.com.br/",
				"paywall" => "https://leiaisso.net/"
			], 
			[
				"url" => "exame.com/",
				"paywall" => "https://leiaisso.net/"
			], 
			[
				"url" => "super.abril.com.br/",
				"paywall" => "https://leiaisso.net/"
			], 
			[
				"url" => "valor.globo.com/",
				"paywall" => "https://leiaisso.net/"
			], 
			[
				"url" => "newyorker.com/",
				"paywall" => "https://archive.ph/submit/?url="
			], 
			[
				"url" => "theatlantic.com/",
				"paywall" => "https://archive.ph/submit/?url="
			], 
			[
				"url" => "technologyreview.com/",
				"paywall" => "https://archive.ph/submit/?url="
			], 
			[
				"url" => "wired.com/",
				"paywall" => "https://leiaisso.net/"
			] 
		]; 

		foreach ( $publishers as $publisher ) {
			if ( preg_match("~" . preg_quote( $publisher['url'], "~" ) . "~i", $url ) ) {
				$options['paywall'] = $publisher['paywall'] . $url;
			}
		}
	}

	if( $title && isset( $options['paywall'] ) ) {
		$tags = [ 'es', 'en' ];
		foreach ( $tags as $tag ){
		   if ( str_contains( $title, '[' . $tag . ']' )){
				$options['translate'] = 'https://translate.google.com/translate?sl=' . $tag . '&tl=pt&hl=pt-BR&u=' . $options['paywall'];
		   }
		}
	}

	if ( $options ) {
		$html = '<span class="options">[';
		if( isset( $options['paywall'] ) ) {
			$html .= '<a href="' . $options['paywall'] . '">sem paywall</a>';
		}
		if( isset( $options['translate'] ) ) {
			$html .= ', <a href="' . $options['translate'] . '">traduzir</a>';
		}
		$html .= ']</span>';
	}

	return $html;
}

/**
 * My Posts
 */
function orbita_my_posts_shortcode() {
	$user_id = get_current_user_id();
	$html  = orbita_get_header_html();

	if ($user_id) {
		$paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
		$html  = orbita_get_header_html();

		$args = array(
			'post_type'      => 'orbita_post',
			'posts_per_page' => 10,
			'paged'          => $paged,
			'author__in'     => $user_id,
			'post__not_in'   => get_option( 'sticky_posts' ),
		);

		$query = new WP_Query( $args );

		if ( $query->have_posts() ) :
			$html .= '<ul class="orbita-list">';

			while ( $query->have_posts() ) :
				$query->the_post();
				$html .= orbita_get_post_html( get_the_id() );
			endwhile;

			$html .= '</ul>';

			$paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;

			$html .= '<nav class="navigation posts-navigation orbita-navigation" aria-label="Posts"><div class="nav-links">';
			$html .= '<h2 class="screen-reader-text">Navegação por posts</h2>';
			$html .= '<div class="nav-previous">'. get_previous_posts_link( '&laquo; Tópicos mais recentes' ) .'</div>';
			$html .= '<div class="nav-next">'. get_next_posts_link( 'Tópicos mais antigos &raquo;', $query->max_num_pages ) .'</div>';
			$html .= '</div></nav>';
		else :
			$html .= 'Você ainda não abriu nenhum tópico.';
		endif;

		wp_reset_query();
	} else {
		$html .= 'Para visualizar seus tópicos, <a href="' . wp_login_url( home_url( '/orbita/postar' ) ) . '">faça login</a>.';
	}

	return $html;
}

/**
 * My Comments
 */
function orbita_my_comments_shortcode() {
	$user_id = get_current_user_id();
	$html  = orbita_get_header_html();

	if ($user_id) {
		$paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;

		$args = array(
			'user_id' => $user_id,
			'number' => 10,
			'paged' => $paged,
			'no_found_rows' => false
		);
		$query = new WP_Comment_Query;
		$comments = $query->query( $args );

		if (empty($comments)) {
			$html .= 'Você ainda não fez nenhum comentário.';
		} else {

			$html .= '<ul style="list-style: none; margin-left: 0">';

			foreach ($comments as $comment) {
				$post_id = $comment->comment_post_ID;

				$html .= '<li class="orbita-comment">';
				$html .= '          Em <a href="' . get_permalink($post_id) . '#comment-' . $comment->comment_ID . '" rel="ugc" title="' . get_the_title($post_id) . '">' . get_the_title($post_id) . '</a> comentou:';
				$html .= '          <div class="orbita-comment-content">' . nl2br(strip_tags($comment->comment_content)) . '</div>';
				$html .= '</li>';
			}

			$html .= '</ul>';

			$html .= '<nav class="navigation posts-navigation orbita-navigation" aria-label="Posts"><div class="nav-links">';
			$html .= '<h2 class="screen-reader-text">Navegação por posts</h2>';
			$html .= '<div class="nav-previous">'. get_previous_posts_link( '&laquo; Tópicos mais recentes' ) .'</div>';
			$html .= '<div class="nav-next">'. get_next_posts_link( 'Tópicos mais antigos &raquo;', $query->max_num_pages ) .'</div>';
			$html .= '</div></nav>';
		}
	} else {
		$html .= 'Para visualizar seus comentários, <a href="' . wp_login_url( home_url( '/orbita/postar' ) ) . '">faça login</a>.';
	}

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
		$html = 'Para postar links ou iniciar conversas na Órbita, <a href="' . wp_login_url( home_url( '/orbita/postar' ) ) . '">faça login</a> ou <a href="' . wp_registration_url() . '">cadastre-se gratuitamente</a>.';
		return $html;
	}

	if ( isset($_REQUEST['orbita_error']) ) {
		$orbita_error = $_REQUEST['orbita_error'];

		if( $orbita_error == 'duplicated' ) {
			$orbita_post_id = $_REQUEST['orbita_post_id'];

			if( isset($orbita_post_id) ) {
				$html = 'Parece que este post <a href="' . get_permalink($orbita_post_id) . '">já existe</a>.';
			} else {
				$html = 'Parece que este post já existe.';
			}
		}
		if( $orbita_error == 'user_logged' ) {
			$html = 'Para postar links ou iniciar conversas na Órbita, <a href="' . wp_login_url( home_url( '/orbita/postar' ) ) . '">faça login</a> ou <a href="' . wp_registration_url() . '">cadastre-se gratuitamente</a>.';
		}
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
	$html .= '          <p>Antes de postar, leia nossas <a href="https://manualdousuario.net/doc-comentarios/">dicas e orientações para comentários</a>.</p>';
	$html .= '      </div>';
	$html .= '      <input type="submit" value="Publicar">';
	$html .= '  </form>';
	$html .= '</div>';
	$html .= '<div class="orbita-bookmarklet ctx-atencao">';
	$html .= '  <p>Se preferir, pode usar nosso bookmarklet! Arraste o botão abaixo para a sua barra de favoritos e clique nele quando quiser compartilhar um link.</p>';
	$html .= '  <p><a onclick="return false" href="javascript:window.location=%22https://manualdousuario.net/orbita/postar?u=%22+encodeURIComponent(document.location)+%22&t=%22+encodeURIComponent(document.title)">Postar no Órbita</a></p>';
	$html .= '</div>';

	return $html;
}

add_action('wp_loaded', 'orbita_form_post');
function orbita_form_post() {
	if ( $_POST && isset( $_POST['orbita_post_title'] ) ) {

		$already_posted = new WP_Query(
			[
				'post_type'              => 'orbita_post',
				'title'                  => $_POST['orbita_post_title'],
				'post_status'            => 'publish',
				'author__in'             => get_current_user_id(),
				'posts_per_page'         => 1,
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false
			]
		);
		if( ! empty($already_posted->posts) ) {
			wp_safe_redirect( add_query_arg( array( 'orbita_error' => 'duplicated', 'orbita_post_id' => $already_posted->posts[0]->ID ), '/orbita/postar' ) );
			die;
		}

		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( add_query_arg( array( 'orbita_error' => 'user_logged' ), '/orbita/postar' ) );
			die;
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

		$post_title = wp_unslash( $_POST['orbita_post_title'] );
		$post_title = preg_replace_callback('/\[(EN|en|ES|es)\]/', function($match){
			return '[' . strtolower($match[1]) . '] ';
		}, $post_title);
		$post_title = rtrim($post_title, '!@#%^&*_+\-=|\\\:;<>,.\/~');

		$post = array(
			'post_title'   => $post_title,
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
		orbita_save_ogimage( $post_id );
		
		wp_redirect(get_permalink($post_id));
		die;
	}
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
 * Save og:image if have external URL
 *
 * @param Post_ID $post_id Post ID to attachment media in post.
 */
function orbita_save_ogimage( $post_id ) {
    $external_url = get_post_meta( $post_id, 'external_url', true );
    if ( empty( $external_url ) ) {
        return;
    }

	// e_001 = error downloading source code from external url
	// e_002 = og:image not found after request
	// e_003 = error downloading image received in og:image
	// e_004 = did not recognize a mime type as an image extension

	$remote_request = wp_safe_remote_request( $external_url );
	if ( is_wp_error( $remote_request ) ) {
		update_post_meta( $post_id, 'external_url_ogimage', 'e_001' );
	} else {
		$html = wp_remote_retrieve_body( $remote_request );

		$doc = new DOMDocument();
		@$doc->loadHTML($html);
		$xpath = new DOMXPath($doc);

		$og_image_url = '';
		$query_ogimages = $xpath->query("//meta[@property='og:image']//@content");
		
		foreach( $query_ogimages as $query ) {
			$og_image_url = $query->nodeValue;
		}

		$og_image_alt = '';
		$query_ogimages = $xpath->query("//meta[@property='og:image:alt']//@content");
		foreach( $query_ogimages as $query ) {
			$og_image_alt = $query->nodeValue;
		}

		if( empty($og_image_url) ) {
			update_post_meta( $post_id, 'external_url_ogimage', 'e_002' );
		} else {
			$remote_request = wp_safe_remote_request( $og_image_url );
			if ( is_wp_error( $remote_request ) ) {
				update_post_meta( $post_id, 'external_url_ogimage', 'e_003' );
			} else {
				$image_body = wp_remote_retrieve_body( $remote_request );
				$image_headers = wp_remote_retrieve_headers( $remote_request );

				if( isset( $image_headers['content-type'] ) ) {
					$content_type = $image_headers['content-type'];

					$extension = null;
					switch ( $content_type ) {
						case 'image/png':
							$extension = 'png';
							break;
						case 'image/avif':
							$extension = 'avif';
							break;
						case 'image/gif':
							$extension = 'gif';
							break;
						case 'image/jpg':
							$extension = 'jpg';
							break;
						case 'image/jpeg':
							$extension = 'jpg';
							break;
						case 'image/svg+xml':
							$extension = 'svg';
							break;
						case 'image/webp':
							$extension = 'webp';
							break;
					}

					if( $extension ) {
						$wp_upload_dir = wp_upload_dir();
						$upload_dir = $wp_upload_dir['basedir'] . '/orbita' . $wp_upload_dir['subdir'];
						wp_mkdir_p( $upload_dir );
			
						// Filename format: postid_timestamp.extension
						$filename = wp_unique_filename($upload_dir, $post_id . '_' . time() . '.' . $extension);
						$upload_file = $upload_dir . '/' . $filename;
						file_put_contents( $upload_file, $image_body );
		
						$post_mime_type = wp_check_filetype( basename( $upload_file ), null );
		
						$attachment = array(
							'guid'           => $upload_file,
							'post_mime_type' => $post_mime_type['type'],
							'post_title'     => sanitize_file_name( $filename ),
							'post_content'   => $og_image_alt,
							'post_status'    => 'inherit'
						);
						$attachment_id = wp_insert_attachment( $attachment, $upload_file, $post_id );

						require_once( ABSPATH . 'wp-admin/includes/image.php' );
		
						$attach_data = wp_generate_attachment_metadata( $attachment_id, $upload_file );
						wp_update_attachment_metadata( $attachment_id, $attach_data );

						set_post_thumbnail( $post_id, $attachment_id );
					} else {
						update_post_meta( $post_id, 'external_url_ogimage', 'e_004' );
					}

				}
			}
		}
	}
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
	add_shortcode( 'orbita-my-comments', 'orbita_my_comments_shortcode' );
	add_shortcode( 'orbita-my-posts', 'orbita_my_posts_shortcode' );
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

/**
 * REST API
 */
add_action(
	'rest_api_init',
	function() {
		register_rest_route(
			'orbitaApi/v1',
			'/likes/',
			array(
				'methods'  => 'POST',
				'callback' => 'orbita_update_post_likes',
			)
		);
	}
);

/****************** WP_CLI *********************/

if (defined('WP_CLI') && WP_CLI) {
	class Orbita_WP_CLI extends WP_CLI_Command {

        public function ogimage( $args, $assoc_args ) {
            list( $action ) = $args;

            if ( 'update' === $action ) {

				/**
				 * Execute the "orbita ogimage update" command.
				 *
				 * ## OPTIONS
				 *
				 * [--posts_per_page=<posts_per_page>]
				 * : Number of posts to process at a time. Default is 10.
				 */
				$posts_per_page = isset( $assoc_args['posts_per_page'] ) ? intval( $assoc_args['posts_per_page'] ) : 10;
			
				$meta_query = [
					'relation'    => 'AND',
					[
						'key'     => 'external_url',
						'value'   => '',
						'compare' => '!='
					],
					[
						'key'     => 'external_url_ogimage',
						'compare' => 'NOT EXISTS'
					],
					[
						'key'     => '_thumbnail_id',
						'compare' => 'NOT EXISTS'
					]
				];
	
				$query = new WP_Query([
					'posts_per_page' => $posts_per_page,
					'post_type'      => 'orbita_post',
					'post_status'    => 'published',
					'meta_query'     => $meta_query
				]);
	
				if ($query->have_posts()) {
					while ($query->have_posts()) {
						$query->the_post();
						$post_id = get_the_ID();
						orbita_save_ogimage( $post_id );

						WP_CLI::success( '[' . $post_id . '] post thumbnail atualizado!' );
					}
					wp_reset_postdata();
				} else {
					WP_CLI::success( 'Não foi encontrado nenhum post com external_url e post thumbnail vazio.' );
				}

            } elseif ( 'search' === $action ) {
                
				/**
				 * Execute the "orbita ogimage search" command.
				 *
				 * ## OPTIONS
				 *
				 * [--posts_per_page=<posts_per_page>]
				 * : Number of posts to process at a time. Default is -1.
				 * 
				 * [--external_url_ogimage=<e_>]
				 * : filter by external_url_ogimage code
				 */
				$posts_per_page = isset( $assoc_args['posts_per_page'] ) ? intval( $assoc_args['posts_per_page'] ) : -1;
				$external_url_ogimage = isset( $assoc_args['external_url_ogimage'] ) ? $assoc_args['external_url_ogimage'] : null;
				
				if(	$external_url_ogimage == null ) {
					WP_CLI::error( 'Parametro --external_url_ogimage é obrigatório!' );
				}

				$meta_query = [
					'relation'    => 'AND',
					[
						'key'     => 'external_url',
						'value'   => '',
						'compare' => '!='
					],
					[
						'key'     => 'external_url_ogimage',
						'value'   => $external_url_ogimage,
						'compare' => '=='
					]
				];

				$query = new WP_Query([
					'posts_per_page' => $posts_per_page,
					'post_type'      => 'orbita_post',
					'post_status'    => 'published',
					'meta_query'     => $meta_query
				]);

				if ($query->have_posts()) {
					while ($query->have_posts()) {
						$query->the_post();
						$post_id = get_the_ID();

						WP_CLI::success( '[' . $post_id . '] encontrado!' );
					}
					wp_reset_postdata();
				} else {
					WP_CLI::success( 'Não foi encontrado nenhum post external_url_ogimage == ' . $external_url_ogimage );
				}

            } else {
                WP_CLI::error( 'Use "update" ou "search".' );
            }
        }
	}

    WP_CLI::add_command('orbita', 'Orbita_WP_CLI');
}
