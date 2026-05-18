<?php

if (!defined('ABSPATH')) exit;

/**
 * Multiple Divi Engine plugins ship this file; only define and bootstrap once.
 */
if (!class_exists('DiviEngine_License_REST_Endpoints', false)) {

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

        register_rest_route(
            'de/v1',
            '/force-remove-license',
            array(
                'methods'             => 'POST',
                'callback'            => array($this, 'force_remove_license_key'),
                'permission_callback' => array($this, 'check_admin_permission'),
            )
        );

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
                'message' => __('License key is missing.', 'download-now-for-woocommerce')
            ));
        }

        try {
            $result = DiviEngine_License_REST_Endpoints::validate_remote_license($license_key, $plugin_id);

            if (!is_array($result)) {
                throw new Exception(__('Unexpected response format.', 'download-now-for-woocommerce'));
            }

            return rest_ensure_response($result);
        } catch (Exception $e) {
            return rest_ensure_response(array(
                'success' => false,
                'message' => __('An error occurred during license validation.', 'download-now-for-woocommerce')
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
                'message' => __('License key or plugin code is missing.', 'download-now-for-woocommerce')
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
                'message' => __('Failed to connect to the license server.', 'download-now-for-woocommerce')
            ));
        }

        $response_block = json_decode($data['body']);

        if (!empty($response_block)) {
            $response_block = end($response_block);

            if (isset($response_block->status) && $response_block->status === 'success') {
                $license_data = $class_name::get_licence_data();
                if (! is_array($license_data)) {
                    $license_data = array();
                }
                $license_data['key'] = '';
                $class_name::update_licence_data($license_data);

                return rest_ensure_response(array(
                    'success' => true,
                    'message' => __('License deactivated successfully.', 'download-now-for-woocommerce')
                ));
            }

            return rest_ensure_response(array(
                'success' => false,
                'message' => $response_block->message ?? __('License deactivation failed.', 'download-now-for-woocommerce')
            ));
        }

        return rest_ensure_response(array(
            'success' => false,
            'message' => __('Unexpected response from the license server.', 'download-now-for-woocommerce')
        ));
    }

    /**
     * Clear the license key locally without contacting the license server.
     *
     * @param WP_REST_Request $request Request with JSON body { "plugin": "divi-machine" } (settings plugin slug).
     * @return WP_REST_Response
     */
    public function force_remove_license_key($request) {
        $params      = $request->get_json_params();
        $plugin_slug = isset($params['plugin']) ? sanitize_key((string) $params['plugin']) : '';

        if ($plugin_slug === '') {
            return rest_ensure_response(array(
                'success' => false,
                'message' => __('Plugin identifier is missing.', 'download-now-for-woocommerce'),
            ));
        }

        $plugin_ids = apply_filters('divi_engine_plugin_ids', array());
        $plugin_id  = isset($plugin_ids[ $plugin_slug ]) ? (string) $plugin_ids[ $plugin_slug ] : '';

        // Embedded Ajax Filter often registers the API prefix under "daf" only.
        if ($plugin_id === '' && 'divi-ajax-filter' === $plugin_slug) {
            $plugin_id = isset($plugin_ids['daf']) ? (string) $plugin_ids['daf'] : '';
        }

        if ($plugin_id === '' || ! defined($plugin_id . '_PRODUCT_ID')) {
            return rest_ensure_response(array(
                'success' => false,
                'message' => __('Unknown plugin for license removal.', 'download-now-for-woocommerce'),
            ));
        }

        $class_name = $plugin_id . '_LICENSE';

        if (! class_exists($class_name) || ! is_callable(array($class_name, 'get_licence_data')) || ! is_callable(array($class_name, 'update_licence_data'))) {
            return rest_ensure_response(array(
                'success' => false,
                'message' => __('License storage for this plugin is not available.', 'download-now-for-woocommerce'),
            ));
        }

        $license_data = $class_name::get_licence_data();
        $current_key  = '';

        if (is_array($license_data) && isset($license_data['key'])) {
            $current_key = (string) $license_data['key'];
        }

        if ($current_key === '') {
            return rest_ensure_response(array(
                'success' => false,
                'message' => __('No license key found to remove.', 'download-now-for-woocommerce'),
            ));
        }

        if (! is_array($license_data)) {
            $license_data = array();
        }
        $license_data['key'] = '';
        $class_name::update_licence_data($license_data);

        return rest_ensure_response(array(
            'success' => true,
            'message' => __('License key removed locally. You can now add a new license key.', 'download-now-for-woocommerce'),
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
                    'description' => esc_html__('Your license is active. You can deactivate it if needed.', 'download-now-for-woocommerce'),
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
                    'description' => esc_html__('Keep your site updated and secure by entering your license key.', 'download-now-for-woocommerce'),
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
                'message' => __('Failed to connect to license server.', 'download-now-for-woocommerce'),
            );
        }

        $response_block = json_decode($data['body']);
        if (!is_array($response_block)) {
            return array(
                'success' => false,
                'message' => __('Invalid response from license server.', 'download-now-for-woocommerce'),
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
                'message' => __('License activated successfully.', 'download-now-for-woocommerce'),
                'formatted_key' => $formatted_key,
            );
        }

        return array(
            'success' => false,
            'message' => $last_response->message ?? __('License activation failed.', 'download-now-for-woocommerce'),
        );
    }

}

} // class_exists DiviEngine_License_REST_Endpoints

if (!defined('DE_DIVI_ENGINE_LICENSE_REST_ENDPOINTS_INIT')) {
    define('DE_DIVI_ENGINE_LICENSE_REST_ENDPOINTS_INIT', true);
    new DiviEngine_License_REST_Endpoints();
}
