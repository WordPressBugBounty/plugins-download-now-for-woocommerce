<?php 

add_action('rest_api_init', function () {
  register_rest_route('wpe/v1', '/settings/(?P<plugin>[a-z0-9\-]+)', [
    [
      'methods'  => 'GET',
      'callback' => 'wpe_get_plugin_settings',
      'permission_callback' => function() { return current_user_can('manage_options'); },
    ],
    [
      'methods'  => 'PUT',
      'callback' => 'wpe_update_plugin_settings',
      'permission_callback' => function() { return current_user_can('manage_options'); },
    ],
  ]);
});

function wpe_option_name_for($plugin) {
  $option_name = apply_filters("wpe_option_name_{$plugin}", null);
  
  if (!$option_name) {
    $option_name = "{$plugin}_options";
  }
  
  return $option_name;
}

function wpe_defaults_for($plugin) {
  $defaults = apply_filters("wpe_settings_defaults_{$plugin}", array(), $plugin);
  return $defaults;
}

function wpe_get_plugin_settings(WP_REST_Request $req) {
  $plugin = $req['plugin'];
  $opt    = wpe_option_name_for($plugin);
  
  if (!$opt) return new WP_Error('invalid_plugin', 'Unknown plugin', ['status' => 400]);
  $defaults = wpe_defaults_for($plugin);
  $saved    = get_option($opt, []);
  
  return rest_ensure_response( wp_parse_args( (array)$saved, $defaults ) );
}

function wpe_update_plugin_settings(WP_REST_Request $req) {
  $plugin = $req['plugin'];
  $opt    = wpe_option_name_for($plugin);
  
  if (!$opt) return new WP_Error('invalid_plugin', 'Unknown plugin', ['status' => 400]);

  $payload = (array) $req->get_json_params();
  $clean   = wpe_sanitize_settings($plugin, $payload);

  // merge with existing so you support partial updates if you want
  $existing = (array) get_option($opt, []);
  $to_save  = array_replace($existing, $clean);
  $result = update_option($opt, $to_save, false);

  return rest_ensure_response($to_save);
}

function wpe_sanitize_settings($plugin, $data) {
  $filter_name = "wpe_settings_sanitize_{$plugin}";
  $has_filter = has_filter($filter_name);
  
  $sanitized = apply_filters($filter_name, $data, $data, $plugin);

  return is_array($sanitized) ? $sanitized : (array) $sanitized;
}
