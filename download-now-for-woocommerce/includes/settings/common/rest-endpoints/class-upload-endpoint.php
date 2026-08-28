<?php
/**
 * Settings File Upload REST Endpoint
 *
 * Provides a file upload endpoint that saves files to the WordPress media library
 * WITHOUT generating responsive image sub-sizes. This avoids PHP memory/processing
 * errors on servers with limited resources when uploading logos, icons, etc.
 *
 * The standard /wp/v2/media endpoint always generates sub-sizes and can fail with
 * "The web server cannot generate responsive image sizes" on large images.
 *
 * @package Settings_Framework
 * @since 2.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', 'de_register_upload_routes' );

/**
 * Register upload REST routes.
 *
 * Routes are registered under both namespaces (de/v1 and wpe/v1) for compatibility.
 */
function de_register_upload_routes() {
	$namespaces = array( 'de/v1', 'wpe/v1' );

	foreach ( $namespaces as $namespace ) {
		register_rest_route(
			$namespace,
			'/upload',
			array(
				'methods'             => 'POST',
				'callback'            => 'de_handle_settings_upload',
				'permission_callback' => 'de_upload_permission_check',
			)
		);
	}
}

/**
 * Check if user has permission to upload files.
 *
 * @return bool
 */
function de_upload_permission_check() {
	return current_user_can( 'upload_files' );
}

/**
 * Handle file upload to the WordPress media library.
 *
 * Accepts a multipart form upload, saves it to the media library, and returns the
 * attachment URL and ID. Sub-size (thumbnail) generation is skipped to avoid server errors.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response|WP_Error
 */
function de_handle_settings_upload( WP_REST_Request $request ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$files = $request->get_file_params();

	if ( empty( $files['file'] ) ) {
		return new WP_Error(
			'no_file',
			'No file was uploaded.',
			array( 'status' => 400 )
		);
	}

	$file = $files['file'];

	if ( ! empty( $file['error'] ) ) {
		$php_errors = array(
			UPLOAD_ERR_INI_SIZE   => 'The file exceeds the server upload size limit.',
			UPLOAD_ERR_FORM_SIZE  => 'The file exceeds the maximum upload size.',
			UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded.',
			UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
			UPLOAD_ERR_NO_TMP_DIR => 'Server configuration error: missing temp directory.',
			UPLOAD_ERR_CANT_WRITE => 'Server error: failed to write file to disk.',
			UPLOAD_ERR_EXTENSION  => 'A server extension stopped the file upload.',
		);
		$message = isset( $php_errors[ $file['error'] ] ) ? $php_errors[ $file['error'] ] : 'Unknown upload error.';
		return new WP_Error( 'upload_error', $message, array( 'status' => 400 ) );
	}

	$wp_filetype = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
	if ( ! $wp_filetype['type'] ) {
		return new WP_Error(
			'invalid_file_type',
			'This file type is not allowed.',
			array( 'status' => 400 )
		);
	}

	$overrides = array(
		'test_form' => false,
		'test_type' => true,
	);

	$uploaded = wp_handle_upload( $file, $overrides );

	if ( isset( $uploaded['error'] ) ) {
		return new WP_Error(
			'upload_failed',
			$uploaded['error'],
			array( 'status' => 500 )
		);
	}

	$attachment = array(
		'post_mime_type' => $uploaded['type'],
		'post_title'     => preg_replace( '/\.[^.]+$/', '', sanitize_file_name( $file['name'] ) ),
		'post_content'   => '',
		'post_status'    => 'inherit',
	);

	$attach_id = wp_insert_attachment( $attachment, $uploaded['file'] );

	if ( is_wp_error( $attach_id ) ) {
		return new WP_Error(
			'attachment_failed',
			'Failed to create media library entry.',
			array( 'status' => 500 )
		);
	}

	$skip_subsizes = function () {
		return array();
	};
	add_filter( 'intermediate_image_sizes_advanced', $skip_subsizes, 999 );

	$attach_data = wp_generate_attachment_metadata( $attach_id, $uploaded['file'] );
	wp_update_attachment_metadata( $attach_id, $attach_data );

	remove_filter( 'intermediate_image_sizes_advanced', $skip_subsizes, 999 );

	$url = wp_get_attachment_url( $attach_id );

	return rest_ensure_response(
		array(
			'success'       => true,
			'url'           => $url,
			'attachment_id' => $attach_id,
			'type'          => $uploaded['type'],
		)
	);
}
