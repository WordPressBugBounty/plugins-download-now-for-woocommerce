<?php
/**
 * REST endpoints for addon license management (React settings).
 * Addons register via filter somdn_addon_licenses.
 *
 * @package Free_Downloads_WooCommerce_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Addon license REST endpoints.
 */
class SOMDN_Addon_License_Endpoint {

	/**
	 * Register REST routes.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		register_rest_route( 'wpe/v1', '/get-addon-licenses', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_addon_licenses' ),
			'permission_callback' => array( $this, 'check_admin_permission' ),
		) );
		register_rest_route( 'wpe/v1', '/debug-addon-licenses', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'debug_addon_licenses' ),
			'permission_callback' => array( $this, 'check_admin_permission' ),
		) );
		register_rest_route( 'wpe/v1', '/validate-addon-license', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'validate_addon_license' ),
			'permission_callback' => array( $this, 'check_admin_permission' ),
		) );
		register_rest_route( 'wpe/v1', '/deactivate-addon-license', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'deactivate_addon_license' ),
			'permission_callback' => array( $this, 'check_admin_permission' ),
		) );
	}

	/**
	 * Check if user can manage options.
	 *
	 * @return bool
	 */
	public function check_admin_permission() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Debug: return raw addon configs from filter (admin only). Open in browser when logged in: /wp-json/wpe/v1/debug-addon-licenses
	 *
	 * @return \WP_REST_Response
	 */
	public function debug_addon_licenses() {
		$addons = apply_filters( 'somdn_addon_licenses', array() );
		$count = is_array( $addons ) ? count( $addons ) : 0;
		return rest_ensure_response( array(
			'endpoint_loaded' => true,
			'filter_name'     => 'somdn_addon_licenses',
			'addons_count'    => $count,
			'addons_raw'      => $addons,
			'message'         => $count > 0
				? sprintf( __( '%d addon(s) registered. React License tab should show them.', 'free-downloads-woocommerce-pro' ), $count )
				: __( 'No addons registered. Ensure CRM/Email addon is active and uses add_filter("somdn_addon_licenses", ...).', 'free-downloads-woocommerce-pro' ),
		) );
	}

