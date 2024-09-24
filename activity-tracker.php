<?php
/**
 * Plugin Name: Activity Tracker
 * Description: Designed to track and log various changes and activities that occur on a WordPress site.
 * Version: 1.0
 * Author: Daniel Cubas
 * Author URI: https://qoding.com.br
 */

 register_activation_hook(__FILE__, 'create_wpcrl_plugin_table');
 function create_wpcrl_plugin_table() {
     global $wpdb;
     $table_name = $wpdb->prefix . 'wpcrl_logs';
     $charset_collate = $wpdb->get_charset_collate();
 
     $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        wpcrl_type varchar(50) NOT NULL,
        wpcrl_action varchar(50) NOT NULL,
        wpcrl_details text NOT NULL,
        user_id bigint(20) NOT NULL,
        wpcrl_datetime datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        content_status varchar(20) DEFAULT NULL,
        PRIMARY KEY  (id)
     ) $charset_collate;";

     require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
     dbDelta($sql);
 }


// Track creation and updating of posts
add_action('save_post', 'wpcrl_log_post_changes', 10, 3);
function wpcrl_log_post_changes($post_id, $post, $update) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpcrl_logs';
    $user_id = get_current_user_id();

    // Check the post type and adjust the message
    $post_type = get_post_type($post_id);
    $action = $update ? 'Updated' : 'Created';

    // Get the post status
    $post_status = $post->post_status;

    // Get username
    $user_info = get_userdata($user_id);
    $user_name = $user_info ? $user_info->display_name : 'Unknown User';

    $details = "{$action}: {$post->post_title} (ID: {$post_id}) ({$user_name})";

    $wpdb->insert($table_name, [
        'wpcrl_type' => ucfirst($post_type),
        'wpcrl_action' => $action,
        'wpcrl_details' => $details,
        'content_status' => $post_status,
        'user_id' => $user_id,
    ]);
}

// Track details of the post before it's deleted
add_action('before_delete_post', 'save_post_details_before_deletion');
function save_post_details_before_deletion($post_id) {
    global $saved_post_details, $saved_post_status;
    $post = get_post($post_id);

    $post_type = $post->post_type === 'post' ? 'Post' : ($post->post_type === 'page' ? 'Page' : ucfirst($post->post_type));
    $saved_post_details = "{$post_type}: {$post->post_title} (ID: {$post_id})";
    $saved_post_status = $post->post_status;
}
// Track post deletion
add_action('delete_post', 'wpcrl_log_post_deletion');
function wpcrl_log_post_deletion($post_id) {
    global $wpdb, $saved_post_details, $saved_post_status;
    $table_name = $wpdb->prefix . 'wpcrl_logs';
    $user_id = get_current_user_id();

    // Extract post type from details
    list($post_type, $rest) = explode(': ', $saved_post_details, 2);
    
    // Get username
    $user_info = get_userdata($user_id);
    $user_name = $user_info ? $user_info->display_name : 'Unknown User';

    $details = "{$saved_post_details} deleted ({$user_name})";

    $wpdb->insert($table_name, [
        'wpcrl_type' => $post_type,
        'wpcrl_action' => 'Deleted',
        'wpcrl_details' => $details,
        'content_status' => $saved_post_status,
        'user_id' => $user_id,
    ]);
}


// Track plugin activation
add_action('activated_plugin', 'wpcrl_log_plugin_activation', 10, 2);
function wpcrl_log_plugin_activation($plugin, $network_wide) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpcrl_logs';
    $user_id = get_current_user_id();
    
    // Get username
    $user_info = get_userdata($user_id);
    $user_name = $user_info ? $user_info->display_name : 'Unknown User';

    $details = "Plugin activated - " . $plugin . " ({$user_name})";

    $wpdb->insert($table_name, [
        'wpcrl_type' => 'Plugin',
        'wpcrl_action' => 'Activated',
        'wpcrl_details' => $details,
        'content_status' => '',
        'user_id' => $user_id,
    ]);
}

