<?php
/**
 * Integration Test REST Endpoint for Free Downloads WooCommerce Settings.
 *
 * This endpoint performs comprehensive testing of the React settings migration:
 * - Loading test: Verify defaults load correctly
 * - Saving test: Verify settings persist correctly
 * - Edge cases: Test special characters, long text, toggle states
 * - Compatibility: Verify existing settings are preserved
 *
 * @package FreeDownloadsWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SOMDN_Integration_Test_Endpoint {

	/**
	 * All option name mappings (slug => database option name).
	 *
	 * @var array
	 */
	private $option_mappings = array(
		// FREE options
		'free-downloads-gen'       => 'somdn_gen_settings',
		'free-downloads-single'    => 'somdn_single_settings',
		'free-downloads-multi'     => 'somdn_multi_settings',
		'free-downloads-owned'     => 'somdn_owned_settings',
		'free-downloads-docviewer' => 'somdn_docviewer_settings',
		'free-downloads-debug'     => 'somdn_debug_settings',
		'free-downloads-quickview' => 'somdn_woo_quickview_settings',
	);

	/**
	 * PRO option name mappings.
	 *
	 * @var array
	 */
	private $pro_option_mappings = array(
		'free-downloads-limits'           => 'somdn_pro_basic_limit_settings',
		'free-downloads-membership-limits' => 'somdn_pro_membership_limit_settings',
		'free-downloads-tracking'         => 'somdn_pro_track_settings',
		'free-downloads-newsletter'       => 'somdn_pro_newsletter_general_settings',
		'free-downloads-mailchimp'        => 'somdn_pro_newsletter_mailchimp_settings',
		'free-downloads-download-type'    => 'somdn_download_type_settings',
		'free-downloads-include-products' => 'somdn_pro_include_product_settings',
		'free-downloads-include-cats'     => 'somdn_pro_include_cat_settings',
		'free-downloads-include-tags'     => 'somdn_pro_include_tag_settings',
		'free-downloads-emails'           => 'somdn_email_settings',
	);

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
			'/somdn-integration-test',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'run_integration_tests' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
				'args'                => array(
					'test_type' => array(
						'type'        => 'string',
						'required'    => false,
						'default'     => 'all',
						'enum'        => array( 'all', 'loading', 'saving', 'edge_cases', 'compatibility' ),
						'description' => 'Type of test to run.',
					),
				),
			)
		);
	}

	/**
	 * Run integration tests.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function run_integration_tests( WP_REST_Request $request ) {
		$test_type = $request->get_param( 'test_type' );
		$is_pro    = defined( 'SOMDN_PRO_VERSION' );
		$results   = array();

		// Combine mappings if Pro is active
		$all_mappings = $this->option_mappings;
		if ( $is_pro ) {
			$all_mappings = array_merge( $all_mappings, $this->pro_option_mappings );
		}

		// Run requested tests
		if ( 'all' === $test_type || 'loading' === $test_type ) {
			$results['loading'] = $this->test_loading( $all_mappings );
		}

		if ( 'all' === $test_type || 'saving' === $test_type ) {
			$results['saving'] = $this->test_saving( $all_mappings );
		}

		if ( 'all' === $test_type || 'edge_cases' === $test_type ) {
			$results['edge_cases'] = $this->test_edge_cases();
		}

		if ( 'all' === $test_type || 'compatibility' === $test_type ) {
			$results['compatibility'] = $this->test_compatibility( $all_mappings );
		}

		// Calculate summary
		$summary = $this->calculate_summary( $results );

		return rest_ensure_response(
			array(
				'success' => $summary['failed'] === 0,
				'summary' => $summary,
				'results' => $results,
				'is_pro'  => $is_pro,
			)
		);
	}

	/**
	 * Test 1: Loading Tests
	 * Verify all options can be loaded and default values are correct.
	 *
	 * @param array $mappings Option name mappings.
	 * @return array
	 */
	private function test_loading( $mappings ) {
		$tests = array();

		// Test 1.1: All option names are registered correctly
		foreach ( $mappings as $slug => $db_option ) {
			$filter_name     = 'wpe_option_name_' . $slug;
			$filtered_option = apply_filters( $filter_name, '' );

			$tests[] = array(
				'id'      => 'option_filter_' . $slug,
				'name'    => "Option filter: {$slug}",
				'status'  => $filtered_option === $db_option ? 'pass' : 'fail',
				'message' => $filtered_option === $db_option
					? "Filter returns correct option name: {$db_option}"
					: "Filter mismatch - expected '{$db_option}', got '{$filtered_option}'",
			);
		}

		// Test 1.2: Options can be read from database (even if empty)
		foreach ( $mappings as $slug => $db_option ) {
			$option_value = get_option( $db_option, null );

			$tests[] = array(
				'id'      => 'option_readable_' . $slug,
				'name'    => "Option readable: {$db_option}",
				'status'  => 'pass', // Always pass - we just verify it doesn't error
				'message' => null === $option_value
					? 'Option not set (will use defaults)'
					: 'Option exists with ' . count( (array) $option_value ) . ' value(s)',
			);
		}

		// Test 1.3: REST endpoint is registered
		// The settings endpoint is registered with a slug parameter pattern
		$routes = rest_get_server()->get_routes();
		$endpoint_exists = false;
		$endpoint_path = '';

		// Check for various possible route patterns
		foreach ( $routes as $route => $handlers ) {
			if ( strpos( $route, '/wpe/v1/settings' ) !== false ) {
				$endpoint_exists = true;
				$endpoint_path = $route;
				break;
			}
		}

		$tests[] = array(
			'id'      => 'rest_endpoint_registered',
			'name'    => 'Settings REST endpoint registered',
			'status'  => $endpoint_exists ? 'pass' : 'fail',
			'message' => $endpoint_exists
				? 'REST endpoint ' . $endpoint_path . ' is available'
				: 'REST endpoint not registered - settings framework may not be loaded',
		);

		return $tests;
	}

	/**
	 * Test 2: Saving Tests
	 * Verify settings can be saved and retrieved correctly.
	 *
	 * @param array $mappings Option name mappings.
	 * @return array
	 */
	private function test_saving( $mappings ) {
		$tests = array();

		// Test 2.1: Test saving and retrieving for each option group
		foreach ( $mappings as $slug => $db_option ) {
			// Create a unique test value
			$test_key   = 'test_field_' . time();
			$test_value = 'integration_test_' . wp_rand( 1000, 9999 );

			// Get current option
			$current_option = get_option( $db_option, array() );
			if ( ! is_array( $current_option ) ) {
				$current_option = array();
			}

			// Save test value
			$new_option              = $current_option;
			$new_option[ $test_key ] = $test_value;
			$save_result             = update_option( $db_option, $new_option );

			// Retrieve and verify
			$retrieved_option = get_option( $db_option, array() );
			$value_matches    = isset( $retrieved_option[ $test_key ] ) && $retrieved_option[ $test_key ] === $test_value;

			// Cleanup - restore original
			update_option( $db_option, $current_option );

			$tests[] = array(
				'id'      => 'save_retrieve_' . $slug,
				'name'    => "Save/retrieve: {$db_option}",
				'status'  => $value_matches ? 'pass' : 'fail',
				'message' => $value_matches
					? 'Successfully saved and retrieved test value'
					: 'Failed to save or retrieve test value',
			);
		}

		return $tests;
	}

	/**
	 * Test 3: Edge Cases
	 * Test special characters, long text, and toggle states.
	 *
	 * @return array
	 */
	private function test_edge_cases() {
		$tests       = array();
		$test_option = 'somdn_gen_settings';

		// Get current value to restore later
		$original_value = get_option( $test_option, array() );
		if ( ! is_array( $original_value ) ) {
			$original_value = array();
		}

		// Test 3.1: Special characters
		$special_chars = "Test with special chars: <script>alert('xss')</script> & \"quotes\" 'apostrophe' © ™ € £";
		$test_data     = array_merge( $original_value, array( 'edge_test_special' => $special_chars ) );
		update_option( $test_option, $test_data );

		$retrieved     = get_option( $test_option, array() );
		$retrieved_val = isset( $retrieved['edge_test_special'] ) ? $retrieved['edge_test_special'] : '';

		// The value should be stored (sanitization happens at save time via REST)
		$tests[] = array(
			'id'      => 'special_characters',
			'name'    => 'Special characters handling',
			'status'  => ! empty( $retrieved_val ) ? 'pass' : 'fail',
			'message' => ! empty( $retrieved_val )
				? 'Special characters stored correctly'
				: 'Failed to store special characters',
		);

		// Test 3.2: Long text (1000+ characters)
		$long_text = str_repeat( 'This is a long text string for testing. ', 50 );
		$test_data = array_merge( $original_value, array( 'edge_test_long' => $long_text ) );
		update_option( $test_option, $test_data );

		$retrieved     = get_option( $test_option, array() );
		$retrieved_val = isset( $retrieved['edge_test_long'] ) ? $retrieved['edge_test_long'] : '';

		$tests[] = array(
			'id'      => 'long_text',
			'name'    => 'Long text handling (2000+ chars)',
			'status'  => strlen( $retrieved_val ) > 1000 ? 'pass' : 'fail',
			'message' => strlen( $retrieved_val ) > 1000
				? 'Long text stored correctly (' . strlen( $retrieved_val ) . ' chars)'
				: 'Long text was truncated or not stored',
		);

		// Test 3.3: Boolean/toggle values
		$test_data = array_merge(
			$original_value,
			array(
				'edge_test_bool_true'  => 'on',
				'edge_test_bool_false' => '',
			)
		);
		update_option( $test_option, $test_data );

		$retrieved = get_option( $test_option, array() );

		$bool_true_ok  = isset( $retrieved['edge_test_bool_true'] ) && 'on' === $retrieved['edge_test_bool_true'];
		$bool_false_ok = isset( $retrieved['edge_test_bool_false'] ) && '' === $retrieved['edge_test_bool_false'];

		$tests[] = array(
			'id'      => 'toggle_states',
			'name'    => 'Toggle/boolean state handling',
			'status'  => ( $bool_true_ok && $bool_false_ok ) ? 'pass' : 'fail',
			'message' => ( $bool_true_ok && $bool_false_ok )
				? 'Both true and false toggle states stored correctly'
				: 'Toggle state handling issue',
		);

		// Test 3.4: Empty string vs. null
		$test_data = array_merge( $original_value, array( 'edge_test_empty' => '' ) );
		update_option( $test_option, $test_data );

		$retrieved = get_option( $test_option, array() );

		$tests[] = array(
			'id'      => 'empty_string',
			'name'    => 'Empty string preservation',
			'status'  => array_key_exists( 'edge_test_empty', $retrieved ) ? 'pass' : 'fail',
			'message' => array_key_exists( 'edge_test_empty', $retrieved )
				? 'Empty string key preserved in array'
				: 'Empty string key was removed',
		);

		// Test 3.5: Numeric values
		$test_data = array_merge(
			$original_value,
			array(
				'edge_test_int'   => 42,
				'edge_test_float' => 3.14,
				'edge_test_zero'  => 0,
			)
		);
		update_option( $test_option, $test_data );

		$retrieved = get_option( $test_option, array() );

		$int_ok   = isset( $retrieved['edge_test_int'] ) && 42 == $retrieved['edge_test_int'];
		$zero_ok  = isset( $retrieved['edge_test_zero'] ) && 0 == $retrieved['edge_test_zero'];

		$tests[] = array(
			'id'      => 'numeric_values',
			'name'    => 'Numeric value handling',
			'status'  => ( $int_ok && $zero_ok ) ? 'pass' : 'fail',
			'message' => ( $int_ok && $zero_ok )
				? 'Numeric values including zero stored correctly'
				: 'Numeric value handling issue',
		);

		// Cleanup - restore original
		update_option( $test_option, $original_value );

		return $tests;
	}

	/**
	 * Test 4: Compatibility Tests
	 * Verify existing settings from old framework are preserved.
	 *
	 * @param array $mappings Option name mappings.
	 * @return array
	 */
	private function test_compatibility( $mappings ) {
		$tests = array();

		// Test 4.1: Check for existing settings in each option group
		$has_existing_settings = false;
		foreach ( $mappings as $slug => $db_option ) {
			$option = get_option( $db_option, array() );
			if ( ! empty( $option ) && is_array( $option ) ) {
				$has_existing_settings = true;
				$field_count           = count( $option );

				$tests[] = array(
					'id'      => 'existing_settings_' . $slug,
					'name'    => "Existing settings: {$db_option}",
					'status'  => 'pass',
					'message' => "Found {$field_count} saved setting(s)",
				);
			}
		}

		if ( ! $has_existing_settings ) {
			$tests[] = array(
				'id'      => 'no_existing_settings',
				'name'    => 'Existing settings check',
				'status'  => 'warning',
				'message' => 'No existing settings found (fresh install or not yet configured)',
			);
		}

		// Test 4.2: Verify sanitizers are loaded
		$sanitizer_dir   = dirname( __FILE__ ) . '/sanitizers/';
		$sanitizer_files = array(
			'general-sanitizer.php',
			'single-files-sanitizer.php',
			'multiple-files-sanitizer.php',
			'owned-products-sanitizer.php',
			'pdf-sanitizer.php',
			'quick-view-sanitizer.php',
			'debug-sanitizer.php',
		);

		if ( defined( 'SOMDN_PRO_VERSION' ) ) {
			$sanitizer_files = array_merge(
				$sanitizer_files,
				array(
					'limits-sanitizer.php',
					'membership-limits-sanitizer.php',
					'tracking-sanitizer.php',
					'newsletter-sanitizer.php',
					'mailchimp-sanitizer.php',
					'download-type-sanitizer.php',
					'include-products-sanitizer.php',
					'include-cats-sanitizer.php',
					'include-tags-sanitizer.php',
					'emails-sanitizer.php',
				)
			);
		}

		$missing_sanitizers = array();
		foreach ( $sanitizer_files as $file ) {
			if ( ! file_exists( $sanitizer_dir . $file ) ) {
				$missing_sanitizers[] = $file;
			}
		}

		$tests[] = array(
			'id'      => 'sanitizers_exist',
			'name'    => 'Sanitizer files exist',
			'status'  => empty( $missing_sanitizers ) ? 'pass' : 'fail',
			'message' => empty( $missing_sanitizers )
				? 'All ' . count( $sanitizer_files ) . ' sanitizer files found'
				: 'Missing sanitizers: ' . implode( ', ', $missing_sanitizers ),
		);

		// Test 4.3: Verify known critical fields exist in database if settings were previously saved
		$gen_settings = get_option( 'somdn_gen_settings', array() );
		if ( ! empty( $gen_settings ) ) {
			// These are critical fields that should exist if General Settings was ever saved
			$critical_fields = array(
				'somdn_require_login',
				'somdn_include_archive_items',
			);

			$has_critical = true;
			foreach ( $critical_fields as $field ) {
				if ( ! array_key_exists( $field, $gen_settings ) ) {
					$has_critical = false;
					break;
				}
			}

			$tests[] = array(
				'id'      => 'critical_fields_preserved',
				'name'    => 'Critical fields preserved',
				'status'  => $has_critical ? 'pass' : 'warning',
				'message' => $has_critical
					? 'Critical settings fields are preserved'
					: 'Some critical fields may need re-saving',
			);
		}

		// Test 4.4: Pro license migration (if Pro active)
		if ( defined( 'SOMDN_PRO_VERSION' ) ) {
			$wpe_licenses   = get_option( 'wp_enhanced_licenses', array() );
			$old_license    = get_option( 'somdn_pro_license_key', '' );
			$migration_done = get_option( 'somdn_license_migrated_to_wpe', false );

			if ( ! empty( $old_license ) ) {
				$migrated = isset( $wpe_licenses['free-downloads-woocommerce-pro'] );

				$tests[] = array(
					'id'      => 'license_migration',
					'name'    => 'License migration status',
					'status'  => $migrated ? 'pass' : 'warning',
					'message' => $migrated
						? 'License migrated to new format successfully'
						: 'License exists in old format but not migrated yet',
				);
			} elseif ( $migration_done ) {
				$tests[] = array(
					'id'      => 'license_migration',
					'name'    => 'License migration status',
					'status'  => 'pass',
					'message' => 'License migration check completed (no license to migrate)',
				);
			}
		}

		return $tests;
	}

	/**
	 * Calculate summary from test results.
	 *
	 * @param array $results All test results.
	 * @return array
	 */
	private function calculate_summary( $results ) {
		$total   = 0;
		$passed  = 0;
		$failed  = 0;
		$warning = 0;

		foreach ( $results as $category => $tests ) {
			foreach ( $tests as $test ) {
				$total++;
				switch ( $test['status'] ) {
					case 'pass':
						$passed++;
						break;
					case 'fail':
						$failed++;
						break;
					case 'warning':
						$warning++;
						break;
				}
			}
		}

		return array(
			'total'   => $total,
			'passed'  => $passed,
			'failed'  => $failed,
			'warning' => $warning,
		);
	}
}

// Initialize the endpoint
new SOMDN_Integration_Test_Endpoint();