	/**
	 * Get list of registered addon licenses with current key (masked) and status.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_addon_licenses() {
		$addons = apply_filters( 'somdn_addon_licenses', array() );
		if ( ! is_array( $addons ) ) {
			$addons = array();
		}
		$out = array();
		foreach ( $addons as $addon ) {
			$slug    = isset( $addon['slug'] ) ? sanitize_key( $addon['slug'] ) : '';
			$opt_key = isset( $addon['option_key'] ) ? $addon['option_key'] : '';
			$opt_st  = isset( $addon['option_status_key'] ) ? $addon['option_status_key'] : '';
			if ( $slug === '' || $opt_key === '' ) {
				continue;
			}
			$license_key = get_option( $opt_key, '' );
			$status_val  = get_option( $opt_st, '' );
			$is_valid    = ( $status_val === 'valid' );
			$masked      = $license_key !== '' ? self::mask_license_key( $license_key ) : '';
			$out[]       = array(
				'plugin'      => $slug,
				'name'        => isset( $addon['name'] ) ? sanitize_text_field( $addon['name'] ) : $slug,
				'description' => $is_valid
					? __( 'Your license is active. You can deactivate it if needed.', 'free-downloads-woocommerce-pro' )
					: __( 'Enter your license key to activate updates and support.', 'free-downloads-woocommerce-pro' ),
				'licenseKey'  => $masked,
				'status'      => $is_valid ? 'Active' : 'Inactive',
				'action'      => $is_valid ? 'Deactivate' : 'Validate',
				'plugin_id'   => isset( $addon['item_id'] ) ? $addon['item_id'] : '',
			);
		}
		return rest_ensure_response( $out );
	}

	/**
	 * Validate (activate) an addon license.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function validate_addon_license( $request ) {
		$params = $request->get_json_params();
		$slug   = isset( $params['addon_slug'] ) ? sanitize_key( $params['addon_slug'] ) : '';
		$key    = isset( $params['license_key'] ) ? sanitize_text_field( trim( $params['license_key'] ) ) : '';
		if ( $slug === '' || $key === '' ) {
			return rest_ensure_response( array(
				'success' => false,
				'message' => __( 'License key or addon is missing.', 'free-downloads-woocommerce-pro' ),
			) );
		}
		$addon = $this->get_addon_by_slug( $slug );
		if ( ! $addon ) {
			return rest_ensure_response( array(
				'success' => false,
				'message' => __( 'Addon not found.', 'free-downloads-woocommerce-pro' ),
			) );
		}
		$store_url = isset( $addon['store_url'] ) ? $addon['store_url'] : 'https://wpenhanced.com';
		$item_id   = isset( $addon['item_id'] ) ? $addon['item_id'] : 0;
		$opt_key   = $addon['option_key'];
		$opt_st    = $addon['option_status_key'];

		$api_params = array(
			'edd_action' => 'activate_license',
			'license'    => $key,
			'item_id'    => $item_id,
			'url'        => home_url(),
		);
		$response   = wp_remote_post( $store_url, array( 'timeout' => 15, 'sslverify' => false, 'body' => $api_params ) );

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			$message = is_wp_error( $response ) ? $response->get_error_message() : __( 'Connection error.', 'free-downloads-woocommerce-pro' );
			return rest_ensure_response( array( 'success' => false, 'message' => $message ) );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data ) || ( isset( $data['success'] ) && ! $data['success'] ) ) {
			$error   = isset( $data['error'] ) ? $data['error'] : '';
			$message = $this->get_edd_error_message( $error, $data );
			return rest_ensure_response( array( 'success' => false, 'message' => $message ) );
		}

		update_option( $opt_key, $key );
		update_option( $opt_st, 'valid' );
		return rest_ensure_response( array(
			'success'       => true,
			'message'       => __( 'License activated successfully.', 'free-downloads-woocommerce-pro' ),
			'formatted_key' => self::mask_license_key( $key ),
		) );
	}

	/**
	 * Deactivate an addon license.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function deactivate_addon_license( $request ) {
		$params = $request->get_json_params();
		$slug   = isset( $params['addon_slug'] ) ? sanitize_key( $params['addon_slug'] ) : '';
		if ( $slug === '' ) {
			return rest_ensure_response( array(
				'success' => false,
				'message' => __( 'Addon slug is missing.', 'free-downloads-woocommerce-pro' ),
			) );
		}
		$addon = $this->get_addon_by_slug( $slug );
		if ( ! $addon ) {
			return rest_ensure_response( array( 'success' => false, 'message' => __( 'Addon not found.', 'free-downloads-woocommerce-pro' ) ) );
		}
		$store_url = isset( $addon['store_url'] ) ? $addon['store_url'] : 'https://wpenhanced.com';
		$item_id   = isset( $addon['item_id'] ) ? $addon['item_id'] : 0;
		$opt_key   = $addon['option_key'];
		$opt_st    = $addon['option_status_key'];
		$key       = get_option( $opt_key, '' );

		if ( $key === '' ) {
			delete_option( $opt_st );
			return rest_ensure_response( array( 'success' => true, 'message' => __( 'License deactivated.', 'free-downloads-woocommerce-pro' ) ) );
		}

		$api_params = array(
			'edd_action' => 'deactivate_license',
			'license'    => $key,
			'item_id'    => $item_id,
			'url'        => home_url(),
		);
		$response   = wp_remote_post( $store_url, array( 'timeout' => 15, 'sslverify' => false, 'body' => $api_params ) );

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			$message = is_wp_error( $response ) ? $response->get_error_message() : __( 'Connection error.', 'free-downloads-woocommerce-pro' );
			return rest_ensure_response( array( 'success' => false, 'message' => $message ) );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		delete_option( $opt_key );
		delete_option( $opt_st );
		return rest_ensure_response( array(
			'success' => true,
			'message' => __( 'License deactivated successfully.', 'free-downloads-woocommerce-pro' ),
		) );
	}

	/**
	 * Get one addon config by slug.
	 *
	 * @param string $slug Addon slug.
	 * @return array|null Addon config or null.
	 */
	private function get_addon_by_slug( $slug ) {
		$addons = apply_filters( 'somdn_addon_licenses', array() );
		if ( ! is_array( $addons ) ) {
			return null;
		}
		foreach ( $addons as $addon ) {
			if ( isset( $addon['slug'] ) && sanitize_key( $addon['slug'] ) === $slug ) {
				return $addon;
			}
		}
		return null;
	}

	/**
	 * Mask license key for display.
	 *
	 * @param string $key License key.
	 * @return string
	 */
	public static function mask_license_key( $key ) {
		$key = trim( $key );
		if ( strlen( $key ) <= 20 ) {
			return $key;
		}
		return substr( $key, 0, 20 ) . '-xxx-xxxxxxxx';
	}

	/**
	 * Get user-facing message for EDD license error.
	 *
	 * @param string $error Error code.
	 * @param array  $data Response data.
	 * @return string
	 */
	private function get_edd_error_message( $error, $data ) {
		switch ( $error ) {
			case 'expired':
				$expires = isset( $data['expires'] ) ? $data['expires'] : '';
				return $expires
					? sprintf( __( 'Your license key expired on %s.', 'free-downloads-woocommerce-pro' ), date_i18n( get_option( 'date_format' ), strtotime( $expires, time() ) ) )
					: __( 'Your license key has expired.', 'free-downloads-woocommerce-pro' );
			case 'revoked':
				return __( 'Your license key has been disabled.', 'free-downloads-woocommerce-pro' );
			case 'missing':
				return __( 'Invalid license.', 'free-downloads-woocommerce-pro' );
			case 'invalid':
			case 'site_inactive':
				return __( 'Your license is not active for this URL.', 'free-downloads-woocommerce-pro' );
			case 'item_name_mismatch':
				return __( 'This appears to be an invalid license key for this product.', 'free-downloads-woocommerce-pro' );
			case 'no_activations_left':
				return __( 'Your license key has reached its activation limit.', 'free-downloads-woocommerce-pro' );
			default:
				return __( 'An error occurred, please try again.', 'free-downloads-woocommerce-pro' );
		}
	}
}

new SOMDN_Addon_License_Endpoint();
