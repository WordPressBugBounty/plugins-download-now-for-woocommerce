<?php

if (!defined('ABSPATH')) exit;

class DiviEngine_License_REST_Endpoints {

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_endpoints'));
    }

    public static function hide_license_key($license_key) {
        if (strlen($license_key) <= 20) {
            return $license_key; // No masking needed for short keys
        }
        return substr($license_key, 0, 20) . '-xxx-xxxxxxxx';
    }


    public function register_endpoints() {
        register_rest_route('de/v1', '/validate-license', array(
            'methods' => 'POST',
            'callback' => array($this, 'validate_license_key'),
            'permission_callback' => array($this, 'check_admin_permission'),
        ));

        register_rest_route('de/v1', '/deactivate-license', array(
            'methods' => 'POST',
            'callback' => array($this, 'deactivate_license_key'),
            'permission_callback' => array($this, 'check_admin_permission'),
        ));
        register_rest_route('de/v1', '/get-licenses', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_missing_licenses'),
            'permission_callback' => array($this, 'check_admin_permission'),
        ));
    }

    /**
     * Check if user has admin permissions
     */
    public function check_admin_permission() {
        return current_user_can('manage_options');
    }

    public function validate_license_key($request) {
        $params = $request->get_json_params();
        $license_key = $params['license_key'] ?? '';
        $plugin_id = $params['plugin_id'];

        if (empty($license_key) || empty($plugin_id)) {
            return rest_ensure_response(array(
                'success' => false,
                'message' => __('License key is missing.', '__DE_SETTINGS_TD__')
            ));
        }

        try {
            $result = DiviEngine_License_REST_Endpoints::validate_remote_license($license_key, $plugin_id);

            if (!is_array($result)) {
                throw new Exception(__('Unexpected response format.', '__DE_SETTINGS_TD__'));
            }

            return rest_ensure_response($result);
        } catch (Exception $e) {
            return rest_ensure_response(array(
                'success' => false,
                'message' => __('An error occurred during license validation.', '__DE_SETTINGS_TD__')
            ));
        }
    }

    public function deactivate_license_key($request) {
        $params = $request->get_json_params();

        $plugin_id = $params['plugin_id'];
        
        $class_name = $plugin_id . '_LICENSE';
        $product_id = constant($plugin_id . '_PRODUCT_ID');
        $instance = constant($plugin_id . '_INSTANCE');
        $api_url = constant($plugin_id . '_APP_API_URL');

        $license_key = $class_name::get_licence_data()['key'] ?? '';
        $plugin_id = $params['plugin_id'];

        if (empty($license_key) || empty($plugin_id)) {
            return rest_ensure_response(array(
                'success' => false,
                'message' => __('License key or plugin code is missing.', '__DE_SETTINGS_TD__')
            ));
        }

        $args = array(
            'woo_sl_action' => 'deactivate',
            'licence_key' => $license_key,
            'product_unique_id' => $product_id,
            'domain' => $instance
        );

        $request_uri = $api_url . '?' . http_build_query($args, '', '&');
        $data = wp_remote_get($request_uri);

        if (is_wp_error($data) || $data['response']['code'] != 200) {
            return rest_ensure_response(array(
                'success' => false,
                'message' => __('Failed to connect to the license server.', '__DE_SETTINGS_TD__')
            ));
        }

        $response_block = json_decode($data['body']);

        if (!empty($response_block)) {
            $response_block = end($response_block);

            if (isset($response_block->status) && $response_block->status === 'success') {
                // Clear the license key locally
                $license_data = get_option('divi_fb_license', []);
                $license_data['key'] = '';
                update_option('divi_fb_license', $license_data);

                return rest_ensure_response(array(
                    'success' => true,
                    'message' => __('License deactivated successfully.', '__DE_SETTINGS_TD__')
                ));
            }

            return rest_ensure_response(array(
                'success' => false,
                'message' => $response_block->message ?? __('License deactivation failed.', '__DE_SETTINGS_TD__')
            ));
        }

        return rest_ensure_response(array(
            'success' => false,
            'message' => __('Unexpected response from the license server.', '__DE_SETTINGS_TD__')
        ));
    }

    public function get_missing_licenses() {
        $licenses = apply_filters("divi_engine_license_data", array());
        $plugins = apply_filters("divi_engine_plugins", array());
        $plugin_ids = apply_filters("divi_engine_plugin_ids", array());
       
        $all_licenses = array();

        foreach ($plugins as $plugin => $name) {

            $plugin_id = $plugin_ids[$plugin] ?? '';

            if (isset($licenses[$plugin]) && $licenses[$plugin] !== "") {

                $masked_license = self::hide_license_key($licenses[$plugin]);

                $all_licenses[] = array(
                    'plugin' => $plugin,
                    'name' => $name,
                    'status' => 'Active',
                    'licenseKey' => $masked_license,
                    'action' => 'Deactivate',
                    'href' => '#divi-engine-license-settings',
                    'description' => esc_html__('Your license is active. You can deactivate it if needed.', '__DE_SETTINGS_TD__'),
                    'plugin_id' => $plugin_id,
                );
            } else {
                $all_licenses[] = array(
                    'plugin' => $plugin,
                    'name' => $name,
                    'status' => 'Incomplete',
                    'licenseKey' => '',
                    'action' => 'Validate',
                    'href' => '#divi-engine-license-settings',
                    'description' => esc_html__('Keep your site updated and secure by entering your license key.', '__DE_SETTINGS_TD__'),
                    'plugin_id' => $plugin_id,
                );
            }
        }

        return $all_licenses;
    }

    public static function validate_remote_license($license_key, $plugin_id) {
               
        $class_name = $plugin_id . '_LICENSE';
        $product_id = constant($plugin_id . '_PRODUCT_ID');
        $instance = constant($plugin_id . '_INSTANCE');
        $api_url = constant($plugin_id . '_APP_API_URL');

        $args = array(
            'woo_sl_action'     => 'activate',
            'licence_key'       => $license_key,
            'product_unique_id' => $product_id,
            'domain'            => $instance,
        );

        $request_uri = $api_url . '?' . http_build_query($args, '', '&');
        $data = wp_remote_get($request_uri);

        if (is_wp_error($data) || empty($data['body'])) {
            return array(
                'success' => false,
                'message' => __('Failed to connect to license server.', '__DE_SETTINGS_TD__'),
            );
        }

        $response_block = json_decode($data['body']);
        if (!is_array($response_block)) {
            return array(
                'success' => false,
                'message' => __('Invalid response from license server.', '__DE_SETTINGS_TD__'),
            );
        }

        $last_response = end($response_block);

        if (isset($last_response->status) && $last_response->status === 'success') {
            // Save license locally
            $license_data = array(
                'key'        => $license_key,
                'last_check' => time(),
            );
            $class_name::update_licence_data($license_data);

            $formatted_key = self::hide_license_key($license_key);

            return array(
                'success' => true,
                'message' => __('License activated successfully.', '__DE_SETTINGS_TD__'),
                'formatted_key' => $license_key,
            );
        }

        return array(
            'success' => false,
            'message' => $last_response->message ?? __('License activation failed.', '__DE_SETTINGS_TD__'),
        );
    }

}

new DiviEngine_License_REST_Endpoints();
