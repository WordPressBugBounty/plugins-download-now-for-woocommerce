<?php

if (!defined('ABSPATH')) exit;

class DiviEngine_REST_Endpoints {

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_endpoints'));
    }

    public function register_endpoints() {

        register_rest_route('de/v1', '/product-categories', array(
            'methods' => 'GET',
            'callback' => function () {
                $terms = get_terms(array(
                    'taxonomy' => 'product_cat',
                    'hide_empty' => false,
                ));
                $result = array_map(function($term) {
                    return [
                        'id' => $term->term_id,
                        'name' => $term->name,
                        'slug' => $term->slug,
                    ];
                }, $terms);
                return rest_ensure_response($result);
            },
            'permission_callback' => '__return_true', // Public, or use your own permission callback
        ));

        register_rest_route('de/v1', '/incomplete-achievements', array(
            'methods' => 'GET',
            'callback' => function () {
                $incomplete_key = 'de_incomplete_achievements';
                $incomplete_achievements = get_option($incomplete_key, []);

                // Ensure achievements are an array
                if (!is_array($incomplete_achievements)) {
                    $incomplete_achievements = [];
                }

                // Fetch metadata dynamically from achievements.json
                $achievements_metadata_path = dirname(__DIR__, 3) . '/src/specific/achievements.json';
                $achievements_metadata = json_decode(file_get_contents($achievements_metadata_path), true);

                if (!is_array($achievements_metadata)) {
                    $achievements_metadata = [];
                }

                $achievements_metadata = array_column($achievements_metadata, null, 'id');

                // Remove achievements from the database if they are no longer in achievements.json
                $incomplete_achievements = array_filter($incomplete_achievements, function ($achievement_id) use ($achievements_metadata) {
                    return isset($achievements_metadata[$achievement_id]);
                });

                // Add new achievements from metadata to the database if not already present
                foreach (array_keys($achievements_metadata) as $achievement_id) {
                    if (!in_array($achievement_id, $incomplete_achievements)) {
                        $incomplete_achievements[] = $achievement_id;
                    }
                }

                // Get license-related achievements
                $licenses = apply_filters("divi_engine_license_data", array());
                $plugins = apply_filters("divi_engine_plugins", array());

                $license_achievements = array();

                foreach ($licenses as $plugin => $license) {
                    if (in_array($plugin, array_keys($plugins)) && $license == "") {
                        $license_achievements[] = $plugin;
                        if (!in_array($plugin, $incomplete_achievements)) {
                            $incomplete_achievements[] = $plugin;
                        }
                    }
                }

                update_option($incomplete_key, $incomplete_achievements);

                $database_achievements = array_map(function ($achievement_id) use ($achievements_metadata) {
                    return $achievements_metadata[$achievement_id] ?? null;
                }, $incomplete_achievements);

                // Filter out null values (unknown achievements)
                $database_achievements = array_filter($database_achievements);

                // Merge database achievements and license achievements
                $result = array_merge($database_achievements, array_map(function ($plugin) use ($plugins) {
                    return [
                        'plugin' => $plugin,
                        'name' => $plugins[$plugin] . esc_html__(' License Key', '__DE_SETTINGS_TD__'),
                        'status' => 'Incomplete',
                        'href' => '/wp-admin/admin.php?page=divi-engine-settings#license',
                        'description' => esc_html__('Enter your license key to activate updates and features.', '__DE_SETTINGS_TD__'),
                    ];
                }, $license_achievements));

                return rest_ensure_response($result);
            },
            'permission_callback' => '__return_true',
        ));

        register_rest_route('de/v1', '/completed-achievements', array(
            'methods' => 'GET',
            'callback' => function () {
                $complete_key = 'de_complete_achievements';
                $completed_achievements = get_option($complete_key, []);

                // Ensure achievements are an array
                if (!is_array($completed_achievements)) {
                    $completed_achievements = [];
                }

                // Fetch metadata dynamically from achievements.json
                $achievements_metadata_path = dirname(__DIR__, 3) . '/src/specific/achievements.json';
                $achievements_metadata = json_decode(file_get_contents($achievements_metadata_path), true);

                if (!is_array($achievements_metadata)) {
                    $achievements_metadata = [];
                }

                $achievements_metadata = array_column($achievements_metadata, null, 'id');

                // Map completed achievements to their metadata
                if (empty($completed_achievements)) {
                    return rest_ensure_response([]);
                }

                $result = array_map(function ($achievement_id) use ($achievements_metadata) {
                    return $achievements_metadata[$achievement_id] ?? null;
                }, $completed_achievements);

                // Filter out null values (unknown achievements)
                $result = array_filter($result);

                return rest_ensure_response($result);
            },
            'permission_callback' => '__return_true',
        ));
    }
}

new DiviEngine_REST_Endpoints();
