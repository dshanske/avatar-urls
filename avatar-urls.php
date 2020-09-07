<?php
/**
 * Plugin Name: Avatar URL
 * Plugin URI: https://github.com/dshanske/avatar-urls
 * Description: Creates an Avatar URL redirect that will not change if you change your image.
 * Author: David Shanske
 * Author URI: https://david.shanske.com
 * Text Domain: avatar-urls
 * Version: 0.01
 */


class Avatar_URL_Plugin {

	public function __construct() {
		add_filter( 'query_vars', array( $this, 'query_var' ) );
		add_action( 'parse_query', array( $this, 'serve_avatar' ) );
		add_action( 'init', array( $this, 'init' ) );
		add_filter( 'pre_get_avatar_data', array( $this, 'pre_get_avatar_data' ), 10, 2 );
	}

	public function init() {
		add_rewrite_rule(
			'avatar/([a-z]+)/?$',
			'index.php?avatar=$matches[1]',
			'top'
		);
	}

	public function query_var( $vars ) {
		$vars[] = 'avatar';
		return $vars;
	}

	public function pre_get_avatar_data( $args, $id_or_email ) {
		global $wp_rewrite;

		// Never bother redirecting the URL on the backend.
		if ( is_admin() ) {
			return $args;
		}

		if ( ! array_key_exists( 'redirect_bypass', $args ) ) {
			$user = null;
			if ( is_numeric( $id_or_email ) ) {
				$id_or_email = get_user_by( 'id', $id_or_email );
			}
			if ( $id_or_email instanceof WP_User ) {
				// check to see if we are using rewrite rules
				$rewrite = $wp_rewrite->wp_rewrite_rules();
				if ( empty( $rewrite ) ) {
					$args['url'] = add_query_arg(
						array(
							'avatar' => $id_or_email->data->user_nicename,
							's'      => $args['size'],
						),
						site_url()
					);
				} else {

					$path        = sprintf( '/avatar/%1$s', $id_or_email->data->user_nicename );
					$args['url'] = site_url( $path );
					$args['url'] = add_query_arg(
						array( 's' => $args['size'] ),
						$args['url']
					);
				}
			}
		}
		return $args;
	}

	public function serve_avatar( $wp ) {
		if ( ! array_key_exists( 'avatar', $wp->query_vars ) ) {
			return;
		}
		$username = $wp->get( 'avatar' );
		$size     = $wp->get( 's' );
		if ( ! is_numeric( $size ) ) {
			$size = 400;
		}

		$user = get_user_by( 'slug', $username );
		if ( ! $user ) {
			status_header( 404 );
			nocache_headers();
			include get_404_template();
			exit;
		}
		header( 'Cache-Control: max-age=300' );
		wp_redirect( // phpcs:ignore
			get_avatar_url(
				$user->ID,
				array(
					'default'         => '404',
					'size'            => $size,
					'redirect_bypass' => true,
				)
			)
		);
		exit;
	}
}

new Avatar_URL_Plugin();
