<?php

if (!defined('ABSPATH')) exit;

/**
 * Multiple Divi Engine plugins ship this file; guard functions and route registration.
 */
if (!function_exists('de_option_name_for')) {
function de_option_name_for($plugin) {
  $option_name = apply_filters("de_option_name_{$plugin}", null);
  
  if (!$option_name) {
    $option_name = "{$plugin}_options";
  }
  
  return $option_name;
}
}

if (!function_exists('de_defaults_for')) {
function de_defaults_for($plugin) {
  $defaults = apply_filters("de_settings_defaults_{$plugin}", array(), $plugin);
  return $defaults;
}
}

if (!function_exists('de_get_plugin_settings')) {
function de_get_plugin_settings(WP_REST_Request $req) {
  $plugin = $req['plugin'];
  $opt    = de_option_name_for($plugin);
  if (!$opt) return new WP_Error('invalid_plugin', 'Unknown plugin', ['status' => 400]);
  $defaults = de_defaults_for($plugin);
  $saved    = get_option($opt, []);
  return rest_ensure_response( wp_parse_args( (array)$saved, $defaults ) );
}
}

if (!function_exists('de_update_plugin_settings')) {
function de_update_plugin_settings(WP_REST_Request $req) {
  $plugin = $req['plugin'];
  $opt    = de_option_name_for($plugin);
  if (!$opt) return new WP_Error('invalid_plugin', 'Unknown plugin', ['status' => 400]);

  $payload = $req->get_json_params();
  if ( ! is_array( $payload ) || ! $payload ) {
    $payload = $req->get_body_params();
  }
  $payload = is_array( $payload ) ? $payload : array();
  if ( isset( $payload['settings'] ) && is_array( $payload['settings'] ) ) {
    $payload = array_merge( $payload['settings'], $payload );
    unset( $payload['settings'] );
  }
  $clean   = de_sanitize_settings($plugin, $payload);

  // merge with existing so you support partial updates if you want
  $existing = (array) get_option($opt, []);
  $to_save  = array_replace($existing, $clean);

  // Do NOT autoload (admin-only)
  update_option($opt, $to_save, false);

  return rest_ensure_response($to_save);
}
}

if (!function_exists('de_sanitize_settings')) {
function de_sanitize_settings($plugin, $data) {
  $sanitized = apply_filters("de_settings_sanitize_{$plugin}", array(), $data, $plugin);
  return $sanitized;
}
}

if (!defined('DE_DIVI_ENGINE_SETTINGS_REST_ENDPOINT_INIT')) {
  define('DE_DIVI_ENGINE_SETTINGS_REST_ENDPOINT_INIT', true);

  add_action('rest_api_init', function () {
    register_rest_route('de/v1', '/settings/(?P<plugin>[a-z0-9\-]+)', [
      [
        'methods'  => 'GET',
        'callback' => 'de_get_plugin_settings',
        'permission_callback' => function() { return current_user_can('manage_options'); },
      ],
      [
        'methods'  => 'PUT',
        'callback' => 'de_update_plugin_settings',
        'permission_callback' => function() { return current_user_can('manage_options'); },
      ],
    ]);
  });
}
