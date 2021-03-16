<?php

namespace WPThemeModsAPI;
/**
 * Plugin Name:       WP ThemeMods API
 * Description:       Allow theme mods editing via REST API.
 * Version:           0.0.4
 * Author:            Themeisle
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */
class Bootstrap {
	const        DISALLOWED_KEYS = [
		'sidebars_widgets'   => true,
		'custom_css_post_id' => true,
		'nav_menu_locations' => true,
	];

	public function init() {

		add_action( 'rest_api_init', [ $this, 'register_route' ] );

	}

	public function register_route() {

		register_rest_route( 'wpthememods/v1', '/settings', array(
			'methods'             => 'POST',
			'permission_callback' => [ $this, 'is_allowed' ],
			'callback'            => [ $this, 'set_mods' ],
		) );

		register_rest_route( 'wpthememods/v1', '/settings', array(
			'methods'             => 'GET',
			'permission_callback' => [ $this, 'is_allowed' ],
			'callback'            => [ $this, 'get_mods' ],
		) );
	}

	/**
	 * Check if the request is allowed.
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return bool
	 */
	public function is_allowed( \WP_REST_Request $request ) {
		//If secret is not defined, we always allow access.
		if ( ! defined( WPTHEMEMODS_SECRET ) ) {
			return true;
		}
		$token = $request->get_header( 'Authorization' );
		$token = \trim( (string) \preg_replace( '/^(?:\s+)?Bearer\s/', '', $token ) );

		return $token === WPTHEMEMODS_SECRET;
	}

	/**
	 * Define the API callback.
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function set_mods( \WP_REST_Request $request ) {
		$body = $request->get_json_params();
		if ( is_string( $body ) ) {
			return new \WP_Error( 'invalid', 'Invalid data provided' );
		}
		$mods = get_theme_mods();

		$mods_to_set = [];
		// Preserve the disallowed keys.
		foreach ( self::DISALLOWED_KEYS as $key_to_keep => $status ) {
			if ( isset( $mods[ $key_to_keep ] ) ) {
				$mods_to_set[ $key_to_keep ] = $mods[ $key_to_keep ];
			}
		}
		// Setup the new thememods and validate theme against the disallowed keys.
		foreach ( $body as $key => $value ) {
			if ( isset( self::DISALLOWED_KEYS[ $key ] ) ) {
				continue;
			}
			$mods_to_set[ $key ] = $value;
		}

		$theme_slug = get_option( 'stylesheet' );
		update_option( "theme_mods_$theme_slug", $mods_to_set );

		return new \WP_REST_Response( $mods_to_set );
	}

	/**
	 * Define the API callback for get request.
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function get_mods( \WP_REST_Request $request ) {
		$mods = get_theme_mods();

		return new \WP_REST_Response( $mods );
	}

  /**
   * Adds to JWT whitelist
   * 
   * @param $custom_urls
   * @param $request_method
   * 
   * @return $custom_urls
   */
  public function api_bearer_auth_unauthenticated_urls_filter($custom_urls, $request_method) {
    switch ($request_method) {
      case 'POST':
        $custom_urls[] = '/wp-json/wpthememods/v1/settings';
        break;
      case 'GET':
        $custom_urls[] = '/wp-json/wpthememods/v1/settings';
        break;
    }
    return $custom_urls;
  }
}

add_action( 'plugins_loaded', [ ( new Bootstrap() ), 'init' ] );

add_filter('api_bearer_auth_unauthenticated_urls', array( ( new Bootstrap() ) , 'api_bearer_auth_unauthenticated_urls_filter' ), 10, 2);