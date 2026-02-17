<?php
/**
 * Stats REST API Endpoint
 * 
 * Provides REST API endpoints for download statistics and reports.
 * This is a Pro-only feature.
 * 
 * @package FreeDownloadsWooCommerce
 */

namespace SOM\FreeDownloads\REST;

defined('ABSPATH') || exit;

/**
 * Stats Endpoint Class
 */
class Stats_Endpoint {

    /**
     * Namespace for the REST API
     */
    const NAMESPACE = 'somdn/v1';

    /**
     * Initialize the endpoint
     */
    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
        add_action('admin_post_somdn_stats_export', array(__CLASS__, 'handle_export_download'));
    }

    /**
     * Handle stats export as a raw file download via admin-post (avoids REST wrapping that causes ERR_INVALID_RESPONSE).
     */
    public static function handle_export_download() {
        if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to export.', 'free-downloads-woocommerce'), '', array('response' => 403));
        }
        if (empty($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'somdn_stats_export')) {
            wp_die(esc_html__('Security check failed.', 'free-downloads-woocommerce'), '', array('response' => 403));
        }

        $format = isset($_POST['format']) ? sanitize_text_field(wp_unslash($_POST['format'])) : 'xlsx';
        $start_date = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : '';
        $export_type = isset($_POST['export_type']) ? sanitize_text_field(wp_unslash($_POST['export_type'])) : 'all-downloads';
        $export_products = isset($_POST['export_products']) ? wp_unslash($_POST['export_products']) : '';
        $export_columns = isset($_POST['export_columns']) ? wp_unslash($_POST['export_columns']) : '';

        // Default to last 30 days when empty (match overview and fix custom range export)
        if (empty($end_date)) {
            $end_date = gmdate('Y-m-d');
        }
        if (empty($start_date)) {
            $start_date = gmdate('Y-m-d', strtotime('-30 days'));
        }

        if (is_string($export_products)) {
            $export_products = json_decode($export_products, true);
        }
        if (!is_array($export_products)) {
            $export_products = array();
        }
        if (is_string($export_columns)) {
            $export_columns = json_decode($export_columns, true);
        }
        if (!is_array($export_columns)) {
            $export_columns = array();
        }

        if (!function_exists('somdn_get_downloads_data')) {
            wp_die(esc_html__('Export is not available.', 'free-downloads-woocommerce'), '', array('response' => 500));
        }

        $download_args = array(
            'start_date' => $start_date,
            'end_date'   => $end_date,
        );
        $download_data = somdn_get_downloads_data($download_args, $format, $export_type, $export_products);

        if (empty($download_data)) {
            $message = __('No download data found for the selected criteria.', 'free-downloads-woocommerce');
            status_header(404);
            header('Content-Type: application/json; charset=' . get_option('blog_charset'));
            echo wp_json_encode(array('code' => 'no_data', 'message' => $message));
            exit;
        }

        if ($format === 'csv' && function_exists('somdn_generate_test_csv')) {
            somdn_generate_test_csv($download_data, $export_columns);
            exit;
        }
        if (($format === 'xlsx' || $format === 'xls') && function_exists('somdn_generate_xlsx')) {
            if (!class_exists('ZipArchive')) {
                wp_die(
                    esc_html__('Excel export requires the PHP Zip extension. Please enable the "zip" extension in PHP, or use CSV export instead.', 'free-downloads-woocommerce'),
                    esc_html__('Export not available', 'free-downloads-woocommerce'),
                    array('response' => 503)
                );
            }
            somdn_generate_xlsx($download_data, $export_columns);
            exit;
        }

        wp_die(esc_html__('Export format not supported.', 'free-downloads-woocommerce'), '', array('response' => 400));
    }

    /**
     * Register REST API routes
     */
    public static function register_routes() {
        // Overview stats
        register_rest_route(self::NAMESPACE, '/stats/overview', array(
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'get_overview'),
            'permission_callback' => array(__CLASS__, 'check_permissions'),
        ));

        // Downloads by date (for chart)
        register_rest_route(self::NAMESPACE, '/stats/downloads-by-date', array(
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'get_downloads_by_date'),
            'permission_callback' => array(__CLASS__, 'check_permissions'),
        ));

        // Top products
        register_rest_route(self::NAMESPACE, '/stats/top-products', array(
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'get_top_products'),
            'permission_callback' => array(__CLASS__, 'check_permissions'),
        ));

        // Downloads by file type
        register_rest_route(self::NAMESPACE, '/stats/by-file-type', array(
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'get_by_file_type'),
            'permission_callback' => array(__CLASS__, 'check_permissions'),
        ));

        // Recent downloads
        register_rest_route(self::NAMESPACE, '/stats/recent-downloads', array(
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'get_recent_downloads'),
            'permission_callback' => array(__CLASS__, 'check_permissions'),
        ));

        // Products list (for export selection)
        register_rest_route(self::NAMESPACE, '/stats/products-list', array(
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'get_products_list'),
            'permission_callback' => array(__CLASS__, 'check_permissions'),
        ));

        // Export settings (save/load)
        register_rest_route(self::NAMESPACE, '/stats/export-settings', array(
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array(__CLASS__, 'get_export_settings'),
                'permission_callback' => array(__CLASS__, 'check_permissions'),
            ),
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => array(__CLASS__, 'save_export_settings'),
                'permission_callback' => array(__CLASS__, 'check_permissions'),
            ),
        ));

        // Export data (CSV/XLSX)
        register_rest_route(self::NAMESPACE, '/stats/export', array(
            'methods'             => array(\WP_REST_Server::READABLE, \WP_REST_Server::CREATABLE),
            'callback'            => array(__CLASS__, 'export_data'),
            'permission_callback' => array(__CLASS__, 'check_permissions'),
        ));

        // Get download logs count (for delete preview)
        register_rest_route(self::NAMESPACE, '/stats/logs-count', array(
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'get_logs_count'),
            'permission_callback' => array(__CLASS__, 'check_permissions'),
        ));

        // Delete download logs
        register_rest_route(self::NAMESPACE, '/stats/delete-logs', array(
            'methods'             => \WP_REST_Server::DELETABLE,
            'callback'            => array(__CLASS__, 'delete_logs'),
            'permission_callback' => array(__CLASS__, 'check_delete_permissions'),
        ));

        // Delete logs batch (for large deletions)
        register_rest_route(self::NAMESPACE, '/stats/delete-logs-batch', array(
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'delete_logs_batch'),
            'permission_callback' => array(__CLASS__, 'check_delete_permissions'),
        ));
    }

    /**
     * Check if user has permission to access stats
     */
    public static function check_permissions() {
        return current_user_can('manage_woocommerce') || current_user_can('manage_options');
    }

    /**
     * Parse date range from request
     */
    private static function parse_date_range($request) {
        $start_date = $request->get_param('start_date');
        $end_date = $request->get_param('end_date');

        // Default to last 30 days if not specified
        if (empty($end_date)) {
            $end_date = date('Y-m-d');
        }
        if (empty($start_date)) {
            $start_date = date('Y-m-d', strtotime('-30 days'));
        }

        return array(
            'start' => $start_date,
            'end'   => $end_date,
        );
    }

    /**
     * Get overview statistics
     */
    public static function get_overview($request) {
        global $wpdb;

        $dates = self::parse_date_range($request);
        $start = $dates['start'] . ' 00:00:00';
        $end = $dates['end'] . ' 23:59:59';

        // Total downloads in date range
        $total_downloads = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'somdn_tracked' 
             AND post_status = 'publish'
             AND post_date >= %s 
             AND post_date <= %s",
            $start, $end
        ));

        // Unique products downloaded
        $unique_products = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT pm.meta_value) 
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'somdn_tracked' 
             AND p.post_status = 'publish'
             AND pm.meta_key = 'somdn_product_id'
             AND p.post_date >= %s 
             AND p.post_date <= %s",
            $start, $end
        ));

        // Unique users (logged in)
        $unique_users = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT pm.meta_value) 
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'somdn_tracked' 
             AND p.post_status = 'publish'
             AND pm.meta_key = 'somdn_user_id'
             AND pm.meta_value > 0
             AND p.post_date >= %s 
             AND p.post_date <= %s",
            $start, $end
        ));

        // Today's downloads
        $today = date('Y-m-d');
        $downloads_today = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'somdn_tracked' 
             AND post_status = 'publish'
             AND DATE(post_date) = %s",
            $today
        ));

        // Yesterday's downloads
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $downloads_yesterday = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'somdn_tracked' 
             AND post_status = 'publish'
             AND DATE(post_date) = %s",
            $yesterday
        ));

        // This week
        $week_start = date('Y-m-d', strtotime('monday this week'));
        $downloads_this_week = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'somdn_tracked' 
             AND post_status = 'publish'
             AND post_date >= %s",
            $week_start . ' 00:00:00'
        ));

        // This month
        $month_start = date('Y-m-01');
        $downloads_this_month = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'somdn_tracked' 
             AND post_status = 'publish'
             AND post_date >= %s",
            $month_start . ' 00:00:00'
        ));

        // Last month
        $last_month_start = date('Y-m-01', strtotime('-1 month'));
        $last_month_end = date('Y-m-t', strtotime('-1 month'));
        $downloads_last_month = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'somdn_tracked' 
             AND post_status = 'publish'
             AND post_date >= %s
             AND post_date <= %s",
            $last_month_start . ' 00:00:00',
            $last_month_end . ' 23:59:59'
        ));

        return rest_ensure_response(array(
            'total_downloads'      => $total_downloads,
            'unique_products'      => $unique_products,
            'unique_users'         => $unique_users,
            'downloads_today'      => $downloads_today,
            'downloads_yesterday'  => $downloads_yesterday,
            'downloads_this_week'  => $downloads_this_week,
            'downloads_this_month' => $downloads_this_month,
            'downloads_last_month' => $downloads_last_month,
        ));
    }

    /**
     * Get downloads by date for chart
     */
    public static function get_downloads_by_date($request) {
        global $wpdb;

        $dates = self::parse_date_range($request);
        $start = $dates['start'];
        $end = $dates['end'];

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(post_date) as date, COUNT(*) as count
             FROM {$wpdb->posts}
             WHERE post_type = 'somdn_tracked'
             AND post_status = 'publish'
             AND DATE(post_date) >= %s
             AND DATE(post_date) <= %s
             GROUP BY DATE(post_date)
             ORDER BY date ASC",
            $start, $end
        ), ARRAY_A);

        // Fill in missing dates with 0
        $all_dates = array();
        $current = strtotime($start);
        $end_time = strtotime($end);

        while ($current <= $end_time) {
            $date_key = date('Y-m-d', $current);
            $all_dates[$date_key] = 0;
            $current = strtotime('+1 day', $current);
        }

        foreach ($results as $row) {
            $all_dates[$row['date']] = (int) $row['count'];
        }

        // Format for chart
        $labels = array();
        $values = array();

        foreach ($all_dates as $date => $count) {
            $labels[] = date('M j', strtotime($date));
            $values[] = $count;
        }

        return rest_ensure_response(array(
            'labels' => $labels,
            'values' => $values,
        ));
    }

    /**
     * Get top downloaded products
     */
    public static function get_top_products($request) {
        global $wpdb;

        $dates = self::parse_date_range($request);
        $limit = (int) $request->get_param('limit') ?: 10;
        $start = $dates['start'] . ' 00:00:00';
        $end = $dates['end'] . ' 23:59:59';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT pm.meta_value as product_id, COUNT(*) as downloads
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'somdn_tracked'
             AND p.post_status = 'publish'
             AND pm.meta_key = 'somdn_product_id'
             AND p.post_date >= %s
             AND p.post_date <= %s
             GROUP BY pm.meta_value
             ORDER BY downloads DESC
             LIMIT %d",
            $start, $end, $limit
        ), ARRAY_A);

        $labels = array();
        $values = array();

        foreach ($results as $row) {
            $product_id = (int) $row['product_id'];
            $product_name = get_the_title($product_id);
            if (empty($product_name)) {
                $product_name = sprintf('Product #%d', $product_id);
            }
            $labels[] = html_entity_decode($product_name);
            $values[] = (int) $row['downloads'];
        }

        return rest_ensure_response(array(
            'labels' => $labels,
            'values' => $values,
        ));
    }

    /**
     * Get downloads by file type
     */
    public static function get_by_file_type($request) {
        global $wpdb;

        $dates = self::parse_date_range($request);
        $start = $dates['start'] . ' 00:00:00';
        $end = $dates['end'] . ' 23:59:59';

        // Get all download file names
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT pm.meta_value as files
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'somdn_tracked'
             AND p.post_status = 'publish'
             AND pm.meta_key = 'somdn_download_files'
             AND p.post_date >= %s
             AND p.post_date <= %s",
            $start, $end
        ), ARRAY_A);

        $type_counts = array();

        foreach ($results as $row) {
            $files = $row['files'];
            if (is_serialized($files)) {
                $files = maybe_unserialize($files);
            }

            if (is_array($files)) {
                foreach ($files as $file) {
                    $ext = strtoupper(pathinfo($file, PATHINFO_EXTENSION));
                    if (empty($ext)) $ext = 'Unknown';
                    $type_counts[$ext] = isset($type_counts[$ext]) ? $type_counts[$ext] + 1 : 1;
                }
            } else if (is_string($files) && !empty($files)) {
                $ext = strtoupper(pathinfo($files, PATHINFO_EXTENSION));
                if (empty($ext)) $ext = 'Unknown';
                $type_counts[$ext] = isset($type_counts[$ext]) ? $type_counts[$ext] + 1 : 1;
            }
        }

        // Sort by count descending
        arsort($type_counts);

        // Limit to top 5, group rest as "Other"
        $top_types = array_slice($type_counts, 0, 5, true);
        $other_count = array_sum(array_slice($type_counts, 5));

        if ($other_count > 0) {
            $top_types['Other'] = $other_count;
        }

        return rest_ensure_response(array(
            'labels' => array_keys($top_types),
            'values' => array_values($top_types),
        ));
    }

    /**
     * Get recent downloads
     */
    public static function get_recent_downloads($request) {
        global $wpdb;

        $dates = self::parse_date_range($request);
        $page = (int) $request->get_param('page') ?: 1;
        $per_page = (int) $request->get_param('per_page') ?: 10;
        $offset = ($page - 1) * $per_page;

        $start = $dates['start'] . ' 00:00:00';
        $end = $dates['end'] . ' 23:59:59';

        // Get total count
        $total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_type = 'somdn_tracked'
             AND post_status = 'publish'
             AND post_date >= %s
             AND post_date <= %s",
            $start, $end
        ));

        // Get downloads
        $downloads = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_date FROM {$wpdb->posts}
             WHERE post_type = 'somdn_tracked'
             AND post_status = 'publish'
             AND post_date >= %s
             AND post_date <= %s
             ORDER BY post_date DESC
             LIMIT %d OFFSET %d",
            $start, $end, $per_page, $offset
        ), ARRAY_A);

        $items = array();

        foreach ($downloads as $download) {
            $download_id = $download['ID'];
            $product_id = get_post_meta($download_id, 'somdn_product_id', true);
            $user_email = get_post_meta($download_id, 'somdn_user_email', true);
            $files = get_post_meta($download_id, 'somdn_download_files', true);

            $file_name = '';
            if (is_array($files) && !empty($files)) {
                $file_name = basename(reset($files));
                if (count($files) > 1) {
                    $file_name .= sprintf(' (+%d more)', count($files) - 1);
                }
            } else if (is_string($files) && !empty($files)) {
                $file_name = basename($files);
            }

            $items[] = array(
                'id'            => $download_id,
                'product_id'    => (int) $product_id,
                'product_name'  => html_entity_decode(get_the_title($product_id)),
                'file_name'     => $file_name,
                'user_email'    => $user_email,
                'download_date' => $download['post_date'],
            );
        }

        return rest_ensure_response(array(
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per_page,
            'total_pages' => ceil($total / $per_page),
        ));
    }

    /**
     * Get products list for export selection
     * 
     * Returns all products (not just free ones) because users may want to
     * export/filter data for any product that has been downloaded.
     */
    public static function get_products_list($request) {
        $args = array(
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'post_status'    => 'publish',
        );

        $products = get_posts($args);
        $result = array();

        foreach ($products as $product) {
            $result[] = array(
                'id'   => $product->ID,
                'name' => html_entity_decode($product->post_title),
            );
        }

        return rest_ensure_response($result);
    }

    /**
     * Get saved export settings
     */
    public static function get_export_settings($request) {
        $settings = get_option('somdn_stats_export_settings', array());

        // Debug: log what's in the database
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Free Downloads Stats - Raw saved settings: ' . print_r($settings, true));
        }

        return rest_ensure_response(array(
            'export_type'     => isset($settings['export_type']) ? $settings['export_type'] : 'all-downloads',
            'export_products' => isset($settings['export_products']) ? $settings['export_products'] : array(),
            'export_columns'  => isset($settings['export_columns']) ? $settings['export_columns'] : array(),
        ));
    }

    /**
     * Save export settings
     */
    public static function save_export_settings($request) {
        $body = $request->get_json_params();

        // Debug: log incoming data
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Free Downloads Stats - Save request body: ' . print_r($body, true));
        }

        $settings = array(
            'export_type'     => isset($body['export_type']) ? sanitize_text_field($body['export_type']) : 'all-downloads',
            'export_products' => isset($body['export_products']) ? array_map('absint', (array) $body['export_products']) : array(),
            'export_columns'  => isset($body['export_columns']) ? array_map('sanitize_text_field', (array) $body['export_columns']) : array(),
        );

        // Debug: log what we're saving
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Free Downloads Stats - Saving settings: ' . print_r($settings, true));
        }

        $result = update_option('somdn_stats_export_settings', $settings);

        // Debug: log result
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Free Downloads Stats - update_option result: ' . ($result ? 'true' : 'false'));
        }

        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Settings saved successfully.',
            'saved'   => $settings,
        ));
    }

    /**
     * Export data (redirect to legacy export handler)
     * 
     * For now, we'll return instructions to use the legacy export.
     * The full export functionality uses the existing somdn_generate_test_csv 
     * and somdn_generate_xlsx functions.
     */
    public static function export_data($request) {
        // Get parameters
        $format = $request->get_param('format') ?: 'xlsx';
        $start_date = $request->get_param('start_date') ?: '';
        $end_date = $request->get_param('end_date') ?: '';
        $export_type = $request->get_param('export_type') ?: 'all-downloads';
        $export_products = $request->get_param('export_products');
        $export_columns = $request->get_param('export_columns');

        // Decode JSON if needed
        if (is_string($export_products)) {
            $export_products = json_decode($export_products, true) ?: array();
        }
        if (is_string($export_columns)) {
            $export_columns = json_decode($export_columns, true) ?: array();
        }

        // Build download args
        $download_args = array(
            'start_date' => $start_date,
            'end_date'   => $end_date,
        );

        // Check if the legacy function exists
        if (!function_exists('somdn_get_downloads_data')) {
            return new \WP_Error('missing_function', 'Export function not available.', array('status' => 500));
        }

        // Get download data using existing function
        $download_data = somdn_get_downloads_data($download_args, $format, $export_type, $export_products);

        if (empty($download_data)) {
            return new \WP_Error('no_data', 'No download data found for the selected criteria.', array('status' => 404));
        }

        // Use the existing export functions
        if ($format === 'csv') {
            if (function_exists('somdn_generate_test_csv')) {
                somdn_generate_test_csv($download_data, $export_columns);
            }
        } else {
            if (function_exists('somdn_generate_xlsx')) {
                somdn_generate_xlsx($download_data, $export_columns);
            }
        }

        // If we get here, something went wrong (the functions above should exit)
        return new \WP_Error('export_failed', 'Export generation failed.', array('status' => 500));
    }

    /**
     * Check if user has permission to delete logs (higher permission required)
     */
    public static function check_delete_permissions() {
        return current_user_can('manage_options');
    }

    /**
     * Get count of logs matching criteria (for delete preview)
     */
    public static function get_logs_count($request) {
        global $wpdb;

        $delete_type = $request->get_param('delete_type') ?: 'older-than';
        $days = (int) $request->get_param('days') ?: 90;
        $product_ids = $request->get_param('product_ids');
        $start_date = $request->get_param('start_date');
        $end_date = $request->get_param('end_date');

        // Decode product_ids if it's a JSON string
        if (is_string($product_ids) && !empty($product_ids)) {
            $product_ids = json_decode($product_ids, true);
        }

        $where_clauses = array("post_type = 'somdn_tracked'", "post_status = 'publish'");
        $params = array();

        switch ($delete_type) {
            case 'older-than':
                $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
                $where_clauses[] = 'post_date < %s';
                $params[] = $cutoff_date;
                break;

            case 'date-range':
                if (!empty($start_date)) {
                    $where_clauses[] = 'post_date >= %s';
                    $params[] = $start_date . ' 00:00:00';
                }
                if (!empty($end_date)) {
                    $where_clauses[] = 'post_date <= %s';
                    $params[] = $end_date . ' 23:59:59';
                }
                break;

            case 'by-product':
                if (!empty($product_ids) && is_array($product_ids)) {
                    $placeholders = implode(',', array_fill(0, count($product_ids), '%d'));
                    $where_clauses[] = "ID IN (
                        SELECT post_id FROM {$wpdb->postmeta} 
                        WHERE meta_key = 'somdn_product_id' 
                        AND meta_value IN ({$placeholders})
                    )";
                    $params = array_merge($params, array_map('absint', $product_ids));
                } else {
                    return rest_ensure_response(array(
                        'count' => 0,
                        'message' => 'No products selected.',
                    ));
                }
                break;

            case 'all':
                // No additional where clauses - delete all
                break;

            default:
                return new \WP_Error('invalid_type', 'Invalid delete type.', array('status' => 400));
        }

        $where_sql = implode(' AND ', $where_clauses);
        $sql = "SELECT COUNT(*) FROM {$wpdb->posts} WHERE {$where_sql}";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, ...$params);
        }

        $count = (int) $wpdb->get_var($sql);

        // Estimate meta entries (typically 10-15 per download)
        $estimated_meta = $count * 12;

        return rest_ensure_response(array(
            'count' => $count,
            'estimated_meta_entries' => $estimated_meta,
            'message' => sprintf(
                'Found %s download log entries (%s estimated postmeta rows).',
                number_format($count),
                number_format($estimated_meta)
            ),
        ));
    }

    /**
     * Delete logs in a single request (for smaller deletions)
     */
    public static function delete_logs($request) {
        global $wpdb;

        $delete_type = $request->get_param('delete_type') ?: 'older-than';
        $days = (int) $request->get_param('days') ?: 90;
        $product_ids = $request->get_param('product_ids');
        $start_date = $request->get_param('start_date');
        $end_date = $request->get_param('end_date');
        $limit = (int) $request->get_param('limit') ?: 1000;

        // Decode product_ids if it's a JSON string
        if (is_string($product_ids) && !empty($product_ids)) {
            $product_ids = json_decode($product_ids, true);
        }

        // Build query to get post IDs
        $where_clauses = array("post_type = 'somdn_tracked'", "post_status = 'publish'");
        $params = array();

        switch ($delete_type) {
            case 'older-than':
                $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
                $where_clauses[] = 'post_date < %s';
                $params[] = $cutoff_date;
                break;

            case 'date-range':
                if (!empty($start_date)) {
                    $where_clauses[] = 'post_date >= %s';
                    $params[] = $start_date . ' 00:00:00';
                }
                if (!empty($end_date)) {
                    $where_clauses[] = 'post_date <= %s';
                    $params[] = $end_date . ' 23:59:59';
                }
                break;

            case 'by-product':
                if (!empty($product_ids) && is_array($product_ids)) {
                    $placeholders = implode(',', array_fill(0, count($product_ids), '%d'));
                    $where_clauses[] = "ID IN (
                        SELECT post_id FROM {$wpdb->postmeta} 
                        WHERE meta_key = 'somdn_product_id' 
                        AND meta_value IN ({$placeholders})
                    )";
                    $params = array_merge($params, array_map('absint', $product_ids));
                } else {
                    return new \WP_Error('no_products', 'No products selected.', array('status' => 400));
                }
                break;

            case 'all':
                // No additional where clauses - delete all
                break;

            default:
                return new \WP_Error('invalid_type', 'Invalid delete type.', array('status' => 400));
        }

        $where_sql = implode(' AND ', $where_clauses);
        
        // Get IDs to delete (with limit for safety)
        $sql = "SELECT ID FROM {$wpdb->posts} WHERE {$where_sql} LIMIT %d";
        $params[] = min($limit, 5000); // Cap at 5000 per request

        $post_ids = $wpdb->get_col($wpdb->prepare($sql, ...$params));

        if (empty($post_ids)) {
            return rest_ensure_response(array(
                'deleted' => 0,
                'remaining' => 0,
                'message' => 'No logs found matching the criteria.',
            ));
        }

        $deleted_count = 0;

        // Delete in batches to avoid memory issues
        foreach (array_chunk($post_ids, 100) as $batch) {
            foreach ($batch as $post_id) {
                // Delete post meta first
                $wpdb->delete($wpdb->postmeta, array('post_id' => $post_id), array('%d'));
                // Delete the post
                $wpdb->delete($wpdb->posts, array('ID' => $post_id), array('%d'));
                $deleted_count++;
            }
        }

        // Check if there are more to delete
        $remaining_sql = "SELECT COUNT(*) FROM {$wpdb->posts} WHERE {$where_sql}";
        // Remove the limit param for counting
        array_pop($params);
        if (!empty($params)) {
            $remaining_sql = $wpdb->prepare($remaining_sql, ...$params);
        }
        $remaining = (int) $wpdb->get_var($remaining_sql);

        return rest_ensure_response(array(
            'deleted' => $deleted_count,
            'remaining' => $remaining,
            'message' => sprintf(
                'Deleted %s download logs. %s remaining.',
                number_format($deleted_count),
                number_format($remaining)
            ),
        ));
    }

    /**
     * Delete logs in batches (for large deletions with progress tracking)
     */
    public static function delete_logs_batch($request) {
        global $wpdb;

        $body = $request->get_json_params();

        $delete_type = isset($body['delete_type']) ? sanitize_text_field($body['delete_type']) : 'older-than';
        $days = isset($body['days']) ? (int) $body['days'] : 90;
        $product_ids = isset($body['product_ids']) ? $body['product_ids'] : array();
        $start_date = isset($body['start_date']) ? sanitize_text_field($body['start_date']) : '';
        $end_date = isset($body['end_date']) ? sanitize_text_field($body['end_date']) : '';
        $batch_size = isset($body['batch_size']) ? min((int) $body['batch_size'], 1000) : 500;

        // Build query to get post IDs
        $where_clauses = array("post_type = 'somdn_tracked'", "post_status = 'publish'");
        $params = array();

        switch ($delete_type) {
            case 'older-than':
                $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
                $where_clauses[] = 'post_date < %s';
                $params[] = $cutoff_date;
                break;

            case 'date-range':
                if (!empty($start_date)) {
                    $where_clauses[] = 'post_date >= %s';
                    $params[] = $start_date . ' 00:00:00';
                }
                if (!empty($end_date)) {
                    $where_clauses[] = 'post_date <= %s';
                    $params[] = $end_date . ' 23:59:59';
                }
                break;

            case 'by-product':
                if (!empty($product_ids) && is_array($product_ids)) {
                    $product_ids = array_map('absint', $product_ids);
                    $placeholders = implode(',', array_fill(0, count($product_ids), '%d'));
                    $where_clauses[] = "ID IN (
                        SELECT post_id FROM {$wpdb->postmeta} 
                        WHERE meta_key = 'somdn_product_id' 
                        AND meta_value IN ({$placeholders})
                    )";
                    $params = array_merge($params, $product_ids);
                } else {
                    return new \WP_Error('no_products', 'No products selected.', array('status' => 400));
                }
                break;

            case 'all':
                // No additional where clauses - delete all
                break;

            default:
                return new \WP_Error('invalid_type', 'Invalid delete type.', array('status' => 400));
        }

        $where_sql = implode(' AND ', $where_clauses);

        // Get total count first
        $count_sql = "SELECT COUNT(*) FROM {$wpdb->posts} WHERE {$where_sql}";
        if (!empty($params)) {
            $count_sql = $wpdb->prepare($count_sql, ...$params);
        }
        $total_before = (int) $wpdb->get_var($count_sql);

        // Get IDs to delete in this batch
        $limit_params = $params;
        $limit_params[] = $batch_size;
        $sql = "SELECT ID FROM {$wpdb->posts} WHERE {$where_sql} ORDER BY ID ASC LIMIT %d";
        $post_ids = $wpdb->get_col($wpdb->prepare($sql, ...$limit_params));

        if (empty($post_ids)) {
            return rest_ensure_response(array(
                'deleted_this_batch' => 0,
                'total_before' => $total_before,
                'remaining' => 0,
                'complete' => true,
                'message' => 'Deletion complete. No more logs to delete.',
            ));
        }

        $deleted_count = 0;

        // Delete this batch
        foreach ($post_ids as $post_id) {
            // Delete post meta first
            $wpdb->delete($wpdb->postmeta, array('post_id' => $post_id), array('%d'));
            // Delete the post
            $result = $wpdb->delete($wpdb->posts, array('ID' => $post_id), array('%d'));
            if ($result) {
                $deleted_count++;
            }
        }

        // Get remaining count
        if (!empty($params)) {
            $count_sql = $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE {$where_sql}", ...$params);
        } else {
            $count_sql = "SELECT COUNT(*) FROM {$wpdb->posts} WHERE {$where_sql}";
        }
        $remaining = (int) $wpdb->get_var($count_sql);

        return rest_ensure_response(array(
            'deleted_this_batch' => $deleted_count,
            'total_before' => $total_before,
            'remaining' => $remaining,
            'complete' => $remaining === 0,
            'message' => sprintf(
                'Deleted %s logs this batch. %s remaining.',
                number_format($deleted_count),
                number_format($remaining)
            ),
        ));
    }
}

// Initialize the endpoint
Stats_Endpoint::init();

