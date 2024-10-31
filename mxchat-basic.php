<?php
/**
 * Plugin Name: MxChat
 * Description: AI Chatbot for WordPress with support for OpenAI, X.AI, and Claude models. Includes features like custom knowledge submission, chat transcripts, and seamless integration with WooCommerce. Perfect for blogs, business sites, e-commerce platforms, and more, offering real-time intelligent interactions with advanced AI models.
 * Version: 1.2.1
 * Author: MxChat
 * Author URI: https://mxchat.ai
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Include classes
require_once plugin_dir_path(__FILE__) . 'includes/class-mxchat-integrator.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-mxchat-admin.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-mxchat-public.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-mxchat-utils.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-mxchat-user.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-mxchat-woocommerce.php';

function mxchat_activate() {
    global $wpdb;

    // Chat Transcripts Table
    $chat_transcripts_table = $wpdb->prefix . 'mxchat_chat_transcripts';
    $sql_chat_transcripts = "CREATE TABLE $chat_transcripts_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id mediumint(9) DEFAULT 0,
        session_id varchar(255) NOT NULL,
        role varchar(255) NOT NULL,
        message text NOT NULL,
        timestamp timestamp DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    );";

    // System Prompt Content Table
    $system_prompt_table = $wpdb->prefix . 'mxchat_system_prompt_content';
    $sql_system_prompt = "CREATE TABLE $system_prompt_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        url varchar(255) NOT NULL,
        article_content longtext NOT NULL,
        embedding_vector longtext,
        timestamp timestamp DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    );";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    // Create or update the chat transcripts table
    dbDelta($sql_chat_transcripts);

    // Create or update the system prompt content table
    dbDelta($sql_system_prompt);

    // Ensure 'embedding_vector' column exists in the 'system_prompt_content' table
    $column_exists = $wpdb->get_results($wpdb->prepare(
        "SHOW COLUMNS FROM {$system_prompt_table} LIKE %s",
        'embedding_vector'
    ));
    if (empty($column_exists)) {
        $wpdb->query(
            "ALTER TABLE {$system_prompt_table} ADD COLUMN embedding_vector longtext"
        );
    }

    // Ensure 'source_url' column exists in the 'system_prompt_content' table
    $source_url_exists = $wpdb->get_results($wpdb->prepare(
        "SHOW COLUMNS FROM {$system_prompt_table} LIKE %s",
        'source_url'
    ));
    if (empty($source_url_exists)) {
        $wpdb->query(
            "ALTER TABLE {$system_prompt_table} ADD COLUMN source_url VARCHAR(255) DEFAULT NULL"
        );
    }

    // Ensure 'user_identifier' and 'user_email' columns exist in the 'chat_transcripts' table
    $user_identifier_exists = $wpdb->get_results($wpdb->prepare(
        "SHOW COLUMNS FROM {$chat_transcripts_table} LIKE %s",
        'user_identifier'
    ));
    if (empty($user_identifier_exists)) {
        $wpdb->query(
            "ALTER TABLE {$chat_transcripts_table} ADD COLUMN user_identifier VARCHAR(255)"
        );
    }

    $user_email_exists = $wpdb->get_results($wpdb->prepare(
        "SHOW COLUMNS FROM {$chat_transcripts_table} LIKE %s",
        'user_email'
    ));
    if (empty($user_email_exists)) {
        $wpdb->query(
            "ALTER TABLE {$chat_transcripts_table} ADD COLUMN user_email VARCHAR(255)"
        );
    }

    // Update the version number in the database
    update_option('mxchat_plugin_version', '1.2.1');
}

function mxchat_check_for_update() {
    $current_version = get_option('mxchat_plugin_version');
    $plugin_version = '1.2.1';

    if ($current_version !== $plugin_version) {
        mxchat_activate(); // Run the activation script which includes database migrations
        update_option('mxchat_plugin_version', $plugin_version); // Update to the latest version
    }
}

// Run the update check function on every request
add_action('plugins_loaded', 'mxchat_check_for_update');

// Register activation hook
register_activation_hook(__FILE__, 'mxchat_activate');

// Instantiate classes
if (is_admin()) {
    $mxchat_admin = new MxChat_Admin();
}
$mxchat_public = new MxChat_Public();
$mxchat_integrator = new MxChat_Integrator();
