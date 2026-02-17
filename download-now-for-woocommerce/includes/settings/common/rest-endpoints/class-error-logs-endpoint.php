<?php
/**
 * Error Logs REST Endpoint
 *
 * Provides endpoints for reading, deleting, and testing plugin error log files.
 * This is a shared endpoint used by all plugins in the settings framework.
 *
 * @package Settings_Framework
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', 'de_register_error_logs_routes' );

/**
 * Register error logs REST routes.
 *
 * Routes are registered under both namespaces (de/v1 and wpe/v1) for compatibility.
 */
function de_register_error_logs_routes() {
	$namespaces = array( 'de/v1', 'wpe/v1' );

	foreach ( $namespaces as $namespace ) {
		// GET /{namespace}/error-logs - Fetch log contents
		register_rest_route(
			$namespace,
			'/error-logs',
			array(
				'methods'             => 'GET',
				'callback'            => 'de_get_error_logs',
				'permission_callback' => 'de_error_logs_permission_check',
				'args'                => array(
					'log_path' => array(
						'required'    => true,
						'type'        => 'string',
						'description' => 'Relative path to log file from uploads directory',
					),
				),
			)
		);

		// DELETE /{namespace}/error-logs - Delete log file
		register_rest_route(
			$namespace,
			'/error-logs',
			array(
				'methods'             => 'DELETE',
				'callback'            => 'de_delete_error_logs',
				'permission_callback' => 'de_error_logs_permission_check',
				'args'                => array(
					'log_path' => array(
						'required'    => true,
						'type'        => 'string',
						'description' => 'Relative path to log file from uploads directory',
					),
				),
			)
		);

		// POST /{namespace}/error-logs/test - Write a test log entry
		register_rest_route(
			$namespace,
			'/error-logs/test',
			array(
				'methods'             => 'POST',
				'callback'            => 'de_test_error_logs',
				'permission_callback' => 'de_error_logs_permission_check',
				'args'                => array(
					'log_path' => array(
						'required'    => true,
						'type'        => 'string',
						'description' => 'Relative path to log file from uploads directory',
					),
					'message'  => array(
						'required'    => false,
						'type'        => 'string',
						'default'     => 'Test log entry from settings page',
						'description' => 'Test message to write',
					),
				),
			)
		);
	}
}

/**
 * Check if user has permission to access error logs.
 *
 * @return bool
 */
function de_error_logs_permission_check() {
	return current_user_can( 'manage_options' );
}

/**
 * Get error log contents.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response|WP_Error
 */
function de_get_error_logs( WP_REST_Request $request ) {
	$log_path   = $request->get_param( 'log_path' );
	$upload_dir = wp_upload_dir();
	$full_path  = $upload_dir['basedir'] . '/' . $log_path;

	// Security: Ensure path is within uploads directory
	// Check if file exists first, then validate real path
	if ( ! file_exists( $full_path ) ) {
		return rest_ensure_response(
			array(
				'content' => '',
				'exists'  => false,
				'message' => 'No errors logged yet.',
			)
		);
	}

	$real_path    = realpath( $full_path );
	$uploads_real = realpath( $upload_dir['basedir'] );

	if ( false === $real_path || false === $uploads_real || strpos( $real_path, $uploads_real ) !== 0 ) {
		return new WP_Error( 'invalid_path', 'Invalid log path', array( 'status' => 400 ) );
	}

	$content = file_get_contents( $full_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

	return rest_ensure_response(
		array(
			'content'  => esc_html( $content ),
			'exists'   => true,
			'size'     => filesize( $full_path ),
			'modified' => filemtime( $full_path ),
		)
	);
}

/**
 * Delete error log file.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response|WP_Error
 */
function de_delete_error_logs( WP_REST_Request $request ) {
	$log_path   = $request->get_param( 'log_path' );
	$upload_dir = wp_upload_dir();
	$full_path  = $upload_dir['basedir'] . '/' . $log_path;

	// Check if file exists
	if ( ! file_exists( $full_path ) ) {
		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Log file does not exist.',
			)
		);
	}

	// Security: Ensure path is within uploads directory
	$real_path    = realpath( $full_path );
	$uploads_real = realpath( $upload_dir['basedir'] );

	if ( false === $real_path || false === $uploads_real || strpos( $real_path, $uploads_real ) !== 0 ) {
		return new WP_Error( 'invalid_path', 'Invalid log path', array( 'status' => 400 ) );
	}

	// Delete the file
	wp_delete_file( $full_path );

	// wp_delete_file doesn't return a value, check if file still exists
	if ( file_exists( $full_path ) ) {
		return new WP_Error( 'delete_failed', 'Failed to delete log file', array( 'status' => 500 ) );
	}

	return rest_ensure_response(
		array(
			'success' => true,
			'message' => 'Log file deleted successfully.',
		)
	);
}

/**
 * Write a test log entry.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response|WP_Error
 */
function de_test_error_logs( WP_REST_Request $request ) {
	$log_path   = $request->get_param( 'log_path' );
	$message    = $request->get_param( 'message' );
	$upload_dir = wp_upload_dir();

	// Build full path
	$full_path = $upload_dir['basedir'] . '/' . $log_path;

	// Get the directory from the path
	$log_dir = dirname( $full_path );

	// Create directory if it doesn't exist
	if ( ! file_exists( $log_dir ) ) {
		wp_mkdir_p( $log_dir );
	}

	// Security: Ensure path is within uploads directory
	$log_dir_real = realpath( $log_dir );
	$uploads_real = realpath( $upload_dir['basedir'] );

	if ( false === $log_dir_real || false === $uploads_real || strpos( $log_dir_real, $uploads_real ) !== 0 ) {
		return new WP_Error( 'invalid_path', 'Invalid log path', array( 'status' => 400 ) );
	}

	// Format the log entry
	$log_timezone        = '';
	$log_timezone_string = get_option( 'timezone_string' );
	$log_time            = current_time( '[d-M-Y H:i:s' );

	if ( empty( $log_timezone_string ) ) {
		$log_timezone = ']';
	} else {
		$log_timezone = ' ' . esc_html( $log_timezone_string ) . ']';
	}

	$new_entry = $log_time . $log_timezone . ' [TEST] ' . sanitize_text_field( $message );

	// Prepend new entry to existing content (newest first)
	if ( file_exists( $full_path ) ) {
		$file_content = file_get_contents( $full_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		file_put_contents( $full_path, $new_entry . "\n\n" . $file_content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	} else {
		file_put_contents( $full_path, $new_entry . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	return rest_ensure_response(
		array(
			'success' => true,
			'message' => 'Test log entry written successfully.',
		)
	);
}
