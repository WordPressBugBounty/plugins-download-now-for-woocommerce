<?php
/**
 * Configuration Check REST Endpoint for Free Downloads WooCommerce.
 *
 * Provides server-side validation of plugin settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SOMDN_Config_Check_Endpoint {

	/**
	 * Constructor - register REST endpoints.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_endpoints' ) );
	}

	/**
	 * Register REST routes.
	 */
	public function register_endpoints() {
		register_rest_route(
			'wpe/v1',
			'/somdn-config-check',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'run_config_check' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
				'args'                => array(
					'settings' => array(
						'type'        => 'object',
						'required'    => false,
						'default'     => array(),
						'description' => 'Current settings values from the UI.',
					),
				),
			)
		);
	}

	/**
	 * Run configuration checks.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function run_config_check( WP_REST_Request $request ) {
		$settings = $request->get_param( 'settings' );
		$checks   = array();
		$is_pro   = defined( 'SOMDN_PRO_VERSION' );

		// =========================================================================
		// CORE CHECKS
		// =========================================================================

		// Check: WooCommerce is active
		$wc_active = class_exists( 'WooCommerce' );
		$checks[]  = array(
			'id'      => 'woocommerce_active',
			'title'   => 'WooCommerce',
			'status'  => $wc_active ? 'pass' : 'fail',
			'message' => $wc_active
				? 'WooCommerce is active and running.'
				: 'WooCommerce is not active. This plugin requires WooCommerce to function.',
		);

		// Check: WooCommerce version
		if ( $wc_active && defined( 'WC_VERSION' ) ) {
			$wc_version_ok = version_compare( WC_VERSION, '3.0', '>=' );
			$checks[]      = array(
				'id'      => 'woocommerce_version',
				'title'   => 'WooCommerce Version',
				'status'  => $wc_version_ok ? 'pass' : 'warning',
				'message' => $wc_version_ok
					? 'WooCommerce version ' . WC_VERSION . ' is compatible.'
					: 'WooCommerce version ' . WC_VERSION . ' may have compatibility issues. Version 3.0+ recommended.',
			);
		}

		// Check: Any downloadable products exist
		if ( $wc_active ) {
			$downloadable_count = $this->count_downloadable_products();
			if ( $downloadable_count > 0 ) {
				$checks[] = array(
					'id'      => 'downloadable_products',
					'title'   => 'Downloadable Products',
					'status'  => 'pass',
					'message' => sprintf( 'Found %d downloadable product(s) in your store.', $downloadable_count ),
				);
			} else {
				$checks[] = array(
					'id'      => 'downloadable_products',
					'title'   => 'Downloadable Products',
					'status'  => 'warning',
					'message' => 'No downloadable products found. Create some products with downloadable files to use this plugin.',
					'action'  => array(
						'label' => 'Add Product',
						'href'  => admin_url( 'post-new.php?post_type=product' ),
					),
				);
			}

			// Check: Free downloadable products exist
			$free_downloadable_count = $this->count_free_downloadable_products();
			if ( $free_downloadable_count > 0 ) {
				$checks[] = array(
					'id'      => 'free_downloadable_products',
					'title'   => 'Free Downloadable Products',
					'status'  => 'pass',
					'message' => sprintf( 'Found %d free downloadable product(s) ready for instant downloads.', $free_downloadable_count ),
				);
			} else {
				$checks[] = array(
					'id'      => 'free_downloadable_products',
					'title'   => 'Free Downloadable Products',
					'status'  => 'warning',
					'message' => 'No free downloadable products found. Set product prices to $0 or enable the "Free Download" option on products.',
				);
			}
		}

		// =========================================================================
		// PRO CHECKS
		// =========================================================================

		if ( $is_pro ) {
			// Check: MailChimp API key validation (if set)
			$mailchimp_api_key = isset( $settings['somdn_newsletter_mailchimp_api_key'] )
				? sanitize_text_field( $settings['somdn_newsletter_mailchimp_api_key'] )
				: '';
			$subscribe_option  = isset( $settings['somdn_capture_email_subscribe'] )
				? sanitize_text_field( $settings['somdn_capture_email_subscribe'] )
				: '0';

			if ( 'mailchimp' === $subscribe_option && ! empty( $mailchimp_api_key ) ) {
				$mailchimp_valid = $this->validate_mailchimp_api_key( $mailchimp_api_key );
				$checks[]        = array(
					'id'      => 'mailchimp_api_set',
					'title'   => 'MailChimp API Key',
					'status'  => $mailchimp_valid ? 'pass' : 'fail',
					'message' => $mailchimp_valid
						? 'MailChimp API key is valid and connected.'
						: 'MailChimp API key appears to be invalid. Please check your API key.',
					'action'  => ! $mailchimp_valid
						? array(
							'label' => 'Configure Newsletter',
							'href'  => '#free-downloads-woocommerce/newsletter',
						)
						: null,
				);
			}

			// Check: Redirect page exists and has shortcode (if redirect delivery enabled)
			$delivery_type  = isset( $settings['somdn_download_type_option'] )
				? sanitize_text_field( $settings['somdn_download_type_option'] )
				: '0';
			$redirect_page  = isset( $settings['somdn_download_type_redirect_page'] )
				? absint( $settings['somdn_download_type_redirect_page'] )
				: 0;

			if ( ( '1' === $delivery_type || '2' === $delivery_type ) && $redirect_page > 0 ) {
				$page_exists    = get_post( $redirect_page );
				$has_shortcode  = false;
				
				if ( $page_exists ) {
					$page_content  = $page_exists->post_content;
					$has_shortcode = has_shortcode( $page_content, 'download_redirect' );
				}

				if ( ! $page_exists ) {
					$checks[] = array(
						'id'      => 'redirect_page_exists',
						'title'   => 'Redirect Page',
						'status'  => 'fail',
						'message' => 'The selected redirect page no longer exists. Please select a different page.',
						'action'  => array(
							'label' => 'Configure Download Delivery',
							'href'  => '#free-downloads-woocommerce/download-delivery',
						),
					);
				} elseif ( ! $has_shortcode ) {
					$checks[] = array(
						'id'      => 'redirect_page_shortcode',
						'title'   => 'Redirect Page Shortcode',
						'status'  => 'warning',
						'message' => 'The redirect page does not contain the [download_redirect] shortcode. Add this shortcode to display the download message.',
						'action'  => array(
							'label' => 'Edit Page',
							'href'  => admin_url( 'post.php?post=' . $redirect_page . '&action=edit' ),
						),
					);
				}
			}

			// Check: Pro license status
			$license_key    = get_option( 'somdn_pro_license_key', '' );
			$license_status = get_option( 'somdn_pro_license_status', '' );

			if ( empty( $license_key ) ) {
				$checks[] = array(
					'id'      => 'pro_license',
					'title'   => 'Pro License',
					'status'  => 'warning',
					'message' => 'No license key entered. Enter your license key to receive updates.',
					'action'  => array(
						'label' => 'Enter License',
						'href'  => '#wp-enhanced-license-settings',
					),
				);
			} elseif ( 'valid' !== $license_status ) {
				$checks[] = array(
					'id'      => 'pro_license',
					'title'   => 'Pro License',
					'status'  => 'warning',
					'message' => 'License key is not active. Activate your license to receive updates.',
					'action'  => array(
						'label' => 'Manage License',
						'href'  => '#wp-enhanced-license-settings',
					),
				);
			} else {
				$checks[] = array(
					'id'      => 'pro_license',
					'title'   => 'Pro License',
					'status'  => 'pass',
					'message' => 'Pro license is active and receiving updates.',
				);
			}
		}

		// =========================================================================
		// GENERAL CHECKS
		// =========================================================================

		// Check: PHP version
		$php_version_ok = version_compare( PHP_VERSION, '7.4', '>=' );
		$checks[]       = array(
			'id'      => 'php_version',
			'title'   => 'PHP Version',
			'status'  => $php_version_ok ? 'pass' : 'warning',
			'message' => $php_version_ok
				? 'PHP version ' . PHP_VERSION . ' is compatible.'
				: 'PHP version ' . PHP_VERSION . ' is outdated. PHP 7.4+ recommended.',
		);

		// Check: WordPress version
		global $wp_version;
		$wp_version_ok = version_compare( $wp_version, '5.0', '>=' );
		$checks[]      = array(
			'id'      => 'wp_version',
			'title'   => 'WordPress Version',
			'status'  => $wp_version_ok ? 'pass' : 'warning',
			'message' => $wp_version_ok
				? 'WordPress version ' . $wp_version . ' is compatible.'
				: 'WordPress version ' . $wp_version . ' may have compatibility issues. Version 5.0+ recommended.',
		);

		// Check: Permalink structure
		$permalink_structure = get_option( 'permalink_structure' );
		if ( empty( $permalink_structure ) ) {
			$checks[] = array(
				'id'      => 'permalinks',
				'title'   => 'Permalinks',
				'status'  => 'warning',
				'message' => 'Plain permalinks are being used. Pretty permalinks are recommended for better compatibility.',
				'action'  => array(
					'label' => 'Update Permalinks',
					'href'  => admin_url( 'options-permalink.php' ),
				),
			);
		} else {
			$checks[] = array(
				'id'      => 'permalinks',
				'title'   => 'Permalinks',
				'status'  => 'pass',
				'message' => 'Pretty permalinks are enabled.',
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'checks'  => $checks,
			)
		);
	}

	/**
	 * Count downloadable products.
	 *
	 * @return int
	 */
	private function count_downloadable_products() {
		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'     => '_downloadable',
					'value'   => 'yes',
					'compare' => '=',
				),
			),
			'fields'         => 'ids',
		);

		$query = new WP_Query( $args );
		return $query->found_posts;
	}

	/**
	 * Count free downloadable products.
	 *
	 * @return int
	 */
	private function count_free_downloadable_products() {
		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => '_downloadable',
					'value'   => 'yes',
					'compare' => '=',
				),
				array(
					'relation' => 'OR',
					array(
						'key'     => '_price',
						'value'   => array( '', '0' ),
						'compare' => 'IN',
					),
					array(
						'key'     => '_price',
						'value'   => 0,
						'compare' => '=',
						'type'    => 'NUMERIC',
					),
				),
			),
			'fields'         => 'ids',
		);

		$query = new WP_Query( $args );
		return $query->found_posts;
	}

	/**
	 * Validate MailChimp API key.
	 *
	 * @param string $api_key The API key to validate.
	 * @return bool
	 */
	private function validate_mailchimp_api_key( $api_key ) {
		if ( empty( $api_key ) ) {
			return false;
		}

		// Extract datacenter from API key (format: xxxx-usX)
		$parts = explode( '-', $api_key );
		if ( count( $parts ) < 2 ) {
			return false;
		}

		$dc = end( $parts );

		// Make a simple API call to verify the key
		$url = sprintf( 'https://%s.api.mailchimp.com/3.0/ping', $dc );

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( 'user:' . $api_key ),
					'Content-Type'  => 'application/json',
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		return 200 === $code;
	}
}

// Initialize the endpoint
new SOMDN_Config_Check_Endpoint();