// Track plugin deactivation
add_action('deactivated_plugin', 'wpcrl_log_plugin_deactivation', 10, 2);
function wpcrl_log_plugin_deactivation($plugin, $network_wide) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpcrl_logs';
    $user_id = get_current_user_id();

    // Get username
    $user_info = get_userdata($user_id);
    $user_name = $user_info ? $user_info->display_name : 'Unknown User';

    $details = "Plugin deactivated - " . $plugin . " ({$user_name})";

    $wpdb->insert($table_name, [
        'wpcrl_type' => 'Plugin',
        'wpcrl_action' => 'Deactivated',
        'wpcrl_details' => $details,
        'content_status' => '',
        'user_id' => $user_id,
    ]);
}


// Track media uploads
add_action('add_attachment', 'wpcrl_log_media_upload');
function wpcrl_log_media_upload($post_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpcrl_logs';
    $user_id = get_current_user_id();
    $post = get_post($post_id);

    // Get username
    $user_info = get_userdata($user_id);
    $user_name = $user_info ? $user_info->display_name : 'Unknown User';

    $details = $post->post_title ." - ". wp_get_attachment_url($post_id)." - ({$user_name})";
    $media_type = wp_check_filetype($post->guid)['type'];

    $wpdb->insert($table_name, [
        'wpcrl_type' => 'Media',
        'wpcrl_action' => 'Upload',
        'wpcrl_details' => $details,
        'content_status' => $media_type,
        'user_id' => $user_id,
    ]);
}

// Track media deletions
add_action('delete_attachment', 'wpcrl_log_media_deletion');
function wpcrl_log_media_deletion($post_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpcrl_logs';
    $user_id = get_current_user_id();
    $post = get_post($post_id);

    // Get username
    $user_info = get_userdata($user_id);
    $user_name = $user_info ? $user_info->display_name : 'Unknown User';

    $details = $post->post_title . " ({$user_name})";
    $media_type = wp_check_filetype($post->guid)['type'];

    $wpdb->insert($table_name, [
        'wpcrl_type' => 'Media',
        'wpcrl_action' => 'Deleted',
        'wpcrl_details' => $details,
        'content_status' => $media_type,
        'user_id' => $user_id,
    ]);
}




function wpcrl_get_types_for_filter() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpcrl_logs';
    $types = $wpdb->get_col("SELECT DISTINCT wpcrl_type FROM $table_name");
    return $types;
}
function wpcrl_get_actions_for_filter() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpcrl_logs';
    $actions = $wpdb->get_col("SELECT DISTINCT wpcrl_action FROM $table_name");
    return $actions;
}
function wpcrl_get_users_for_filter() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpcrl_logs';
    $user_ids = $wpdb->get_col("SELECT DISTINCT user_id FROM $table_name");
    $users = [];
    foreach ($user_ids as $id) {
        $user_info = get_userdata($id);
        if ($user_info) {
            $users[$id] = $user_info->display_name;
        }
    }
    return $users;
}




// Hooks to add the button and handle the export
add_action('admin_menu', 'register_export_page');
add_action('init', 'wpcrl_generate_csv_file');

// Register the page in the admin menu
function register_export_page() {
    if (current_user_can('administrator')) {
        add_menu_page(
            'Logs', 
            'Logs', 
            'manage_options', 
            'register-alterations',
            'wpcrl_logs_page', 
            'dashicons-media-text',
            200
        );
    }
}

