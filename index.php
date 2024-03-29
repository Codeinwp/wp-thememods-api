<?php

namespace WPThemeModsAPI;
/**
 * Plugin Name:       WP ThemeMods API
 * Description:       Allow theme mods editing via REST API.
 * Version:           0.0.5
 * Author:            Themeisle
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */
class Bootstrap {
	const DISALLOWED_KEYS = [
		'sidebars_widgets'   => true,
		'custom_css_post_id' => true,
		'nav_menu_locations' => true,
	];

	/**
	 * Bootstrap init function.
	 */
	public function init() {

		add_action( 'rest_api_init', [ $this, 'register_route' ] );
		
		/*
		 * Automagically authorize every request
		 * INSECURE! DANGER! ONLY USE IN LOCAL ENVIRONMENT.
		 */
		add_filter( 'rest_authentication_errors', function(){
		    wp_set_current_user( 1 ); // replace with the ID of a WP user with the authorization you want
		}, 101 );
		
		$this->update_empty_option();
		$this->filter_theme_mods();
	}

	/**
	 * This method is required because you can't filter an option that doesn't exist.
	 */
	private function update_empty_option() {
		if ( get_option( 'thememods_api_updated_option', 'no' ) === 'yes' ) {
			return;
		}
		update_option( 'woocommerce_shop_page_display', '' );
		update_option( 'thememods_api_updated_option', 'yes' );
	}

	/**
	 * Filter theme mods.
	 */
	private function filter_theme_mods() {
		if ( ! isset( $_GET['test_name'] ) ) {
			return;
		}

		$test_name  = $_GET['test_name'];
		$theme_mods = get_option( $test_name . '_theme_mods' );

		if ( empty( $theme_mods ) ) {
			return;
		}

		// For some theme mods we need to cast the value to boolean.
		$bool_theme_mods = [ 'neve_advanced_layout_options', 'neve_blog_list_alternative_layout', 'neve_enable_card_style', 'neve_blog_separator', 'neve_global_header', 'neve_enable_payment_icons', 'neve_enable_product_breadcrumbs', 'neve_enable_product_navigation', 'neve_enable_cart_upsells', 'neve_checkout_boxed_layout', 'neve_enable_seamless_add_to_cart', 'neve_ran_migrations', 'neve_migrated_hfg_colors', 'advanced_search_form_1_exclude_sticky' ];

		foreach ( $theme_mods as $key => $value ){

			// Check if a key is actually an option and not a theme mod.
			if ( $key === 'options' ) {
				foreach ( $value as $option_key => $option_value ) {
					add_filter( 'option_' . $option_key, function () use ( $option_value ) {
						return $option_value;
					});
				}

				continue;
			}

			add_filter( 'theme_mod_'.$key, function ( $value ) use ( $key, $test_name, $bool_theme_mods ) {
				if ( 'not-exists' === get_option( $test_name . '_' . $key, 'not-exists' ) ) {
					return $value;
				}

				if ( in_array( $key, $bool_theme_mods, true ) ){
					return (bool) get_option( $test_name . '_' . $key );
				}

				return get_option( $test_name . '_' . $key );
			});
		}
	}

	/**
	 * Define the API callback for get.
	 */
	public function register_route() {

		register_rest_route( 'wpthememods/v1', '/settings/(?P<test_name>[a-zA-Z0-9-]+)', array(
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
		if ( ! defined( 'WPTHEMEMODS_SECRET' ) ) {
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
        $test_name = $request->get_param( 'test_name' );
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

			if ( $key === 'neve_header_conditional_selector' ) {
				$response = $this->handle_conditional_headers( $value );

				if ( is_wp_error( $response ) ) {
					return $response;
				}

				$mods_to_set['neve_global_header'] = false;

				continue;
			}

			if ( $key === 'options' ) {
				foreach ( $value as $option_key => $option_value ) {
					$mods_to_set['options'][ $option_key ] = $option_value;
				}
			}

			$mods_to_set[ $key ] = $value;
		}

        update_option( $test_name . '_theme_mods', $mods_to_set);
        foreach ( $mods_to_set as $key => $value ){
            update_option( $test_name . '_' . $key, $value );
        }

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

	/**
	 * Handle the conditional headers theme mod.
	 *
	 * @param array $value value for conditional headers.
	 * @return array|\WP_Error
	 */
	public function handle_conditional_headers( $value ) {
		if ( ! class_exists( '\Neve_Pro\Modules\Header_Footer_Grid\Customizer\Conditional_Headers' ) ) {
			return new \WP_Error( 'invalid', 'Neve Pro should be installed for this to work.' );
		}

		$value['add'] = [];
		foreach ( array_keys( $value['headers'] ) as $header_slug ) {
			if ( $header_slug === 'default' ) {
				continue;
			}

			$value['add'][ $header_slug ] = $value['headers'][ $header_slug ]['label'];
		}

		$handler = new \Neve_Pro\Modules\Header_Footer_Grid\Customizer\Conditional_Headers();
		$handler->init();

		$to_send = [ 'neve::neve_header_conditional_selector' => [ 'value' => $value ] ];
		$context = [ 'status' => 'publish' ];

		return $handler->conditional_headers_filtering( $to_send, $context );
	}
}

add_action( 'plugins_loaded', [ ( new Bootstrap() ), 'init' ] );

add_filter('api_bearer_auth_unauthenticated_urls', array( ( new Bootstrap() ) , 'api_bearer_auth_unauthenticated_urls_filter' ), 10, 2);
