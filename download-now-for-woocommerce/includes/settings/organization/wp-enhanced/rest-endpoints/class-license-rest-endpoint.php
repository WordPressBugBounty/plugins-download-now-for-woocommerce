<?php

if (!defined('ABSPATH')) exit;

class WPEnhanced_License_REST_Endpoints {

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_endpoints'));
    }

    public static function hide_license_key($license_key) {
        if (strlen($license_key) <= 20) {
            return $license_key;
        }
        return substr($license_key, 0, 20) . '-xxx-xxxxxxxx';
    }

    public function register_endpoints() {
        register_rest_route('wpe/v1', '/validate-license', array(
            'methods' => 'POST',
            'callback' => array($this, 'validate_license_key'),
            'permission_callback' => array($this, 'check_admin_permission'),
        ));

        register_rest_route('wpe/v1', '/deactivate-license', array(
            'methods' => 'POST',
            'callback' => array($this, 'deactivate_license_key'),
            'permission_callback' => array($this, 'check_admin_permission'),
        ));

        register_rest_route('wpe/v1', '/force-remove-license', array(
            'methods' => 'POST',
            'callback' => array($this, 'force_remove_license_key'),
            'permission_callback' => array($this, 'check_admin_permission'),
        ));

        register_rest_route('wpe/v1', '/get-licenses', array(
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
        $plugin = $params['plugin'] ?? '';

        if (empty($license_key) || empty($plugin_id)) {
            return rest_ensure_response(array(
                'success' => false,
                'message' => __('License key is missing.', 'download-now-for-woocommerce')
            ));
        }

        try {
            $result = WPEnhanced_License_REST_Endpoints::validate_remote_license($license_key, $plugin_id, $plugin);

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
        $plugin = $params['plugin'] ?? '';
        
        $store_url = apply_filters('wp_enhanced_store_url', 'https://wpenhanced.com');

        $licenses = get_option('wp_enhanced_licenses', array());
        $license_key = $licenses[$plugin] ?? '';

        if (empty($license_key) || empty($plugin_id)) {
            return rest_ensure_response(array(
                'success' => false,
                'message' => __('License key or plugin code is missing.', 'download-now-for-woocommerce')
            ));
        }

        $api_params = array(
            'edd_action' => 'deactivate_license',
            'license'    => $license_key,
            'item_id'    => $plugin_id,
            'url'        => home_url()
        );

        $response = wp_remote_post($store_url, array('timeout' => 15, 'sslverify' => false, 'body' => $api_params));

        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            return rest_ensure_response(array(
                'success' => false,
                'message' => __('Failed to connect to the license server.', 'download-now-for-woocommerce')
            ));
        }

        $license_data = json_decode(wp_remote_retrieve_body($response));

        if ($license_data->license == 'deactivated') {
            $licenses[$plugin] = '';
            update_option('wp_enhanced_licenses', $licenses);

            return rest_ensure_response(array(
                'success' => true,
                'message' => __('License deactivated successfully.', 'download-now-for-woocommerce')
            ));
        }

        return rest_ensure_response(array(
            'success' => false,
            'message' => __('License deactivation failed.', 'download-now-for-woocommerce')
        ));
    }

    /**
     * Force remove license key locally without server validation.
     * Useful when server is unreachable, license was already removed, or site was cloned.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function force_remove_license_key($request) {
        $params = $request->get_json_params();
        $plugin = $params['plugin'] ?? '';

        if (empty($plugin)) {
            return rest_ensure_response(array(
                'success' => false,
                'message' => __('Plugin identifier is missing.', 'download-now-for-woocommerce')
            ));
        }

        $licenses = get_option('wp_enhanced_licenses', array());

        if (empty($licenses[$plugin])) {
            return rest_ensure_response(array(
                'success' => false,
                'message' => __('No license key found to remove.', 'download-now-for-woocommerce')
            ));
        }

        $licenses[$plugin] = '';
        update_option('wp_enhanced_licenses', $licenses);

        return rest_ensure_response(array(
            'success' => true,
            'message' => __('License key removed locally. You can now add a new license key.', 'download-now-for-woocommerce')
        ));
    }

    public function get_missing_licenses() {
        $stored_licenses = get_option('wp_enhanced_licenses', array());
        $plugins = apply_filters("wp_enhanced_plugins", array());
        $plugin_ids = apply_filters("wp_enhanced_plugin_ids", array());
       
        $all_licenses = array();

        foreach ($plugins as $plugin => $name) {

            $plugin_id = $plugin_ids[$plugin] ?? '';
            $license_key = $stored_licenses[$plugin] ?? '';

            if (!empty($license_key)) {

                $masked_license = self::hide_license_key($license_key);

                $all_licenses[] = array(
                    'plugin' => $plugin,
                    'name' => $name,
                    'status' => 'Active',
                    'licenseKey' => $masked_license,
                    'action' => 'Deactivate',
                    'href' => '#wp-enhanced-license-settings',
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
                    'href' => '#wp-enhanced-license-settings',
                    'description' => esc_html__('Keep your site updated and secure by entering your license key.', 'download-now-for-woocommerce'),
                    'plugin_id' => $plugin_id,
                );
            }
        }

        return $all_licenses;
    }

    public static function validate_remote_license($license_key, $plugin_id, $plugin) {
               
        $store_url = apply_filters('wp_enhanced_store_url', 'https://wpenhanced.com');

        $api_params = array(
            'edd_action' => 'activate_license',
            'license'    => $license_key,
            'item_id'    => $plugin_id,
            'url'        => home_url()
        );

        $response = wp_remote_post($store_url, array('timeout' => 15, 'sslverify' => false, 'body' => $api_params));

        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            if (is_wp_error($response)) {
                $message = $response->get_error_message();
            } else {
                $message = __('An error occurred, please try again.', 'download-now-for-woocommerce');
            }
            return array(
                'success' => false,
                'message' => $message,
            );
        }

        $license_data = json_decode(wp_remote_retrieve_body($response));

        if (false === $license_data->success) {
            switch($license_data->error) {
                case 'expired' :
                    $message = sprintf(
                        __('Your license key expired on %s.', 'download-now-for-woocommerce'),
                        date_i18n(get_option('date_format'), strtotime($license_data->expires, current_time('timestamp')))
                    );
                    break;

                case 'revoked' :
                    $message = __('Your license key has been disabled.', 'download-now-for-woocommerce');
                    break;

                case 'missing' :
                    $message = __('Invalid license.', 'download-now-for-woocommerce');
                    break;

                case 'invalid' :
                case 'site_inactive' :
                    $message = __('Your license is not active for this URL.', 'download-now-for-woocommerce');
                    break;

                case 'item_name_mismatch' :
                    $message = __('This appears to be an invalid license key.', 'download-now-for-woocommerce');
                    break;

                case 'no_activations_left':
                    $message = __('Your license key has reached its activation limit.', 'download-now-for-woocommerce');
                    break;

                default :
                    $message = __('An error occurred, please try again.', 'download-now-for-woocommerce');
                    break;
            }

            return array(
                'success' => false,
                'message' => $message,
            );
        }

        $licenses = get_option('wp_enhanced_licenses', array());
        $licenses[$plugin] = $license_key;
        update_option('wp_enhanced_licenses', $licenses);

        $formatted_key = self::hide_license_key($license_key);

        return array(
            'success' => true,
            'message' => __('License activated successfully.', 'download-now-for-woocommerce'),
            'formatted_key' => $formatted_key,
        );
    }

}

new WPEnhanced_License_REST_Endpoints();