function wpcrl_logs_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpcrl_logs';

    $types = wpcrl_get_types_for_filter();
    $actions = wpcrl_get_actions_for_filter();
    $users = wpcrl_get_users_for_filter();

    $where = [];
    if (isset($_GET['type']) && $_GET['type']) {
        $where[] = $wpdb->prepare("wpcrl_type = %s", sanitize_text_field($_GET['type']));
    }
    if (isset($_GET['action']) && $_GET['action']) {
        $where[] = $wpdb->prepare("wpcrl_action = %s", sanitize_text_field($_GET['action']));
    }
    if (isset($_GET['user']) && $_GET['user']) {
        $where[] = $wpdb->prepare("user_id = %s", absint($_GET['user']));
    }

    $where_sql = $where ? " WHERE " . implode(" AND ", $where) : '';

    // Determine the current page
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $limit = 100;
    $offset = ($current_page - 1) * $limit;

    // Fetch logs for the current page
    #$logs = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name ORDER BY wpcrl_datetime DESC LIMIT %d OFFSET %d", $limit, $offset));
    $logs = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name $where_sql ORDER BY wpcrl_datetime DESC LIMIT %d OFFSET %d", $limit, $offset));

    // Calculate the total number of logs
    $total_logs = $wpdb->get_var("SELECT COUNT(id) FROM $table_name");
    $total_pages = ceil($total_logs / $limit);

    // Display the logs
    echo '<div class="wrap"><h1>Change Logs</h1>';

    echo '<div style="display:flex;justify-content:space-between;align-items:center;">';
    // Mostrar os selectboxes (exemplo básico)
    echo '<form style="margin-bottom:5px;margin-top:5px;" method="get">';
        echo '<input type="hidden" name="page" value="register-alterations">';
        echo '<select style="margin-right:5px;" name="type">';
            echo "<option value=''>Type</option>";
        foreach ($types as $type) {
            echo "<option value='$type'>$type</option>";
        }
        echo '</select>';

        echo '<select style="margin-right:5px;" name="action">';
            echo "<option value=''>Action</option>";
        foreach ($actions as $action) {
            echo "<option value='$action'>$action</option>";
        }
        echo '</select>';

        echo '<select style="margin-right:5px;" name="user">';
            echo "<option value=''>User</option>";
        foreach ($users as $id => $name) {
            echo "<option value='$id'>$name</option>";
        }
        echo '</select>';

        // Repita para ações, status e usuários
        echo '<input style="margin-right:5px;" class="button button-primary" type="submit" value="Filtrar">';
        echo '<a href="' . admin_url('admin.php?page=register-alterations') . '" class="button button-primary">Limpar</a>';
    echo '</form>';

    echo '<a href="' . admin_url('admin.php?page=register-alterations&export=csv') . '" class="button button-primary">Download CSV</a></div>';

    echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>ID</th><th>Action</th><th>Type</th><th>Status</th><th>Details</th><th>User</th><th>Date/Time</th></tr></thead>';
        echo '<tbody>';

        foreach ($logs as $log) {
            echo "<tr><td>" . esc_html($log->id) . "</td><td>" . esc_html($log->wpcrl_action) . "</td><td>" . esc_html($log->wpcrl_type) . "</td><td>" . esc_html($log->content_status) . "</td><td>" . esc_html($log->wpcrl_details) . "</td><td>" . esc_html($log->user_id) . "</td><td>" . esc_html($log->wpcrl_datetime) . "</td></tr>";
        }
    echo '</tbody></table>';

    // Pagination links
    $page_base = menu_page_url('register-alterations', false) . '%_%';
    echo paginate_links(array(
        'base' => $page_base,
        'format' => '&paged=%#%',
        'current' => $current_page,
        'total' => $total_pages,
        'type' => 'plain'
    ));

    echo '</div>';
}


function wpcrl_generate_csv_file() {
    if (isset($_GET['page']) && $_GET['page'] == 'register-alterations' && isset($_GET['export']) && $_GET['export'] == 'csv') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpcrl_logs';

        $logs = $wpdb->get_results("SELECT * FROM $table_name ORDER BY wpcrl_datetime DESC", ARRAY_A);

        if (!empty($logs)) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="logs.csv"');

            $output = fopen('php://output', 'w');
            // Adding column headers
            fputcsv($output, array('ID', 'Type', 'Action', 'Details', 'Status', 'User', 'Date/Time'));

            foreach ($logs as $log) {
                // Inserting each log line into the CSV
                fputcsv($output, array(
                    $log['id'], 
                    $log['wpcrl_type'], 
                    $log['wpcrl_action'], 
                    $log['wpcrl_details'], 
                    $log['content_status'], 
                    $log['user_id'], 
                    $log['wpcrl_datetime']
                ));
            }

            fclose($output);
            exit;
        }
    }
}
