<?php

if (!defined('ABSPATH')) exit;

// Make sure the upgrader skin base class is loaded.
if ( ! class_exists( 'WP_Upgrader_Skin' ) ) {
    // Newer WP versions split this into its own file.
    if ( file_exists( ABSPATH . 'wp-admin/includes/class-wp-upgrader-skin.php' ) ) {
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader-skin.php';
    } else {
        // Fallback for older WP where it's inside class-wp-upgrader.php
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    }
}

if ( ! class_exists( 'WPE_Silent_Upgrader_Skin' ) && class_exists( 'WP_Upgrader_Skin' ) ) {
    class WPE_Silent_Upgrader_Skin extends WP_Upgrader_Skin {
        public function header() {}
        public function footer() {}
        public function feedback( $string, ...$args ) {}
        public function before() {}
        public function after() {}
    }
}

class WPEnhanced_REST_Endpoints {

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_endpoints'));
    }

    public function register_endpoints() {
        register_rest_route('wpe/v1', '/incomplete-achievements', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_incomplete_achievements'),
            'permission_callback' => function() { return current_user_can('manage_options'); },
        ));

        register_rest_route('wpe/v1', '/completed-achievements', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_completed_achievements'),
            'permission_callback' => function() { return current_user_can('manage_options'); },
        ));

        register_rest_route('wpe/v1', '/plugins-status', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_plugins_status'),
            'permission_callback' => function() { return current_user_can('manage_options'); },
        ));

        register_rest_route('wpe/v1', '/plugin-action', array(
            'methods' => 'POST',
            'callback' => array($this, 'plugin_action'),
            'permission_callback' => function() { return current_user_can('install_plugins') && current_user_can('activate_plugins'); },
            'args' => array(
                'slug' => array(), // not required globally, only for install
                'action' => array( 'required' => true ), // 'install' or 'activate'
                'plugin_file' => array(), // required for activate
            ),
        ));
    }

    public function get_incomplete_achievements() {
        $incomplete = get_option('wpe_incomplete_achievements', array());
        
        $licenses = apply_filters("wp_enhanced_license_data", array());
        $plugins = apply_filters("wp_enhanced_plugins", array());

        $license_achievements = array();
        foreach ($plugins as $plugin => $name) {
            $is_complete = isset($licenses[$plugin]) && $licenses[$plugin] !== "";
            
            if (!$is_complete) {
                $license_achievements[] = array(
                    'name' => $name,
                    'href' => '#wp-enhanced-license-settings',
                    'icon' => 'License',
                    'description' => 'Enter your license key to enable updates.',
                );
            }
        }

        $all_incomplete = array_merge($license_achievements, $incomplete);

        return rest_ensure_response($all_incomplete);
    }

    public function get_completed_achievements() {
        $completed = get_option('wpe_complete_achievements', array());
        
        $licenses = apply_filters("wp_enhanced_license_data", array());
        $plugins = apply_filters("wp_enhanced_plugins", array());

        $license_achievements = array();
        foreach ($plugins as $plugin => $name) {
            $is_complete = isset($licenses[$plugin]) && $licenses[$plugin] !== "";
            
            if ($is_complete) {
                $license_achievements[] = array(
                    'name' => $name,
                    'href' => '#wp-enhanced-license-settings',
                    'icon' => 'License',
                    'description' => 'License is active.',
                );
            }
        }

        $all_completed = array_merge($license_achievements, $completed);

        return rest_ensure_response($all_completed);
    }

    public function get_plugins_status(WP_REST_Request $req) {
        $plugins_to_check = [
            [
                'slug' => 'download-now-for-woocommerce/som-woocommerce-download-now.php',
                'name' => 'Download Now for WooCommerce',
                'type' => 'free',
            ],
            // Free Downloads for WooCommerce (support both possible plugin files for free version)
            [
                'slug' => 'free-downloads-woocommerce/free-downloads-woocommerce.php',
                'name' => 'Free Downloads for WooCommerce',
                'type' => 'free',
            ],
            [
                'slug' => 'free-downloads-woocommerce-pro/som-woocommerce-download-now.php',
                'name' => 'Free Downloads for WooCommerce',
                'type' => 'free',
            ],
            [
                'slug' => 'free-downloads-woocommerce-pro/free-downloads-woocommerce-pro.php',
                'name' => 'Free Downloads WooCommerce Pro',
                'type' => 'pro',
            ],
            [
                'slug' => 'frontend-reset-password/frontend-reset-password.php',
                'name' => 'Frontend Reset Password',
                'type' => 'free',
            ],
            [
                'slug' => 'frontend-reset-password/som-frontend-reset-password.php',
                'name' => 'Frontend Reset Password',
                'type' => 'free',
            ],
            [
                'slug' => 'frontend-reset-password-d5/frontend-reset-password-d5.php',
                'name' => 'Frontend Reset Password',
                'type' => 'pro',
            ],
        ];
        $all_plugins = get_plugins();
        
        $active_plugins = (array) get_option('active_plugins', []);
        // Check if pro version of Free Downloads is active
        $pro_fdwc_slug = 'free-downloads-woocommerce-pro/free-downloads-woocommerce-pro.php';
        $pro_fdwc_active = isset($all_plugins[$pro_fdwc_slug]) && in_array($pro_fdwc_slug, $active_plugins);
        // Check if pro version of frontend-reset-password is active
        $pro_frp_slug = 'frontend-reset-password-d5/frontend-reset-password-d5.php';
        $pro_frp_active = isset($all_plugins[$pro_frp_slug]) && in_array($pro_frp_slug, $active_plugins);
        $result = [];
        foreach ($plugins_to_check as $plugin) {
            $slug = $plugin['slug'];
            $status = 'not-installed';
            if (isset($all_plugins[$slug])) {
                $status = in_array($slug, $active_plugins) ? 'active' : 'inactive';
            }
            // Hide free version if pro is active for Free Downloads (for both possible free slugs)
            if ((
                $plugin['slug'] === 'free-downloads-woocommerce/free-downloads-woocommerce.php' ||
                $plugin['slug'] === 'free-downloads-woocommerce-pro/som-woocommerce-download-now.php'
            ) && $pro_fdwc_active) {
                continue;
            }
            // Hide free version if pro is active for frontend-reset-password
            if ($plugin['slug'] === 'frontend-reset-password/frontend-reset-password.php' && $pro_frp_active) {
                continue;
            }
            // For frontend-reset-password, if any of the free or pro slugs is active, mark as active
            if ($plugin['name'] === 'Frontend Reset Password') {
                if (
                    (isset($all_plugins['frontend-reset-password/frontend-reset-password.php']) && in_array('frontend-reset-password/frontend-reset-password.php', $active_plugins)) ||
                    (isset($all_plugins['frontend-reset-password/som-frontend-reset-password.php']) && in_array('frontend-reset-password/som-frontend-reset-password.php', $active_plugins)) ||
                    (isset($all_plugins['frontend-reset-password-d5/frontend-reset-password-d5.php']) && in_array('frontend-reset-password-d5/frontend-reset-password-d5.php', $active_plugins))
                ) {
                    $status = 'active';
                }
            }
            $result[] = [
                'slug' => $slug,
                'name' => $plugin['name'],
                'status' => $status,
            ];
        }
        return rest_ensure_response($result);
    }

    public function plugin_action(WP_REST_Request $req) {
        $slug = sanitize_text_field($req->get_param('slug'));
        $action = sanitize_text_field($req->get_param('action'));
        $plugin_file = $req->get_param('plugin_file') ? sanitize_text_field($req->get_param('plugin_file')) : '';

        if (empty($action)) {
            return new WP_Error('missing_params', 'Missing action', ['status' => 400]);
        }
        if ($action === 'install' && empty($slug)) {
            return new WP_Error('missing_params', 'Missing slug for install', ['status' => 400]);
        }
        if ($action === 'activate' && empty($plugin_file)) {
            return new WP_Error('missing_plugin_file', 'Missing plugin_file for activation', ['status' => 400]);
        }

        if ($action === 'install') {
            include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
            include_once ABSPATH . 'wp-admin/includes/file.php';
            include_once ABSPATH . 'wp-admin/includes/misc.php';
            include_once ABSPATH . 'wp-admin/includes/plugin.php';

            $api = plugins_api('plugin_information', [ 'slug' => $slug, 'fields' => [ 'sections' => false ] ]);
            if (is_wp_error($api)) {
                return $api;
            }

            $upgrader = new Plugin_Upgrader(new WPE_Silent_Upgrader_Skin());
            $result = $upgrader->install($api->download_link);

            $plugin_file = $upgrader->plugin_info();
            
            $response = [];
            if (is_wp_error($result)) {
                if ($result->get_error_code() === 'folder_exists') {
                    $response = [
                        'installed' => false,
                        'main_file' => $plugin_file,
                        'message' => 'Plugin folder already exists. Please activate the plugin or delete the folder and try again.'
                    ];
                } else {
                    $response = [
                        'installed' => false,
                        'main_file' => $plugin_file,
                        'message' => $result->get_error_message()
                    ];
                }
            } else {
                $response = [
                    'installed' => true,
                    'main_file' => $plugin_file,
                    'message' => 'Plugin installed successfully.'
                ];
            }

            return rest_ensure_response($response);
        }

        if ($action === 'activate') {
            if (empty($plugin_file)) {
                return new WP_Error('missing_plugin_file', 'Missing plugin_file for activation', ['status' => 400]);
            }
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
            $result = activate_plugin($plugin_file);
            if (is_wp_error($result)) {
                if ($result->get_error_code() === 'plugin_already_active') {
                    return rest_ensure_response([ 'activated' => true, 'message' => 'Plugin is already active.' ]);
                }
                return $result;
            }
            return rest_ensure_response([ 'activated' => true, 'message' => 'Plugin activated successfully.' ]);
        }

        return new WP_Error('invalid_action', 'Invalid action', ['status' => 400]);
    }

}

new WPEnhanced_REST_Endpoints();