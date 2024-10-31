<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class MxChat_Admin {
    private $options;
    private $chat_count;
    private $is_activated;

    public function __construct() {
        $this->options = get_option('mxchat_options');
        $this->chat_count = get_option('mxchat_chat_count', 0);
        $this->is_activated = $this->is_license_active();

        // Initialize default options if they are not set
        if (!$this->options) {
            $this->initialize_default_options();
        }

        // Add admin menu and initialize settings
        add_action('admin_menu', array($this, 'mxchat_add_plugin_page'));
        add_action('admin_init', array($this, 'mxchat_page_init'));
        add_action('admin_enqueue_scripts', array($this, 'mxchat_enqueue_admin_assets'));
        add_action('wp_ajax_mxchat_delete_chat_history', array($this, 'mxchat_delete_chat_history'));
        add_action('admin_post_mxchat_submit_content', array($this, 'mxchat_handle_content_submission'));
        add_action('admin_post_mxchat_delete_prompt', array($this, 'mxchat_handle_delete_prompt'));
        add_action('wp_ajax_mxchat_fetch_chat_history', array($this, 'mxchat_fetch_chat_history'));
        add_action('wp_ajax_nopriv_mxchat_fetch_chat_history', array($this, 'mxchat_fetch_chat_history'));
        add_action('admin_post_mxchat_submit_sitemap', array($this, 'mxchat_handle_sitemap_submission'));
        add_action('wp_footer', array($this, 'mxchat_append_chatbot_to_body'));
        add_action('admin_head-mxchat-prompts', array($this, 'mxchat_enqueue_admin_assets'));
        add_action('admin_head-toplevel_page_mxchat-max', array($this, 'mxchat_enqueue_admin_assets'));
        add_action('wp_ajax_mxchat_activate_license', array($this, 'mxchat_handle_activate_license'));
        add_action('admin_notices', array($this, 'mxchat_display_admin_notice'));

        // Add the AJAX handler for logged-in users
        add_action('wp_ajax_mxchat_save_inline_prompt', array($this, 'mxchat_save_inline_prompt'));


    }

    // Method to check if the license is active
    private function is_license_active() {
        $license_status = get_option('mxchat_license_status', 'inactive');
        return $license_status === 'active';
    }

    // Initialize default options
private function initialize_default_options() {
    $default_options = array(
        'api_key' => '',
        'xai_api_key' => '',
        'claude_api_key' => '',
        'system_prompt_instructions' => '[EXAMPLE INSTRUCTIONS] You are an AI Chatbot assistant for this website. The primary subject you should focus on is [insert proper subject here]. Your main goal is to assist visitors with questions related to this specific topic. Here are some key things to keep in mind:
        - Your name is [Chatbot Name]. Always introduce yourself as this name when appropriate.
        - Stay focused on topics related to [insert proper subject here]. If a visitor asks about an unrelated topic, politely redirect the conversation to how you can assist them with this subject. If there is an exception topic (e.g., "parking") that you should assist with, you may do so if instructed.
        - When appropriate, highlight the benefits of [insert proper subject here]. Offer to guide visitors to relevant pages or provide them with more information.
        - If a visitor asks for a purchase link or further information, provide them with this link: [Insert Purchase Link Here]. Always ensure that the link is relevant and directly related to the website\'s offerings.
        - Keep your responses short, concise, and to the point. Provide clear and direct answers suitable for a chatbot interaction.
        - If you reference specific content, provide a hyperlink to the relevant page using hypertext. Avoid including links that do not directly relate to the content or answer the visitor\'s query.
        - Provide answers based on the knowledge available to you. If you do not have an answer to a specific question, let the visitor know that you don’t have the information and suggest where they might find it or offer to help with something else.',
        'model' => 'gpt-3.5-turbo',
        'rate_limit' => '100',
        'rate_limit_message' => 'Rate limit exceeded. Please try again later.',
        'top_bar_title' => 'MxChat',
        'intro_message' => 'Hello! How can I assist you today?',
        'input_copy' => 'How can I assist?',
        'append_to_body' => 'off',
        'close_button_color' => '#fff',
        'chatbot_bg_color' => '#fff',
        'user_message_bg_color' => '#fff',
        'user_message_font_color' => '#212121',
        'bot_message_bg_color' => '#212121',
        'bot_message_font_color' => '#fff',
        'top_bar_bg_color' => '#212121',
        'send_button_font_color' => '#212121',
        'chat_input_font_color' => '#212121',
        'chatbot_background_color' => '#212121',
        'icon_color' => '#fff',
        'enable_woocommerce_integration' => '0',
        'enable_woocommerce_order_access' => '0',
        'link_target_toggle' => 'off',
        'pre_chat_message' => 'Hey there! Ask me anything!',

        // New fields for Loops Integration
        'loops_api_key' => '',
        'loops_mailing_list' => '',
        'trigger_keywords' => '',
        'triggered_phrase_response' => 'Would you like to join our mailing list? Please provide your email below.',
        'email_capture_response' => 'Thank you for providing your email! You\'ve been added to our list.',
        'popular_question_1' => '',
        'popular_question_2' => '',
        'popular_question_3' => '',
    );


        // Merge existing options with defaults
        $existing_options = get_option('mxchat_options', array());
        $merged_options = wp_parse_args($existing_options, $default_options);

        // Update the options if they have changed
        if ($existing_options !== $merged_options) {
            update_option('mxchat_options', $merged_options);
        }

        // Update the $this->options property
        $this->options = $merged_options;
    }



    public function mxchat_add_plugin_page() {
        // Main menu page
        add_menu_page(
            'MxChat Settings',
            'MxChat',
            'manage_options',
            'mxchat-max',
            array($this, 'mxchat_create_admin_page'),
            'dashicons-testimonial',
            6
        );

        // Submenu page for Knowledge
        add_submenu_page(
            'mxchat-max',
            'Prompts',
            'Knowledge',
            'manage_options',
            'mxchat-prompts',
            array($this, 'mxchat_create_prompts_page')
        );

        // Submenu page for Chat Transcripts
        add_submenu_page(
            'mxchat-max',  // Corrected parent slug to match the main menu
            'Chat Transcripts',
            'Transcripts',
            'manage_options',
            'mxchat-transcripts',
            array($this, 'mxchat_create_transcripts_page') // Prefixed function name with mxchat_
        );

        // Submenu page for Activation Key
        add_submenu_page(
            'mxchat-max',
            'Pro Upgrade',
            'Pro Upgrade',
            'manage_options',
            'mxchat-activation',
            array($this, 'mxchat_create_activation_page')
        );
    }


public function mxchat_handle_content_submission() {
    // Check if the form was submitted and the user has sufficient permissions
    if (!isset($_POST['submit_content']) || !current_user_can('manage_options')) {
        return;
    }

    // Verify the nonce field for security
    $nonce = isset($_POST['mxchat_submit_content_nonce']) ? sanitize_text_field(wp_unslash($_POST['mxchat_submit_content_nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'mxchat_submit_content_action')) {
        wp_die('Nonce verification failed.');
    }

    // Sanitize the content input
    $article_content = sanitize_textarea_field($_POST['article_content']);

    // Sanitize the URL input (optional URL)
    $article_url = isset($_POST['article_url']) ? esc_url_raw($_POST['article_url']) : ''; // Default to empty if not provided

    // Generate the embedding vector for the content
    $embedding_vector = $this->mxchat_generate_embedding($article_content);

    global $wpdb;
    $table_name = $wpdb->prefix . 'mxchat_system_prompt_content';

    // Check if the 'source_url' column exists in the table and add it if it doesn't
    if ($wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table_name} LIKE %s", 'source_url')) != 'source_url') {
        // Use wpdb::query and a prepared statement to avoid SQL injection
        $wpdb->query($wpdb->prepare("ALTER TABLE {$table_name} ADD source_url VARCHAR(255) DEFAULT ''"));
    }

    if (is_array($embedding_vector)) {
        // Serialize the embedding vector before storing it
        $embedding_vector_serialized = serialize($embedding_vector);

        // Insert the content, embedding vector, and source URL into the database, using a prepared statement
        $inserted = $wpdb->insert(
            $table_name,
            array(
                'article_content' => $article_content,
                'embedding_vector' => $embedding_vector_serialized,
                'source_url'       => $article_url, // Insert the URL, empty string if not provided
            ),
            array(
                '%s', // Format for article_content (string)
                '%s', // Format for embedding_vector (serialized string)
                '%s', // Format for source_url (string)
            )
        );

        if ($inserted === false) {
            //error_log('Error inserting content: ' . $wpdb->last_error);
            set_transient('mxchat_admin_notice_error', 'Error inserting content into the database. Please try again.', 30);
        } else {
            set_transient('mxchat_admin_notice_success', 'Content successfully submitted!', 30);
        }

    } else {
        //error_log('Embedding generation failed for article content: ' . $article_content);
        set_transient('mxchat_admin_notice_error', 'Embedding generation failed. Please ensure your API key is correct and try again.', 30);
    }

    // Redirect after setting the transient
    wp_safe_redirect(esc_url(admin_url('admin.php?page=mxchat-prompts')));
    exit;
}

public function mxchat_display_admin_notice() {
    // Success notice
    if ($message = get_transient('mxchat_admin_notice_success')) {
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html($message); ?></p>
            <button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>
        </div>
        <?php
        delete_transient('mxchat_admin_notice_success'); // Clear the transient after displaying
    }

    // Error notice
    if ($message = get_transient('mxchat_admin_notice_error')) {
        ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html($message); ?></p>
            <button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>
        </div>
        <?php
        delete_transient('mxchat_admin_notice_error'); // Clear the transient after displaying
    }
}


    public function mxchat_handle_delete_prompt() {
        // Sanitize and validate nonce using wp_unslash and sanitize_text_field
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if (empty($nonce) || !wp_verify_nonce($nonce, 'mxchat_delete_prompt_nonce')) {
            wp_die('Nonce verification failed.');
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to delete prompts.');
        }

        // Sanitize and validate the 'id' parameter
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($id <= 0) {
            wp_die('Invalid prompt ID.');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'mxchat_system_prompt_content';

        // Clear relevant cache before deletion
        $cache_key = 'prompt_' . $id;
        wp_cache_delete($cache_key, 'mxchat_prompts');

        // Delete the record using a prepared statement
        $deleted = $wpdb->delete($table_name, array('id' => $id), array('%d'));

        if ($deleted !== false) {
            // Optionally, clear a general cache if you have one
            wp_cache_delete('all_prompts', 'mxchat_prompts');
        }

        // Redirect to the prompts page with a success message
        $redirect_url = add_query_arg(array(
            'page' => 'mxchat-prompts',
            'deleted' => 'true'
        ), admin_url('admin.php'));

        wp_safe_redirect($redirect_url);
        exit;
    }


public function mxchat_create_admin_page() {
    ?>
    <div class="wrap mxchat-admin">
        <?php if (!$this->is_activated): ?>
        <div class="mxchat-pro-banner">
          <p>
              Limited-time offer: Get a lifetime MxChat Pro license for just $49.97 until 01.01.25! After that, it switches to an annual license at $99.97/year. Purchase now and be grandfathered into the lifetime deal!
              <a href="https://mxchat.ai/" target="_blank">Upgrade to MxChat Pro today!</a>
          </p>
        </div>
        <?php endif; ?>
        <h2 class="admin-title">Mx<span class="admin-emphasis">Chat</span></h2>
            <h2 class="mxchat-nav-tab-wrapper">
                <a href="#chatbot" class="mxchat-nav-tab mxchat-nav-tab-active" data-tab="chatbot">Chatbot</a>
                <a href="#embed" class="mxchat-nav-tab" data-tab="embed">Integrations</a>
                <a href="#theme" class="mxchat-nav-tab" data-tab="theme">Theme</a>
                <a href="#general" class="mxchat-nav-tab" data-tab="general">FAQ</a>
            </h2>
        <form method="post" action="options.php">
            <?php settings_fields('mxchat_option_group'); ?>
            <div id="chatbot" class="mxchat-tab-content active">
                <?php do_settings_sections('mxchat-chatbot'); ?>
            </div>



<div id="embed" class="mxchat-tab-content">
    <div class="mxchat-settings-section">
        <h2>WooCommerce Settings</h2>
        <table class="form-table">
            <?php do_settings_fields('mxchat-embed', 'mxchat_woocommerce_section'); ?>
        </table>
    </div>

    <div class="section-divider"></div> <!-- Divider -->

    <div class="mxchat-settings-section">
        <h2>Loops Settings</h2>
        <table class="form-table">
            <?php do_settings_fields('mxchat-embed', 'mxchat_loops_section'); ?>
        </table>
    </div>
</div>





            <div id="theme" class="mxchat-tab-content">
                <?php do_settings_sections('mxchat-theme'); ?>
            </div>
            <div id="general" class="mxchat-tab-content">
                <?php do_settings_sections('mxchat-general'); ?>
<p>If you're having trouble setting up or getting the responses you need, please don’t hesitate to <a href="https://mxchat.ai/contact/">contact our team</a>.<p>

<div class="faq-item">
    <h3>How does the Claude API integration work?</h3>
    <p>
        The Claude API, provided by Anthropic, allows for intelligent, context-aware chatbot responses in MxChat. To use it, you will need both an OpenAI API key and a Claude API key. This is necessary because the system utilizes OpenAI for vector embedding in the Retrieval-Augmented Generation (RAG) process, as Claude does not currently offer an embedding API.
    </p>
    <p>
        When using the Claude API, your custom content is sent to Claude for generating responses, while the embeddings are processed through OpenAI. This ensures your chatbot provides accurate and engaging responses while maintaining knowledge relevant to your website.
    </p>
    <p>
        You can obtain your Claude API key by signing up on the <a href="https://www.anthropic.com/api" target="_blank" rel="noopener">Anthropic API page</a>.
    </p>
</div>

<div class="faq-item">
    <h3>How does the X.AI API integration work?</h3>
    <p>
        The X.AI API, released on 10.21.24, is currently in beta. To use it, you will need both an OpenAI API key and an X.AI API key. This is because OpenAI handles vector embeddings in the Retrieval-Augmented Generation (RAG) process, as X.AI does not yet provide an embedding API.
    </p>
    <p>
        Custom content is sent to the X.AI API for generating responses, while OpenAI processes the embeddings. This allows the chatbot to provide advanced responses while maintaining context from your website. You can get your X.AI API key from the <a href="https://docs.x.ai/docs" target="_blank" rel="noopener">X.AI API documentation</a>.
    </p>
</div>

<div class="faq-item">
    <h3>Do I need an OpenAI API key to use the chatbot?</h3>
    <p>
        Yes, you will need an OpenAI API key to power the chatbot. You can obtain an API key by signing up on the <a href="https://platform.openai.com/signup" target="_blank" rel="noopener">OpenAI platform</a>. After signing up, you must add credits to your account—typically, $5 in credits is sufficient to get started.
    </p>
    <p>
        Once you have your API key, simply enter it in the chatbot's settings to enable functionality. The chatbot relies on OpenAI’s models for generating responses, so having sufficient credits in your OpenAI account is essential for smooth operation.
    </p>
</div>

                    <div class="faq-item">
                        <h3>How do I add the chatbot to my site?</h3>
                        <p>
                            You can add the chatbot using the shortcode <code>[mxchat_chatbot floating="yes"]</code> or <code>[mxchat_chatbot floating="no"]</code>. For initial testing and styling, it's best to use the shortcode on a draft or non-public page. Once you’re ready to go live, enable the “Append Chat Widget to Body” option in the settings for site-wide integration (recommended) or add shortcode to the footer.
                        </p>
                    </div>

                    <div class="faq-item">
                        <h3>How does the chatbot use my content to generate responses?</h3>
                        <p>
                            The chatbot uses AI and vector embeddings to connect users with relevant information. When you submit content, it converts it into a mathematical format. When a user asks a question, the bot matches it to your stored content and generates a response based on relevance. For example, submitting "Our phone number is 910-123-4567" allows the bot to retrieve this information when asked about contact details. To ensure related information is provided together, submit it in one entry.
                        </p>
                    </div>

                    <div class="faq-item">
                        <h3>Why does the chatbot sometimes make up links or information?</h3>
                        <p>
                            Occasionally, the chatbot may generate inaccurate links or information, known as "hallucinations." To minimize this, you can add system instructions to guide its behavior. For example, include an instruction like: "Only provide links you directly have access to or retrieve from the knowledge base. Do not make up links or information that you do not have direct access to." This helps the bot stay aligned with your content.
                        </p>
                    </div>

                    <div class="faq-item">
                        <h3>How does the Complianz integration work?</h3>
                        <p>
                            The Pro version offers direct integration with the Complianz GDPR plugin, making it easy to stay compliant with GDPR. If Complianz is enabled on your website, users must accept consent before the chatbot widget appears. The chatbot will only display once the user has accepted, ensuring compliance with data privacy regulations.
                        </p>
                    </div>

                    <div class="faq-item">
                        <h3>How does the WooCommerce integration work?</h3>
                        <p>
                            With WooCommerce integration, the chatbot automatically embeds new products and updates existing ones. For already-published products, you can click update or submit the product sitemap. The “Order History Access” setting allows the bot to access users' order history (requires login) and assist with order inquiries. To enable the “Add to Cart” feature, add this system instruction: “After discussing a product, ask if the user wants to add it to their cart. The user must say 'Add to cart' exactly.” Currently, this feature supports English only, with more languages coming soon.
                        </p>
                    </div>

                <div class="faq-item">
                    <h3>Why isn't my chatbot responding as expected?</h3>
                    <p>
                        AI chatbots can sometimes behave in unexpected ways, especially if you're new to configuring AI for specific tasks. We're committed to helping you get the most out of your chatbot experience. While we’re working on comprehensive guides and video tutorials, our team is here to assist you directly. If your chatbot isn't delivering the responses you need, please don’t hesitate to <a href="https://mxchat.ai/contact/" target="_blank" rel="noopener noreferrer">contact us</a> for personalized support.
                    </p>
                    <p>
                        We’re dedicated to your success and ready to guide you in aligning the AI's behavior to meet your goals.
                    </p>
                </div>

                <div class="faq-item">
                    <h3>What is Loops, and how do I get an API key?</h3>
                    <p>
                        Loops is a powerful SaaS email service that helps you automate and enhance your email marketing campaigns, making it easy to reach and engage with your audience. To integrate Loops with MxChat, you’ll need an API key from Loops. You can obtain this key by logging into your Loops account and navigating to the API settings. Visit the <a href="https://loops.so" target="_blank" rel="noopener noreferrer">Loops website</a> to get started or to sign up for an account.
                    </p>
                </div>


            </div>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}


        public function mxchat_create_transcripts_page() {
            ?>
            <div class="wrap mxchat-admin">
                <h2><?php esc_html_e('Chat Transcripts', 'mxchat'); ?></h2>
                <form id="mxchat-delete-form" method="post">
                    <?php wp_nonce_field('mxchat_delete_chat_history', 'mxchat_delete_chat_nonce'); ?>
                    <div class="mxchat-controls">
                        <label for="mxchat-select-all-transcripts" class="mxchat-select-all-label">
                            <input type="checkbox" id="mxchat-select-all-transcripts" /> <?php esc_html_e('Select All', 'mxchat'); ?>
                        </label>
                        <input type="submit" value="<?php esc_attr_e('Delete Selected', 'mxchat'); ?>" class="button button-primary delete-chats-button" />
                    </div>
                    <div id="mxchat-transcripts">
                        <!-- Transcripts will be loaded here -->
                    </div>
                </form>
            </div>
            <?php
        }




    public function mxchat_create_prompts_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mxchat_system_prompt_content';

        // Verify the nonce before processing the request
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field($_GET['_wpnonce']) : '';

        if (!empty($nonce) && wp_verify_nonce($nonce, 'mxchat_prompts_search_nonce')) {
            // Sanitize input data
            $search_query = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
            $current_page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        } else {
            $search_query = '';
            $current_page = 1;
        }

        $per_page = 10;

        // Create cache keys
        $cache_key_total = 'total_prompts_' . md5($search_query);
        $cache_key_prompts = 'prompts_' . md5($search_query . '_' . $current_page);

        // Retrieve total number of prompts from cache or database
        $total_prompts = wp_cache_get($cache_key_total, 'mxchat_prompts');
        if ($total_prompts === false) {
            if (!empty($search_query)) {
                $total_prompts = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$table_name} WHERE article_content LIKE %s",
                        '%' . $wpdb->esc_like($search_query) . '%'
                    )
                );
            } else {
                $total_prompts = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
            }
            wp_cache_set($cache_key_total, $total_prompts, 'mxchat_prompts', 3600); // Cache for 1 hour
        }

        $total_pages = ceil($total_prompts / $per_page);
        $offset = ($current_page - 1) * $per_page;

        // Retrieve prompts from cache or database
        $prompts = wp_cache_get($cache_key_prompts, 'mxchat_prompts');
        if ($prompts === false) {
            if (!empty($search_query)) {
                $prompts = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM {$table_name} WHERE article_content LIKE %s ORDER BY timestamp DESC LIMIT %d OFFSET %d",
                        '%' . $wpdb->esc_like($search_query) . '%',
                        $per_page,
                        $offset
                    )
                );
            } else {
                $prompts = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM {$table_name} ORDER BY timestamp DESC LIMIT %d OFFSET %d",
                        $per_page,
                        $offset
                    )
                );
            }
            wp_cache_set($cache_key_prompts, $prompts, 'mxchat_prompts', 3600); // Cache for 1 hour
        }

        ?>

        <div class="wrap mxchat-admin">
            <div class="mxchat-grid-container">

                <!-- Submit Content -->
<div class="mxchat-grid-item full-width">
    <h2>Submit Content</h2>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php?action=mxchat_submit_content')); ?>">
        <?php wp_nonce_field('mxchat_submit_content_action', 'mxchat_submit_content_nonce'); ?>
        <div class="mxchat-form-group">
            <label for="article_content">Article Content:</label>
            <textarea name="article_content" id="article_content" required></textarea>
        </div>
        <div class="mxchat-form-group">
            <label for="article_url">Article URL (Optional):</label>
            <input type="url" name="article_url" id="article_url" placeholder="Enter related URL for the content">
        </div>
        <input type="submit" name="submit_content" value="Submit Content" class="button button-primary submit-content-button" />
    </form>
</div>


                <!-- Submit Sitemap -->
                <div class="mxchat-grid-item">
                    <h2>Submit Sitemap or Page URL</h2>
                    <form id="mxchat-sitemap-form" method="post" class="mxchat-sitemap-form" action="<?php echo esc_url(admin_url('admin-post.php?action=mxchat_submit_sitemap')); ?>">
                        <?php wp_nonce_field('mxchat_submit_sitemap_action', 'mxchat_submit_sitemap_nonce'); ?>
                        <div class="mxchat-search-group">
                            <input type="url" name="sitemap_url" id="sitemap_url" placeholder="Sitemap or URL" required />
                            <input type="submit" name="submit_sitemap" value="Submit" class="button button-primary" />
                        </div>
                    </form>
                    <div id="mxchat-sitemap-loading" class="mxchat-spinner" style="display: none;"></div>
                    <div id="mxchat-loading-text" style="display: none; margin-top: 10px; color: #333;">Loading sitemap content into database, please wait...</div>
                </div>

                <!-- Search Knowledge -->
                <div class="mxchat-grid-item">
                    <h2>Search Knowledge</h2>
                    <form method="get" id="knowledge-search">
                        <?php wp_nonce_field('mxchat_prompts_search_nonce'); ?>
                        <input type="hidden" name="page" value="mxchat-prompts" />
                        <div class="mxchat-search-group">
                            <input type="text" name="search" placeholder="Search Knowledge" value="<?php echo esc_attr($search_query); ?>" />
                            <input type="submit" value="Search" class="button button-primary" />
                        </div>
                    </form>
                </div>
            </div>

            <!-- Table below the forms -->
            <div class="tablenav">
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php echo esc_html($total_prompts); ?> items</span>
                    <?php
                    $page_links = paginate_links(array(
                        'base'      => add_query_arg('paged', '%#%', admin_url('admin.php?page=mxchat-prompts')),
                        'format'    => '',
                        'prev_text' => __('&laquo; Previous'),
                        'next_text' => __('Next &raquo;'),
                        'total'     => $total_pages,
                        'current'   => $current_page,
                        'add_args'  => array(
                            'search' => $search_query,
                            '_wpnonce' => wp_create_nonce('mxchat_prompts_search_nonce')
                        ),
                    ));

                    if ($page_links) {
                        echo '<div class="tablenav-pages">' . wp_kses_post($page_links) . '</div>';
                    }
                    ?>
                </div>
            </div>
            <table class="mxchat-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Article Content</th>
            <th>URL</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($prompts) : ?>
            <?php foreach ($prompts as $prompt) : ?>
                <tr id="prompt-<?php echo esc_attr($prompt->id); ?>">
                    <td><?php echo esc_html($prompt->id); ?></td>
                    <td class="mxchat_article_content_dashboard">
                        <span class="content-view"><?php echo wp_kses_post(wpautop(esc_textarea($prompt->article_content))); ?></span>
                        <textarea class="content-edit" style="display:none;"><?php echo esc_textarea($prompt->article_content); ?></textarea>
                    </td>
                    <td class="mxchat_article_url_dashboard">
                        <span class="url-view">
                            <?php if (!empty($prompt->source_url)) : ?>
                                <a href="<?php echo esc_url($prompt->source_url); ?>" target="_blank"><?php echo esc_html($prompt->source_url); ?></a>
                            <?php else : ?>
                                N/A
                            <?php endif; ?>
                        </span>
                        <input type="url" class="url-edit" value="<?php echo esc_attr($prompt->source_url); ?>" style="display:none;" />
                    </td>
                    <td>
                        <button class="button edit-button" data-id="<?php echo esc_attr($prompt->id); ?>">Edit</button>
                        <button class="button save-button" data-id="<?php echo esc_attr($prompt->id); ?>" style="display:none;">Save</button>
                        <a href="<?php echo esc_url(admin_url('admin-post.php?action=mxchat_delete_prompt&id=' . esc_attr($prompt->id) . '&_wpnonce=' . wp_create_nonce('mxchat_delete_prompt_nonce'))); ?>" class="button delete-button">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else : ?>
            <tr>
                <td colspan="4">No prompts found.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

        </div>

        <?php
    }

    public function mxchat_generate_embedding($text) {
        $options = get_option('mxchat_options');
        $api_key = $options['api_key'] ?? 'default_api_key';

        $response = wp_remote_post('https://api.openai.com/v1/embeddings', array(
            'body'    => wp_json_encode(array(
                'model' => 'text-embedding-ada-002',
                'input' => $text
            )),
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json'
            ),
        ));

        if (is_wp_error($response)) {
            return null;
        }

        $response_data = json_decode(wp_remote_retrieve_body($response), true);
        return $response_data['data'][0]['embedding'] ?? null;
    }

    public function mxchat_delete_chat_history() {
        if (!current_user_can('manage_options')) {
            echo wp_json_encode(['error' => 'You do not have sufficient permissions.']);
            wp_die();
        }

        check_ajax_referer('mxchat_delete_chat_history', 'security');

        global $wpdb;
        $table_name = $wpdb->prefix . 'mxchat_chat_transcripts';

        if (isset($_POST['delete_session_ids']) && is_array($_POST['delete_session_ids'])) {
            foreach ($_POST['delete_session_ids'] as $session_id) {
                $session_id_sanitized = sanitize_text_field($session_id);

                // Clear relevant cache before deletion
                $cache_key = 'chat_session_' . $session_id_sanitized;
                wp_cache_delete($cache_key, 'mxchat_chat_sessions');

                // Perform the deletion
                $wpdb->delete($table_name, ['session_id' => $session_id_sanitized]);
            }

            // Optionally, clear a general cache if you have one
            wp_cache_delete('all_chat_sessions', 'mxchat_chat_sessions');

            echo wp_json_encode(['success' => 'Selected chat sessions have been deleted.']);
        } else {
            echo wp_json_encode(['error' => 'No chat sessions selected for deletion.']);
        }

        wp_die();
    }


public function mxchat_save_inline_prompt() {
    // Check for nonce security
    check_ajax_referer('mxchat_save_inline_nonce');

    // Verify permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied.');
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'mxchat_system_prompt_content';

    // Validate and sanitize input data
    $prompt_id = isset($_POST['id']) ? absint($_POST['id']) : 0;
    $article_content = isset($_POST['article_content']) ? sanitize_textarea_field($_POST['article_content']) : '';
    $article_url = isset($_POST['article_url']) ? esc_url_raw($_POST['article_url']) : '';

    if ($prompt_id > 0 && !empty($article_content)) {
        // Re-generate the embedding vector for the updated content
        $embedding_vector = $this->mxchat_generate_embedding($article_content);

        if (is_array($embedding_vector)) {
            // Serialize the embedding vector before storing it
            $embedding_vector_serialized = serialize($embedding_vector);

            // Update the prompt in the database
            $updated = $wpdb->update(
                $table_name,
                array(
                    'article_content'   => $article_content,
                    'embedding_vector'  => $embedding_vector_serialized,
                    'source_url'        => $article_url,
                ),
                array('id' => $prompt_id),
                array('%s', '%s', '%s'),
                array('%d')
            );

            if ($updated !== false) {
                wp_send_json_success();
            } else {
                wp_send_json_error('Database update failed.');
            }
        } else {
            wp_send_json_error('Embedding generation failed.');
        }
    } else {
        wp_send_json_error('Invalid data.');
    }
}


public function mxchat_fetch_chat_history() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'mxchat_chat_transcripts';

    // Check if the current user has sufficient permissions
    if (!current_user_can('manage_options')) {
        echo esc_html__('You do not have sufficient permissions to view this page.', 'mxchat');
        wp_die();
    }

    // Fetch chat transcripts from the database, ordered by timestamp
    $chat_transcripts = $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM {$table_name} ORDER BY timestamp ASC")
    );

    // If no transcripts are available, display a message
    if (empty($chat_transcripts)) {
        echo esc_html__('No chat history available.', 'mxchat');
        wp_die();
    }

    ob_start();
    $current_session_id = '';

    foreach ($chat_transcripts as $transcript) {
        // Start a new session block if session ID changes
        if ($current_session_id !== $transcript->session_id) {
            if ($current_session_id !== '') {
                echo '</div>'; // Close the previous session block
            }
            $current_session_id = sanitize_text_field($transcript->session_id);
            echo '<div class="chat-session">';
            echo '<h4><input type="checkbox" name="delete_session_ids[]" value="' . esc_attr($transcript->session_id) . '"> ' . esc_html__('Session ID:', 'mxchat') . ' ' . esc_html($current_session_id) . '</h4>';
        }

        // Format the timestamp for display
        $formatted_timestamp = date_i18n('F j, Y g:i a', strtotime($transcript->timestamp));

        // Determine the role to display (user identifier or email for users, bot for the AI)
        $role = $transcript->role;
        if ($role === 'user') {
            // Sanitize email if available
            if (!empty($transcript->user_email)) {
                $role = sanitize_email($transcript->user_email);
            } else {
                // Sanitize user identifier, anonymize if it's an IP address
                $user_identifier = sanitize_text_field($transcript->user_identifier);

                // Check if the user identifier is an IP address and anonymize it
                if (filter_var($user_identifier, FILTER_VALIDATE_IP)) {
                    $role = preg_replace('/\.\d+$/', '.xxx', $user_identifier); // Mask the last octet
                } else {
                    $role = $user_identifier; // If it's not an IP, just display the identifier
                }
            }
        }

        // Output the chat message
        echo '<div class="chat-message">';
        echo '<strong>' . esc_html($role) . ' (' . esc_html($formatted_timestamp) . '):</strong> ';
        echo wp_kses_post($transcript->message);
        echo '</div>';
    }

    // Close the final session block
    echo '</div>';

    $output = ob_get_clean();

    echo $output;
    wp_die();
}


public function mxchat_create_activation_page() {
    $license_status = get_option('mxchat_license_status', 'inactive');
    $license_error = get_option('mxchat_license_error', '');

    ?>
    <div class="wrap mxchat-admin">
        <h2>MxChat Pro: Activation</h2>
        <?php if ($license_status === 'inactive' && !empty($license_error)): ?>
            <div class="error notice">
                <p><?php echo esc_html($license_error); ?></p>
            </div>
        <?php endif; ?>
        <form id="mxchat-activation-form">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Email Address</th>
                    <td>
                        <input type="email" id="mxchat_pro_email" name="mxchat_pro_email" value="<?php echo esc_attr(get_option('mxchat_pro_email')); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Activation Key</th>
                    <td>
                        <input type="text" id="mxchat_activation_key" name="mxchat_activation_key" value="<?php echo esc_attr(get_option('mxchat_activation_key')); ?>" class="regular-text" />
                    </td>
                </tr>
            </table>
            <?php if ($license_status !== 'active'): ?>
                <?php submit_button('Activate License', 'primary', 'activate_license'); ?>
            <?php else: ?>
                <h3>MxChat Pro</h3>
            <?php endif; ?>
        </form>
        <h3>License Status: <span id="mxchat-license-status"><?php echo $license_status === 'active' ? 'Active' : 'Inactive'; ?></span></h3>
    </div>
    <?php
}



public function mxchat_page_init() {
    // Register settings
    register_setting(
        'mxchat_option_group',
        'mxchat_options',
        array($this, 'mxchat_sanitize')
    );

    // Chatbot Settings Section
    add_settings_section(
        'mxchat_chatbot_section',
        'Chatbot Settings',
        null,
        'mxchat-chatbot'
    );

    // Registering fields for the Chatbot Settings section
    add_settings_field(
        'api_key',
        'OpenAI API Key',
        array($this, 'api_key_callback'),
        'mxchat-chatbot',
        'mxchat_chatbot_section'
    );

    add_settings_field(
    'xai_api_key',
    'X.AI API Key',
    array($this, 'xai_api_key_callback'),
    'mxchat-chatbot',
    'mxchat_chatbot_section'
    );

    add_settings_field(
        'claude_api_key',
        'Claude API Key',
        array($this, 'claude_api_key_callback'),
        'mxchat-chatbot',
        'mxchat_chatbot_section'
    );


    add_settings_field(
        'system_prompt_instructions',
        'AI Instructions',
        array($this, 'system_prompt_instructions_callback'),
        'mxchat-chatbot',
        'mxchat_chatbot_section'
    );

    add_settings_field(
        'model',
        'Model',
        array($this, 'mxchat_model_callback'),
        'mxchat-chatbot',
        'mxchat_chatbot_section'
    );

    add_settings_field(
        'top_bar_title',
        'Top Bar Title',
        array($this, 'mxchat_top_bar_title_callback'),
        'mxchat-chatbot',
        'mxchat_chatbot_section'
    );

    add_settings_field(
        'intro_message',
        'Introductory Message',
        array($this, 'mxchat_intro_message_callback'),
        'mxchat-chatbot',
        'mxchat_chatbot_section'
    );

    add_settings_field(
        'input_copy',
        'Input Copy',
        array($this, 'mxchat_input_copy_callback'),
        'mxchat-chatbot',
        'mxchat_chatbot_section'
    );

    add_settings_field(
        'rate_limit',
        'Rate Limit',
        array($this, 'mxchat_rate_limit_callback'),
        'mxchat-chatbot',
        'mxchat_chatbot_section'
    );

    add_settings_field(
        'rate_limit_message',
        'Rate Limit Message',
        array($this, 'mxchat_rate_limit_message_callback'),
        'mxchat-chatbot',
        'mxchat_chatbot_section'
    );

    add_settings_field(
        'pre_chat_message',
        'Pre-Chat Message',
        array($this, 'mxchat_pre_chat_message_callback'),
        'mxchat-chatbot',
        'mxchat_chatbot_section'
    );

        add_settings_field(
        'append_to_body',
        'Append Chat Widget to Body',
        array($this, 'mxchat_append_to_body_callback'),
        'mxchat-chatbot',
        'mxchat_chatbot_section'
    );

        add_settings_field(
        'privacy_toggle',
        'Toggle Privacy Notice',
        array($this, 'mxchat_privacy_toggle_callback'),
        'mxchat-chatbot',
        'mxchat_chatbot_section'
    );

    add_settings_field(
        'complianz_toggle',
        'Enable Complianz',
        array($this, 'mxchat_complianz_toggle_callback'),
        'mxchat-chatbot',
        'mxchat_chatbot_section'
    );

    add_settings_field(
        'link_target_toggle',
        'Open Links in a New Tab',
        array($this, 'mxchat_link_target_toggle_callback'),
        'mxchat-chatbot',
        'mxchat_chatbot_section'
    );

    add_settings_field(
    'chat_persistence_toggle',
    'Enable Chat Persistence',
    array($this, 'mxchat_chat_persistence_toggle_callback'),
    'mxchat-chatbot',
    'mxchat_chatbot_section'
    );

    add_settings_field(
    'popular_question_1',
    'Popular Question 1',
    array($this, 'mxchat_popular_question_1_callback'),
    'mxchat-chatbot',
    'mxchat_chatbot_section'
);

add_settings_field(
    'popular_question_2',
    'Popular Question 2',
    array($this, 'mxchat_popular_question_2_callback'),
    'mxchat-chatbot',
    'mxchat_chatbot_section'
);

add_settings_field(
    'popular_question_3',
    'Popular Question 3',
    array($this, 'mxchat_popular_question_3_callback'),
    'mxchat-chatbot',
    'mxchat_chatbot_section'
);



// WooCommerce Settings Section
add_settings_section(
    'mxchat_woocommerce_section',
    'WooCommerce Settings',
    null,
    'mxchat-embed'
);

// Loops Settings Section
add_settings_section(
    'mxchat_loops_section',
    'Loops Settings',
    null,
    'mxchat-embed'
);

// WooCommerce Settings Fields
add_settings_field(
    'enable_woocommerce_integration',
    'Automatically Embed Products',
    array($this, 'mxchat_enable_woocommerce_integration_callback'),
    'mxchat-embed',
    'mxchat_woocommerce_section'
);

add_settings_field(
    'enable_woocommerce_order_access',
    'Order History Access',
    array($this, 'mxchat_enable_woocommerce_order_access_callback'),
    'mxchat-embed',
    'mxchat_woocommerce_section'
);

add_settings_field(
    'woocommerce_consumer_key',
    'WooCommerce Consumer Key',
    array($this, 'mxchat_woocommerce_consumer_key_callback'),
    'mxchat-embed',
    'mxchat_woocommerce_section'
);

add_settings_field(
    'woocommerce_consumer_secret',
    'WooCommerce Consumer Secret',
    array($this, 'mxchat_woocommerce_consumer_secret_callback'),
    'mxchat-embed',
    'mxchat_woocommerce_section'
);

// Loops Settings Fields
add_settings_field(
    'loops_api_key',
    'Loops API Key',
    array($this, 'mxchat_loops_api_key_callback'),
    'mxchat-embed',
    'mxchat_loops_section'
);

add_settings_field(
    'loops_mailing_list',
    'Loops Mailing List',
    array($this, 'mxchat_loops_mailing_list_callback'),
    'mxchat-embed',
    'mxchat_loops_section'
);

add_settings_field(
    'trigger_keywords',
    'Email Capture Trigger Keywords',
    array($this, 'mxchat_trigger_keywords_callback'),
    'mxchat-embed',
    'mxchat_loops_section'
);

add_settings_field(
    'triggered_phrase_response',
    'Triggered Phrase Response',
    array($this, 'mxchat_triggered_phrase_response_callback'),
    'mxchat-embed',
    'mxchat_loops_section'
);

add_settings_field(
    'email_capture_response',
    'Email Capture Response',
    array($this, 'mxchat_email_capture_response_callback'),
    'mxchat-embed',
    'mxchat_loops_section'
);

    // Theme Settings Section
    add_settings_section(
        'mxchat_theme_section',
        'Theme Settings',
        null,
        'mxchat-theme'
    );

    add_settings_field(
        'close_button_color',
        'Close Button & Title Color',
        array($this, 'mxchat_close_button_color_callback'),
        'mxchat-theme',
        'mxchat_theme_section'
    );

    add_settings_field(
        'chatbot_bg_color',
        'Chatbot Background Color',
        array($this, 'mxchat_chatbot_bg_color_callback'),
        'mxchat-theme',
        'mxchat_theme_section'
    );

    add_settings_field(
        'user_message_bg_color',
        'User Message Background Color',
        array($this, 'mxchat_user_message_bg_color_callback'),
        'mxchat-theme',
        'mxchat_theme_section'
    );

    add_settings_field(
        'user_message_font_color',
        'User Message Font Color',
        array($this, 'mxchat_user_message_font_color_callback'),
        'mxchat-theme',
        'mxchat_theme_section'
    );

    add_settings_field(
        'bot_message_bg_color',
        'Bot Message Background Color',
        array($this, 'mxchat_bot_message_bg_color_callback'),
        'mxchat-theme',
        'mxchat_theme_section'
    );

    add_settings_field(
        'bot_message_font_color',
        'Bot Message Font Color',
        array($this, 'mxchat_bot_message_font_color_callback'),
        'mxchat-theme',
        'mxchat_theme_section'
    );

    add_settings_field(
        'top_bar_bg_color',
        'Top Bar Background Color',
        array($this, 'mxchat_top_bar_bg_color_callback'),
        'mxchat-theme',
        'mxchat_theme_section'
    );

    add_settings_field(
        'send_button_font_color',
        'Send Button Color',
        array($this, 'mxchat_send_button_font_color_callback'),
        'mxchat-theme',
        'mxchat_theme_section'
    );

    add_settings_field(
        'chat_input_font_color',
        'Chat Input Font Color',
        array($this, 'mxchat_chat_input_font_color_callback'),
        'mxchat-theme',
        'mxchat_theme_section'
    );

    add_settings_field(
        'chatbot_background_color',
        'Floating Widget Background Color',
        array($this, 'mxchat_chatbot_background_color_callback'),
        'mxchat-theme',
        'mxchat_theme_section'
    );

    add_settings_field(
        'icon_color',
        'Chatbot Icon Color',
        array($this, 'mxchat_icon_color_callback'),
        'mxchat-theme',
        'mxchat_theme_section'
    );

    // General Settings Section
    add_settings_section(
        'mxchat_general_section',
        'Frequently Asked Questions (FAQ)',
        null,
        'mxchat-general'
    );


}




    public function mxchat_handle_activate_license() {
        check_ajax_referer('mxchat_activate_license_nonce', 'security');

        $license_key = isset($_POST['key']) ? sanitize_text_field($_POST['key']) : '';
        $customer_email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';

        if (empty($license_key) || empty($customer_email)) {
            wp_send_json_error('Email or License Key is missing');
        }

        $product_id = 'MxChatPRO';

        $response = wp_remote_get("http://mxchat.ai/?wc-api=software-api&request=activation&email={$customer_email}&license_key={$license_key}&product_id={$product_id}");

        if (is_wp_error($response)) {
            wp_send_json_error('Activation failed due to a server error');
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        if ($data && isset($data->activated) && $data->activated) {
            update_option('mxchat_license_status', 'active');
            wp_send_json_success();
        } else {
            $error_message = isset($data->error) ? $data->error : 'Activation failed';
            update_option('mxchat_license_status', 'inactive');
            update_option('mxchat_license_error', $error_message);
            wp_send_json_error($error_message);
        }
    }


public function mxchat_rate_limit_callback() {
    $rate_limits = array('5', '10', '15', '20', '100', 'unlimited'); // Add 'unlimited' here
    $selected_rate_limit = isset($this->options['rate_limit']) ? $this->options['rate_limit'] : '100';

    echo '<div class="pro-feature-wrapper active">';
    echo '<select id="rate_limit" name="mxchat_options[rate_limit]">';
    foreach ($rate_limits as $limit) {
        echo '<option value="' . esc_attr($limit) . '" ' . selected($selected_rate_limit, $limit, false) . '>' . esc_html($limit) . '</option>';
    }
    echo '</select>';
    echo '</div>';
}



public function mxchat_rate_limit_message_callback() {
    // Remove the is_activated check and make it always enabled
    echo '<div class="pro-feature-wrapper active">';
    printf(
        '<textarea id="rate_limit_message" name="mxchat_options[rate_limit_message]" rows="3" cols="50">%s</textarea>',
        isset($this->options['rate_limit_message']) ? esc_textarea($this->options['rate_limit_message']) : 'Rate limit exceeded. Please try again later.'
    );
    echo '</div>';
}


public function mxchat_enable_woocommerce_integration_callback() {
    $checked = isset($this->options['enable_woocommerce_integration']) && $this->options['enable_woocommerce_integration'] === '1' ? 'checked' : '';
    $disabled = $this->is_activated ? '' : 'disabled';
    $class = $this->is_activated ? 'pro-feature-wrapper active' : 'pro-feature-wrapper inactive';

    echo '<div class="' . esc_attr($class) . '">';
    echo '<label class="toggle-switch">';
    echo '<input type="checkbox" id="enable_woocommerce_integration" name="mxchat_options[enable_woocommerce_integration]" value="1" ' . $checked . ' ' . $disabled . '>';
    echo '<span class="slider"></span>';
    echo '</label>';

    if (!$this->is_activated) {
        echo '<div class="pro-feature-overlay">';
        echo '<a href="https://mxchat.ai/" target="_blank"><img src="' . plugin_dir_url(__FILE__) . '../images/pro-only-dark.png" alt="Pro Only" /></a>';
        echo '</div>';
    }

    echo '</div>';
}


public function mxchat_enable_woocommerce_order_access_callback() {
    $checked = isset($this->options['enable_woocommerce_order_access']) && $this->options['enable_woocommerce_order_access'] === '1' ? 'checked' : '';
    $disabled = $this->is_activated ? '' : 'disabled';
    $class = $this->is_activated ? 'pro-feature-wrapper active' : 'pro-feature-wrapper inactive';

    echo '<div class="' . esc_attr($class) . '">';
    echo '<label class="toggle-switch">';
    echo '<input type="checkbox" id="enable_woocommerce_order_access" name="mxchat_options[enable_woocommerce_order_access]" value="1" ' . $checked . ' ' . $disabled . '>';
    echo '<span class="slider"></span>';
    echo '</label>';

    if (!$this->is_activated) {
        echo '<div class="pro-feature-overlay">';
        echo '<a href="https://mxchat.ai/" target="_blank"><img src="' . plugin_dir_url(__FILE__) . '../images/pro-only-dark.png" alt="Pro Only" /></a>';
        echo '</div>';
    }

    echo '</div>';
}


    private function mxchat_add_option_field($id, $title, $callback = '') {
        add_settings_field(
            $id,
            $title,
            $callback ? array($this, $callback) : array($this, $id . '_callback'),
            'mxchat-max',
            'mxchat_setting_section_id',
            $id === 'model' ? ['label_for' => 'model'] : []
        );
    }


        public function api_key_callback() {
            $apiKey = isset($this->options['api_key']) ? esc_attr($this->options['api_key']) : '';
            echo '<input type="password" id="api_key" name="mxchat_options[api_key]" value="' . $apiKey . '" class="regular-text" />';
            echo '<button type="button" id="toggleApiKeyVisibility">Show</button>';
        }

        public function xai_api_key_callback() {
            // Check if the feature is activated (paid feature)
            $disabled = $this->is_activated ? '' : 'disabled'; // Disable input if not activated
            $class = $this->is_activated ? 'pro-feature-wrapper active' : 'pro-feature-wrapper inactive'; // CSS class to style the wrapper based on activation status

            // Render the input field for the X.AI API key
            echo '<div class="' . esc_attr($class) . '">';
            printf(
                '<input type="password" id="xai_api_key" name="mxchat_options[xai_api_key]" value="%s" class="regular-text" %s />',
                isset($this->options['xai_api_key']) ? esc_attr($this->options['xai_api_key']) : '',
                $disabled
            );
            echo '<button type="button" id="toggleXaiApiKeyVisibility">Show</button>';

            // If the feature is not activated, show the overlay with a "Pro Only" message
            if (!$this->is_activated) {
                echo '<div class="pro-feature-overlay">';
                echo '<a href="https://mxchat.ai/" target="_blank"><img src="' . plugin_dir_url(__FILE__) . '../images/pro-only-dark.png" alt="Pro Only" /></a>';
                echo '</div>';
            }

            echo '</div>';
        }

        public function claude_api_key_callback() {
            // Check if the feature is activated (paid feature)
            $disabled = $this->is_activated ? '' : 'disabled'; // Disable input if not activated
            $class = $this->is_activated ? 'pro-feature-wrapper active' : 'pro-feature-wrapper inactive'; // CSS class to style the wrapper based on activation status

            // Render the input field for the Claude API key
            echo '<div class="' . esc_attr($class) . '">';
            printf(
                '<input type="password" id="claude_api_key" name="mxchat_options[claude_api_key]" value="%s" class="regular-text" %s />',
                isset($this->options['claude_api_key']) ? esc_attr($this->options['claude_api_key']) : '',
                $disabled
            );
            echo '<button type="button" id="toggleClaudeApiKeyVisibility">Show</button>';

            // If the feature is not activated, show the overlay with a "Pro Only" message
            if (!$this->is_activated) {
                echo '<div class="pro-feature-overlay">';
                echo '<a href="https://mxchat.ai/" target="_blank"><img src="' . plugin_dir_url(__FILE__) . '../images/pro-only-dark.png" alt="Pro Only" /></a>';
                echo '</div>';
            }

            echo '</div>';
        }

        public function mxchat_woocommerce_consumer_key_callback() {
            $disabled = $this->is_activated ? '' : 'disabled';
            $class = $this->is_activated ? 'pro-feature-wrapper active' : 'pro-feature-wrapper inactive';

            echo '<div class="' . esc_attr($class) . '">';
            printf(
                '<input type="text" id="woocommerce_consumer_key" name="mxchat_options[woocommerce_consumer_key]" value="%s" class="regular-text" %s />',
                isset($this->options['woocommerce_consumer_key']) ? esc_attr($this->options['woocommerce_consumer_key']) : '',
                $disabled
            );

            if (!$this->is_activated) {
                echo '<div class="pro-feature-overlay">';
                echo '<a href="https://mxchat.ai/" target="_blank"><img src="' . plugin_dir_url(__FILE__) . '../images/pro-only-dark.png" alt="Pro Only" /></a>';
                echo '</div>';
            }

            echo '</div>';
        }

        public function mxchat_woocommerce_consumer_secret_callback() {
            $disabled = $this->is_activated ? '' : 'disabled';
            $class = $this->is_activated ? 'pro-feature-wrapper active' : 'pro-feature-wrapper inactive';

            echo '<div class="' . esc_attr($class) . '">';
            printf(
                '<input type="password" id="woocommerce_consumer_secret" name="mxchat_options[woocommerce_consumer_secret]" value="%s" class="regular-text" %s />',
                isset($this->options['woocommerce_consumer_secret']) ? esc_attr($this->options['woocommerce_consumer_secret']) : '',
                $disabled
            );
            echo '<button type="button" id="toggleWooCommerceSecretVisibility">Show</button>';

            if (!$this->is_activated) {
                echo '<div class="pro-feature-overlay">';
                echo '<a href="https://mxchat.ai/" target="_blank"><img src="' . plugin_dir_url(__FILE__) . '../images/pro-only-dark.png" alt="Pro Only" /></a>';
                echo '</div>';
            }

            echo '</div>';
        }

        public function mxchat_loops_api_key_callback() {
            $loops_api_key = isset($this->options['loops_api_key']) ? esc_attr($this->options['loops_api_key']) : '';

            echo '<div class="api-key-wrapper">';
            printf(
                '<input type="password" id="loops_api_key" name="mxchat_options[loops_api_key]" value="%s" class="regular-text" />',
                $loops_api_key
            );
            echo '<button type="button" id="toggleLoopsApiKeyVisibility">Show</button>';
            echo '</div>';
            echo '<p class="description">Enter your Loops API Key here. (See FAQ for details)</p>';
        }

        public function mxchat_loops_mailing_list_callback() {
            // Retrieve Loops API key and lists
            $loops_api_key = isset($this->options['loops_api_key']) ? $this->options['loops_api_key'] : '';
            $selected_list = isset($this->options['loops_mailing_list']) ? $this->options['loops_mailing_list'] : '';

            if ($loops_api_key) {
                // Fetch lists from Loops API
                $lists = $this->mxchat_fetch_loops_mailing_lists($loops_api_key);

                if ($lists) {
                    echo '<select id="loops_mailing_list" name="mxchat_options[loops_mailing_list]">';
                    foreach ($lists as $list) {
                        echo '<option value="' . esc_attr($list['id']) . '" ' . selected($selected_list, $list['id'], false) . '>' . esc_html($list['name']) . '</option>';
                    }
                    echo '</select>';
                } else {
                    echo '<p class="description">No lists found. Please verify your API Key.</p>';
                }
            } else {
                echo '<p class="description">Enter a valid Loops API Key to load mailing lists.</p>';
            }
        }

        public function mxchat_trigger_keywords_callback() {
            $trigger_keywords = isset($this->options['trigger_keywords']) ? $this->options['trigger_keywords'] : '';
            echo '<input type="text" id="trigger_keywords" name="mxchat_options[trigger_keywords]" value="' . esc_attr($trigger_keywords) . '">';
            echo '<p class="description">Enter comma-separated trigger keywords (e.g., subscribe, discount, newsletter).</p>';
        }

        public function mxchat_triggered_phrase_response_callback() {
            $triggered_response = isset($this->options['triggered_phrase_response']) ? $this->options['triggered_phrase_response'] : '';
            echo '<textarea id="triggered_phrase_response" name="mxchat_options[triggered_phrase_response]" rows="3" cols="50">' . esc_textarea($triggered_response) . '</textarea>';
            echo '<p class="description">Enter the chatbot response when a trigger keyword is detected, prompting the user to share their email.</p>';
        }

        public function mxchat_email_capture_response_callback() {
            $email_capture_response = isset($this->options['email_capture_response']) ? $this->options['email_capture_response'] : 'Thank you for providing your email! You\'ve been added to our list.';
            echo '<textarea id="email_capture_response" name="mxchat_options[email_capture_response]" rows="3" cols="50">' . esc_textarea($email_capture_response) . '</textarea>';
            echo '<p class="description">Enter the message to send when a user provides their email.</p>';
        }


    public function mxchat_pre_chat_message_callback() {
        printf(
            '<textarea id="pre_chat_message" name="mxchat_options[pre_chat_message]" rows="5" cols="50">%s</textarea>',
            isset($this->options['pre_chat_message']) ? esc_textarea($this->options['pre_chat_message']) : ''
        );
    }

    // Callback for AI Instructions textarea
    public function system_prompt_instructions_callback() {
        printf(
            '<textarea id="system_prompt_instructions" name="mxchat_options[system_prompt_instructions]" rows="5" cols="50">%s</textarea>',
            isset($this->options['system_prompt_instructions']) ? esc_textarea($this->options['system_prompt_instructions']) : ''
        );
    }

public function mxchat_model_callback() {
    $models = array(
        'X.AI Models' => array(
            'grok-beta' => 'grok-beta (Early Beta)'
        ),
        'Claude Models' => array(
            'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet (Most Intelligent)',
            'claude-3-opus-20240229' => 'Claude 3 Opus (Highly Complex Tasks)',
            'claude-3-sonnet-20240229' => 'Claude 3 Sonnet (Balanced)',
            'claude-3-haiku-20240307' => 'Claude 3 Haiku (Fastest)'
        ),
        'OpenAI Models' => array(
            'gpt-4o' => 'GPT-4o (Recommended)',
            'gpt-4o-mini' => 'GPT-4o Mini (Fast and Lightweight)',
            'gpt-4-turbo' => 'GPT-4 Turbo (High-Performance)',
            'gpt-4' => 'GPT-4 (High Intelligence)',
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo (Affordable and Fast)'
        )
    );

    $selected_model = isset($this->options['model']) ? $this->options['model'] : 'gpt-3.5-turbo';

    echo '<select id="model" name="mxchat_options[model]">';

    foreach ($models as $group_label => $group_models) {
        echo '<optgroup label="' . esc_attr($group_label) . '">';

        foreach ($group_models as $model_value => $model_label) {
            // Disable if not activated for paid models (Claude or X.AI)
            $disabled = (!$this->is_activated && ($group_label === 'X.AI Models' || $group_label === 'Claude Models')) ? 'disabled' : '';
            $label_suffix = (!$this->is_activated && ($group_label === 'X.AI Models' || $group_label === 'Claude Models')) ? ' (Pro Only)' : '';

            // Output the <option> element
            echo '<option value="' . esc_attr($model_value) . '" ' . selected($selected_model, $model_value, false) . ' ' . $disabled . '>' . esc_html($model_label . $label_suffix) . '</option>';
        }

        echo '</optgroup>';
    }

    echo '</select>';
}



    public function mxchat_top_bar_title_callback() {
        printf(
            '<input type="text" id="top_bar_title" name="mxchat_options[top_bar_title]" value="%s" />',
            isset($this->options['top_bar_title']) ? esc_attr($this->options['top_bar_title']) : ''
        );
    }

    public function mxchat_intro_message_callback() {
        printf(
            '<textarea id="intro_message" name="mxchat_options[intro_message]" rows="5" cols="50">%s</textarea>',
            isset($this->options['intro_message']) ? esc_textarea($this->options['intro_message']) : 'Hello! How can I assist you today?'
        );
    }

    public function mxchat_input_copy_callback() {
        printf(
            '<input type="text" id="input_copy" name="mxchat_options[input_copy]" value="%s" placeholder="How can I assist?" />',
            isset($this->options['input_copy']) ? esc_attr($this->options['input_copy']) : 'How can I assist?'
        );
        echo '<p class="description">This is the placeholder text for the chat input field.</p>';
    }






    public function mxchat_close_button_color_callback() {
    $disabled = $this->is_activated ? '' : 'disabled';

    echo '<div class="pro-feature-wrapper">';
    printf(
        '<input type="text" id="close_button_color" name="mxchat_options[close_button_color]" value="%s" class="my-color-field" data-default-color="#4a4a4a" %s />',
        isset($this->options['close_button_color']) ? esc_attr($this->options['close_button_color']) : '',
        esc_attr($disabled)
    );

    if (!$this->is_activated) {
        echo '<div class="pro-feature-overlay">';
        echo '<a href="https://mxchat.ai/" target="_blank"><img src="' . plugin_dir_url(__FILE__) . '../images/pro-only-dark.png" alt="Pro Only" /></a>';
        echo '</div>';
    }

    echo '</div>';
    }

    public function mxchat_chatbot_bg_color_callback() {
        $disabled = $this->is_activated ? '' : 'disabled';

        echo '<div class="pro-feature-wrapper">';
        printf(
            '<input type="text" id="chatbot_bg_color" name="mxchat_options[chatbot_bg_color]" value="%s" class="my-color-field" data-default-color="#f9f9f9" %s />',
            isset($this->options['chatbot_bg_color']) ? esc_attr($this->options['chatbot_bg_color']) : '',
            esc_attr($disabled)
        );

        if (!$this->is_activated) {
            echo '<div class="pro-feature-overlay">';
            echo '<a href="https://mxchat.ai/" target="_blank"><img src="' . plugin_dir_url(__FILE__) . '../images/pro-only-dark.png" alt="Pro Only" /></a>';
            echo '</div>';
        }

        echo '</div>';
    }

    public function mxchat_user_message_bg_color_callback() {
        $disabled = $this->is_activated ? '' : 'disabled';

        echo '<div class="pro-feature-wrapper">';
        printf(
            '<input type="text" id="user_message_bg_color" name="mxchat_options[user_message_bg_color]" value="%s" class="my-color-field" data-default-color="#0078d7" %s />',
            isset($this->options['user_message_bg_color']) ? esc_attr($this->options['user_message_bg_color']) : '',
            esc_attr($disabled)
        );

        if (!$this->is_activated) {
            echo '<div class="pro-feature-overlay">';
            echo '<a href="https://mxchat.ai/" target="_blank"><img src="' . plugin_dir_url(__FILE__) . '../images/pro-only-dark.png" alt="Pro Only" /></a>';
            echo '</div>';
        }

        echo '</div>';
    }

    public function mxchat_user_message_font_color_callback() {
        $disabled = $this->is_activated ? '' : 'disabled';

        echo '<div class="pro-feature-wrapper">';
        printf(
            '<input type="text" id="user_message_font_color" name="mxchat_options[user_message_font_color]" value="%s" class="my-color-field" data-default-color="#ffffff" %s />',
            isset($this->options['user_message_font_color']) ? esc_attr($this->options['user_message_font_color']) : '',
            esc_attr($disabled)
        );

        if (!$this->is_activated) {
            echo '<div class="pro-feature-overlay">';
            echo '<a href="https://mxchat.ai/" target="_blank"><img src="' . plugin_dir_url(__FILE__) . '../images/pro-only-dark.png" alt="Pro Only" /></a>';
            echo '</div>';
        }

        echo '</div>';
    }

    public function mxchat_bot_message_bg_color_callback() {
        $disabled = $this->is_activated ? '' : 'disabled';

        echo '<div class="pro-feature-wrapper">';
        printf(
            '<input type="text" id="bot_message_bg_color" name="mxchat_options[bot_message_bg_color]" value="%s" class="my-color-field" data-default-color="#e1e1e1" %s />',
            isset($this->options['bot_message_bg_color']) ? esc_attr($this->options['bot_message_bg_color']) : '',
            esc_attr($disabled)
        );

        if (!$this->is_activated) {
            echo '<div class="pro-feature-overlay">';
            echo '<a href="https://mxchat.ai/" target="_blank"><img src="' . plugin_dir_url(__FILE__) . '../images/pro-only-dark.png" alt="Pro Only" /></a>';
            echo '</div>';
        }

        echo '</div>';
    }

    public function mxchat_bot_message_font_color_callback() {
        $disabled = $this->is_activated ? '' : 'disabled';

        echo '<div class="pro-feature-wrapper">';
        printf(
            '<input type="text" id="bot_message_font_color" name="mxchat_options[bot_message_font_color]" value="%s" class="my-color-field" data-default-color="#333333" %s />',
            isset($this->options['bot_message_font_color']) ? esc_attr($this->options['bot_message_font_color']) : '',
            esc_attr($disabled)
        );

        if (!$this->is_activated) {
            echo '<div class="pro-feature-overlay">';
            echo '<a href="https://mxchat.ai/" target="_blank"><img src="' . plugin_dir_url(__FILE__) . '../images/pro-only-dark.png" alt="Pro Only" /></a>';
            echo '</div>';
        }

        echo '</div>';
    }

    public function mxchat_top_bar_bg_color_callback() {
        $disabled = $this->is_activated ? '' : 'disabled';

        echo '<div class="pro-feature-wrapper">';
        printf(
            '<input type="text" id="top_bar_bg_color" name="mxchat_options[top_bar_bg_color]" value="%s" class="my-color-field" data-default-color="#00b294" %s />',
            isset($this->options['top_bar_bg_color']) ? esc_attr($this->options['top_bar_bg_color']) : '',
            esc_attr($disabled)
        );

        if (!$this->is_activated) {
            echo '<div class="pro-feature-overlay">';
            echo '<a href="https://mxchat.ai/" target="_blank"><img src="' . plugin_dir_url(__FILE__) . '../images/pro-only-dark.png" alt="Pro Only" /></a>';
            echo '</div>';
        }

        echo '</div>';
    }

    public function mxchat_send_button_font_color_callback() {
        $disabled = $this->is_activated ? '' : 'disabled';

        echo '<div class="pro-feature-wrapper">';
        printf(
            '<input type="text" id="send_button_font_color" name="mxchat_options[send_button_font_color]" value="%s" class="my-color-field" data-default-color="#ffffff" %s />',
            isset($this->options['send_button_font_color']) ? esc_attr($this->options['send_button_font_color']) : '',
            esc_attr($disabled)
        );

        if (!$this->is_activated) {
            echo '<div class="pro-feature-overlay">';
            echo '<a href="https://mxchat.ai/" target="_blank"><img src="' . plugin_dir_url(__FILE__) . '../images/pro-only-dark.png" alt="Pro Only" /></a>';
            echo '</div>';
        }

        echo '</div>';
    }

    public function mxchat_chatbot_background_color_callback() {
        $disabled = $this->is_activated ? '' : 'disabled';

        echo '<div class="pro-feature-wrapper">';
        printf(
            '<input type="text" id="chatbot_background_color" name="mxchat_options[chatbot_background_color]" value="%s" class="my-color-field" data-default-color="#000000" %s />',
            isset($this->options['chatbot_background_color']) ? esc_attr($this->options['chatbot_background_color']) : '',
            esc_attr($disabled)
        );

        if (!$this->is_activated) {
            echo '<div class="pro-feature-overlay">';
            echo '<a href="https://mxchat.ai/" target="_blank"><img src="' . plugin_dir_url(__FILE__) . '../images/pro-only-dark.png" alt="Pro Only" /></a>';
            echo '</div>';
        }

        echo '</div>';
    }

    public function mxchat_icon_color_callback() {
        $disabled = $this->is_activated ? '' : 'disabled';

        echo '<div class="pro-feature-wrapper">';
        printf(
            '<input type="text" id="icon_color" name="mxchat_options[icon_color]" value="%s" class="my-color-field" data-default-color="#ffffff" %s />',
            isset($this->options['icon_color']) ? esc_attr($this->options['icon_color']) : '',
            esc_attr($disabled)
        );

        if (!$this->is_activated) {
            echo '<div class="pro-feature-overlay">';
            echo '<a href="https://mxchat.ai/" target="_blank"><img src="' . plugin_dir_url(__FILE__) . '../images/pro-only-dark.png" alt="Pro Only" /></a>';
            echo '</div>';
        }

        echo '</div>';
    }

    public function mxchat_chat_input_font_color_callback() {
        $disabled = $this->is_activated ? '' : 'disabled';

        echo '<div class="pro-feature-wrapper">';
        printf(
            '<input type="text" id="chat_input_font_color" name="mxchat_options[chat_input_font_color]" value="%s" class="my-color-field" data-default-color="#555555" %s />',
            isset($this->options['chat_input_font_color']) ? esc_attr($this->options['chat_input_font_color']) : '',
            esc_attr($disabled)
        );

        if (!$this->is_activated) {
            echo '<div class="pro-feature-overlay">';
            echo '<a href="https://mxchat.ai/" target="_blank"><img src="' . plugin_dir_url(__FILE__) . '../images/pro-only-dark.png" alt="Pro Only" /></a>';
            echo '</div>';
        }

        echo '</div>';
    }


public function mxchat_append_to_body_callback() {
    $append_to_body_checked = isset($this->options['append_to_body']) && $this->options['append_to_body'] === 'on' ? 'checked' : '';
    echo '<label class="toggle-switch">';
    printf(
        '<input type="checkbox" id="append_to_body" name="mxchat_options[append_to_body]" %s />',
        esc_attr($append_to_body_checked)
    );
    echo '<span class="slider"></span>';
    echo '</label>';
echo '<p class="description">Enable this option to automatically append the chat widget to the body of every page (recommended). No need to manually use the shortcode [mxchat_chatbot floating="yes"].</p>';
}


public function mxchat_privacy_toggle_callback() {
    // Check if the privacy toggle is enabled
    $privacy_toggle_checked = isset($this->options['privacy_toggle']) && $this->options['privacy_toggle'] === 'on' ? 'checked' : '';

    // Retrieve the stored privacy text if it exists
    $privacy_text = isset($this->options['privacy_text']) ? wp_kses_post($this->options['privacy_text']) : 'By chatting, you agree to our <a href="https://example.com/privacy-policy" target="_blank">privacy policy</a>.';

    // Output the toggle switch
    echo '<label class="toggle-switch">';
    printf(
        '<input type="checkbox" id="privacy_toggle" name="mxchat_options[privacy_toggle]" %s />',
        esc_attr($privacy_toggle_checked)
    );
    echo '<span class="slider"></span>';
    echo '</label>';
    echo '<p class="description">Enable this option to display a privacy notice below the chat widget.</p>';

    // Output the custom text input field
    printf(
        '<textarea id="privacy_text" name="mxchat_options[privacy_text]" rows="5" cols="50" class="regular-text">%s</textarea>',
        esc_textarea($privacy_text)
    );
    echo '<p class="description">Enter the privacy policy text. You can include HTML links.</p>';
}


public function mxchat_complianz_toggle_callback() {
    // Check if the Complianz toggle is enabled
    $complianz_toggle_checked = isset($this->options['complianz_toggle']) && $this->options['complianz_toggle'] === 'on' ? 'checked' : '';

    // Check if the plugin is activated (paid feature)
    $disabled = $this->is_activated ? '' : 'disabled';
    $class = $this->is_activated ? 'pro-feature-wrapper active' : 'pro-feature-wrapper inactive';

    echo '<div class="' . esc_attr($class) . '">';

    // Output the toggle switch
    echo '<label class="toggle-switch">';
    printf(
        '<input type="checkbox" id="complianz_toggle" name="mxchat_options[complianz_toggle]" %s %s />',
        esc_attr($complianz_toggle_checked),
        esc_attr($disabled)
    );
    echo '<span class="slider"></span>';
    echo '</label>';
    echo '<p class="description">Enable this option to apply Complianz consent logic to the chatbot.</p>';

    // If the feature is not activated, show the Pro feature overlay
    if (!$this->is_activated) {
        echo '<div class="pro-feature-overlay">';
        echo '<a href="https://mxchat.ai/" target="_blank"><img src="' . plugin_dir_url(__FILE__) . '../images/pro-only-dark.png" alt="Pro Only" /></a>';
        echo '</div>';
    }

    echo '</div>';
}





public function mxchat_link_target_toggle_callback() {
    // Check if the toggle is enabled in the options
    $link_target_toggle = isset($this->options['link_target_toggle']) && $this->options['link_target_toggle'] === 'on' ? 'checked' : '';

    // Output the toggle switch
    echo '<label class="toggle-switch">';
    printf(
        '<input type="checkbox" id="link_target_toggle" name="mxchat_options[link_target_toggle]" %s />',
        esc_attr($link_target_toggle)
    );
    echo '<span class="slider"></span>';
    echo '</label>';
    echo '<p class="description">Enable to open links in a new tab (default is to open in the same tab).</p>';
}

public function mxchat_chat_persistence_toggle_callback() {
    // Check if chat persistence toggle is enabled
    $chat_persistence_toggle_checked = isset($this->options['chat_persistence_toggle']) && $this->options['chat_persistence_toggle'] === 'on' ? 'checked' : '';

    // Check if the plugin is activated (paid feature)
    $disabled = $this->is_activated ? '' : 'disabled';
    $class = $this->is_activated ? 'pro-feature-wrapper active' : 'pro-feature-wrapper inactive';

    echo '<div class="' . esc_attr($class) . '">';

    // Output the toggle switch
    echo '<label class="toggle-switch">';
    printf(
        '<input type="checkbox" id="chat_persistence_toggle" name="mxchat_options[chat_persistence_toggle]" %s %s />',
        esc_attr($chat_persistence_toggle_checked),
        esc_attr($disabled)
    );
    echo '<span class="slider"></span>';
    echo '</label>';
    echo '<p class="description">Enable to keep chat history when users navigate tabs or return to the site within 24 hours.</p>';

    // If not activated, show Pro feature overlay
    if (!$this->is_activated) {
        echo '<div class="pro-feature-overlay">';
        echo '<a href="https://mxchat.ai/" target="_blank"><img src="' . plugin_dir_url(__FILE__) . '../images/pro-only-dark.png" alt="Pro Only" /></a>';
        echo '</div>';
    }

    echo '</div>';
}


public function mxchat_popular_question_1_callback() {
    printf(
        '<input type="text" id="popular_question_1" name="mxchat_options[popular_question_1]" value="%s" placeholder="Enter Popular Question 1" />',
        isset($this->options['popular_question_1']) ? esc_attr($this->options['popular_question_1']) : ''
    );
    echo '<p class="description">This will be the first popular question in the chatbot.</p>';
}

public function mxchat_popular_question_2_callback() {
    // Check if the plugin is activated (paid feature)
    $disabled = $this->is_activated ? '' : 'disabled';
    $class = $this->is_activated ? 'pro-feature-wrapper active' : 'pro-feature-wrapper inactive';

    echo '<div class="' . esc_attr($class) . '">';

    printf(
        '<input type="text" id="popular_question_2" name="mxchat_options[popular_question_2]" value="%s" placeholder="Enter Popular Question 2" %s />',
        isset($this->options['popular_question_2']) ? esc_attr($this->options['popular_question_2']) : '',
        esc_attr($disabled)
    );
    echo '<p class="description">This will be the second popular question in the chatbot.</p>';

    // If not activated, show Pro feature overlay
    if (!$this->is_activated) {
        echo '<div class="pro-feature-overlay">';
        echo '<a href="https://mxchat.ai/" target="_blank"><img src="' . plugin_dir_url(__FILE__) . '../images/pro-only-dark.png" alt="Pro Only" /></a>';
        echo '</div>';
    }

    echo '</div>';
}

public function mxchat_popular_question_3_callback() {
    // Check if the plugin is activated (paid feature)
    $disabled = $this->is_activated ? '' : 'disabled';
    $class = $this->is_activated ? 'pro-feature-wrapper active' : 'pro-feature-wrapper inactive';

    echo '<div class="' . esc_attr($class) . '">';

    printf(
        '<input type="text" id="popular_question_3" name="mxchat_options[popular_question_3]" value="%s" placeholder="Enter Popular Question 3" %s />',
        isset($this->options['popular_question_3']) ? esc_attr($this->options['popular_question_3']) : '',
        esc_attr($disabled)
    );
    echo '<p class="description">This will be the third popular question in the chatbot.</p>';

    // If not activated, show Pro feature overlay
    if (!$this->is_activated) {
        echo '<div class="pro-feature-overlay">';
        echo '<a href="https://mxchat.ai/" target="_blank"><img src="' . plugin_dir_url(__FILE__) . '../images/pro-only-dark.png" alt="Pro Only" /></a>';
        echo '</div>';
    }

    echo '</div>';
}






    public function mxchat_enqueue_admin_assets() {
        wp_enqueue_style('wp-color-picker');

        // Get the plugin version or file modification time for cache busting
        $plugin_version = '1.2.1'; // Replace this with your plugin's version

        // File paths
        $color_picker_js_path = plugin_dir_path(__FILE__) . '../js/my-color-picker.js';
        $embedding_check_js_path = plugin_dir_path(__FILE__) . '../js/embedding-check.js';
        $admin_css_path = plugin_dir_path(__FILE__) . '../css/admin-style.css';
        $transcripts_js_path = plugin_dir_path(__FILE__) . '../js/mxchat_transcripts.js';

        // Check if files exist and get modification times
        $color_picker_version = file_exists($color_picker_js_path) ? filemtime($color_picker_js_path) : $plugin_version;
        $embedding_check_version = file_exists($embedding_check_js_path) ? filemtime($embedding_check_js_path) : $plugin_version;
        $admin_css_version = file_exists($admin_css_path) ? filemtime($admin_css_path) : $plugin_version;
        $transcripts_js_version = file_exists($transcripts_js_path) ? filemtime($transcripts_js_path) : $plugin_version;

        // Enqueue scripts and styles with corrected paths
        wp_enqueue_script(
            'mxchat-color-picker',
            plugin_dir_url(__FILE__) . '../js/my-color-picker.js',
            array('wp-color-picker'),
            $color_picker_version,
            true
        );

        wp_enqueue_script(
            'mxchat-embedding-check',
            plugin_dir_url(__FILE__) . '../js/embedding-check.js',
            array(),
            $embedding_check_version,
            true
        );

        wp_enqueue_script(
            'mxchat-transcripts-js',
            plugin_dir_url(__FILE__) . '../js/mxchat_transcripts.js',
            array('jquery'),
            $transcripts_js_version,
            true
        );

        wp_enqueue_script(
            'mxchat-admin-js',
            plugin_dir_url(__FILE__) . '../js/mxchat-admin.js',
            array('jquery'),
            $plugin_version,
            true
        );

        wp_localize_script('mxchat-admin-js', 'mxchatAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('mxchat_activate_license_nonce'),
        ));

        wp_enqueue_style(
            'mxchat-admin-css',
            plugin_dir_url(__FILE__) . '../css/admin-style.css',
            array(),
            $admin_css_version
        );

        wp_localize_script('mxchat-color-picker', 'mxchatStyleSettings', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'link_target_toggle' => $this->options['link_target_toggle'] ?? 'off',
            'user_message_bg_color' => $this->options['user_message_bg_color'] ?? '#fff',
            'user_message_font_color' => $this->options['user_message_font_color'] ?? '#212121',
            'bot_message_bg_color' => $this->options['bot_message_bg_color'] ?? '#212121',
            'bot_message_font_color' => $this->options['bot_message_font_color'] ?? '#fff',
            'top_bar_bg_color' => $this->options['top_bar_bg_color'] ?? '#212121',
            'send_button_font_color' => $this->options['send_button_font_color'] ?? '#212121',
            'close_button_color' => $this->options['close_button_color'] ?? '#fff',
            'chatbot_background_color' => $this->options['chatbot_background_color'] ?? '#212121',
            'icon_color' => $this->options['icon_color'] ?? '#fff',
            'chat_input_font_color' => $this->options['chat_input_font_color'] ?? '#212121',
            'pre_chat_message' => $this->options['pre_chat_message'] ?? 'Hey there! Ask me anything!',
            'rate_limit_message' => $this->options['rate_limit_message'] ?? 'Rate limit exceeded. Please try again later.',

            // New fields for Loops Integration
            'loops_api_key' => $this->options['loops_api_key'] ?? '',
            'loops_mailing_list' => $this->options['loops_mailing_list'] ?? '',
            'trigger_keywords' => $this->options['trigger_keywords'] ?? '',
            'triggered_phrase_response' => $this->options['triggered_phrase_response'] ?? 'Would you like to join our mailing list? Please provide your email below.',
            'email_capture_response' => $this->options['email_capture_response'] ?? 'Thank you for providing your email! You\'ve been added to our list.'
        ));

            // Localize the script to pass the nonce and other data to JavaScript
    wp_localize_script('mxchat-admin-js', 'mxchatInlineEdit', array(
        'nonce' => wp_create_nonce('mxchat_save_inline_nonce'),
        'ajax_url' => admin_url('admin-ajax.php')
    ));


    }

    public function mxchat_sanitize($input) {
        $new_input = array();

        if (isset($input['api_key'])) {
            $new_input['api_key'] = sanitize_text_field($input['api_key']);
        }

        if (isset($input['xai_api_key'])) {
            $new_input['xai_api_key'] = sanitize_text_field($input['xai_api_key']);
        }

        if (isset($input['claude_api_key'])) {
            $new_input['claude_api_key'] = sanitize_text_field($input['claude_api_key']);
        }

        if (isset($input['enable_woocommerce_integration'])) {
            $new_input['enable_woocommerce_integration'] = isset($input['enable_woocommerce_integration']) && $input['enable_woocommerce_integration'] === '1' ? '1' : '0';

        }

        if (isset($input['privacy_toggle'])) {
            $new_input['privacy_toggle'] = $input['privacy_toggle'];
        }

        if (isset($input['complianz_toggle'])) {
            $new_input['complianz_toggle'] = $input['complianz_toggle'];
        }

        // Handle custom privacy text input
        if (isset($input['privacy_text'])) {
            // Allow basic HTML for links
            $new_input['privacy_text'] = wp_kses_post($input['privacy_text']);
        }


        if (isset($input['enable_woocommerce_order_access'])) {
            $new_input['enable_woocommerce_order_access'] = isset($input['enable_woocommerce_order_access']) && $input['enable_woocommerce_order_access'] === '1' ? '1' : '0';

        }

        if (isset($input['system_prompt_instructions'])) {
            $new_input['system_prompt_instructions'] = sanitize_textarea_field($input['system_prompt_instructions']);
        }

        if (isset($input['mxchat_pro_email'])) {
            $new_input['mxchat_pro_email'] = sanitize_email($input['mxchat_pro_email']);
        }

        if (isset($input['mxchat_activation_key'])) {
            $new_input['mxchat_activation_key'] = sanitize_text_field($input['mxchat_activation_key']);
        }

        if (isset($input['append_to_body'])) {
            $new_input['append_to_body'] = $input['append_to_body'] === 'on' ? 'on' : 'off';
        }

        if (isset($input['top_bar_title'])) {
            $new_input['top_bar_title'] = sanitize_text_field($input['top_bar_title']);
        }

        if (isset($input['intro_message'])) {
            $new_input['intro_message'] = sanitize_text_field($input['intro_message']);
        }

        if (isset($input['input_copy'])) {
            $new_input['input_copy'] = sanitize_text_field($input['input_copy']);
        }


        if (isset($input['rate_limit_message'])) {
            $new_input['rate_limit_message'] = sanitize_text_field($input['rate_limit_message']);
        }

    if (isset($input['rate_limit'])) {
        $allowed_limits = array('5', '10', '15', '20', '100', 'unlimited'); // Add 'unlimited' to allowed values
        $rate_limit = sanitize_text_field($input['rate_limit']);

        if (in_array($rate_limit, $allowed_limits, true)) {
            $new_input['rate_limit'] = $rate_limit;
        } else {
            // Set a default or fallback value in case of an invalid entry
            $new_input['rate_limit'] = '100';
        }
    }


        if (isset($input['pre_chat_message'])) {
            $new_input['pre_chat_message'] = sanitize_textarea_field($input['pre_chat_message']);
        }

        if (isset($input['model'])) {
            $allowed_models = array(
                'grok-beta',
                'claude-3-5-sonnet-20241022',
                'claude-3-opus-20240229',
                'claude-3-sonnet-20240229',
                'claude-3-haiku-20240307',
                'gpt-4o',
                'gpt-4o-mini',
                'gpt-4-turbo',
                'gpt-4',
                'gpt-3.5-turbo',
            );
            if (in_array($input['model'], $allowed_models)) {
                $new_input['model'] = sanitize_text_field($input['model']);
            }
        }

        // Sanitize new pro features
        if (isset($input['close_button_color'])) {
            $new_input['close_button_color'] = sanitize_hex_color($input['close_button_color']);
        }

        if (isset($input['chatbot_bg_color'])) {
            $new_input['chatbot_bg_color'] = sanitize_hex_color($input['chatbot_bg_color']);
        }

        if (isset($input['woocommerce_consumer_key'])) {
            $new_input['woocommerce_consumer_key'] = sanitize_text_field($input['woocommerce_consumer_key']);
        }

        if (isset($input['woocommerce_consumer_secret'])) {
            $new_input['woocommerce_consumer_secret'] = sanitize_text_field($input['woocommerce_consumer_secret']);
        }

        if (isset($input['user_message_bg_color'])) {
            $new_input['user_message_bg_color'] = sanitize_hex_color($input['user_message_bg_color']);
        }

        if (isset($input['user_message_font_color'])) {
            $new_input['user_message_font_color'] = sanitize_hex_color($input['user_message_font_color']);
        }

        if (isset($input['bot_message_bg_color'])) {
            $new_input['bot_message_bg_color'] = sanitize_hex_color($input['bot_message_bg_color']);
        }

        if (isset($input['bot_message_font_color'])) {
            $new_input['bot_message_font_color'] = sanitize_hex_color($input['bot_message_font_color']);
        }

        if (isset($input['top_bar_bg_color'])) {
            $new_input['top_bar_bg_color'] = sanitize_hex_color($input['top_bar_bg_color']);
        }

        if (isset($input['send_button_font_color'])) {
            $new_input['send_button_font_color'] = sanitize_hex_color($input['send_button_font_color']);
        }

        if (isset($input['chatbot_background_color'])) {
            $new_input['chatbot_background_color'] = sanitize_hex_color($input['chatbot_background_color']);
        }

        if (isset($input['icon_color'])) {
            $new_input['icon_color'] = sanitize_hex_color($input['icon_color']);
        }

        if (isset($input['chat_input_font_color'])) {
            $new_input['chat_input_font_color'] = sanitize_hex_color($input['chat_input_font_color']);
        }

        // Sanitize link_target_toggle
        if (isset($input['link_target_toggle'])) {
            $new_input['link_target_toggle'] = $input['link_target_toggle'] === 'on' ? 'on' : 'off';
        }

        // Sanitize Loops API Key
    if (isset($input['loops_api_key'])) {
        $new_input['loops_api_key'] = sanitize_text_field($input['loops_api_key']);
    }

    if (isset($input['chat_persistence_toggle'])) {
        $new_input['chat_persistence_toggle'] = $input['chat_persistence_toggle'] === 'on' ? 'on' : 'off';
    }



if (isset($input['popular_question_1'])) {
    $new_input['popular_question_1'] = sanitize_text_field($input['popular_question_1']);
}

if (isset($input['popular_question_2'])) {
    $new_input['popular_question_2'] = sanitize_text_field($input['popular_question_2']);
}

if (isset($input['popular_question_3'])) {
    $new_input['popular_question_3'] = sanitize_text_field($input['popular_question_3']);
}


    // Sanitize Loops Mailing List
    if (isset($input['loops_mailing_list'])) {
        $new_input['loops_mailing_list'] = sanitize_text_field($input['loops_mailing_list']);
    }

    // Sanitize Trigger Keywords
    if (isset($input['trigger_keywords'])) {
        $new_input['trigger_keywords'] = sanitize_text_field($input['trigger_keywords']);
    }

    // Sanitize Triggered Phrase Response
    if (isset($input['triggered_phrase_response'])) {
        $new_input['triggered_phrase_response'] = wp_kses_post($input['triggered_phrase_response']);
    }

    if (isset($input['email_capture_response'])) {
        $new_input['email_capture_response'] = sanitize_textarea_field($input['email_capture_response']);
    }

        return $new_input;
    }


    // Method to append the chatbot to the body
    public function mxchat_append_chatbot_to_body() {
        $options = get_option('mxchat_options');
        if (isset($options['append_to_body']) && $options['append_to_body'] === 'on') {
            echo do_shortcode('[mxchat_chatbot floating="yes"]');
        }
    }



private function mxchat_extract_main_content($html) {
    $dom = new DOMDocument;
    libxml_use_internal_errors(true); // Suppress HTML parsing errors
    @$dom->loadHTML($html);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);

    // Simplified selectors focusing on common content areas
    $selectors = [
        '//article',
        '//*[@id="content"]',
        '//*[@class="entry-content"]',
        '//main',
    ];

    foreach ($selectors as $selector) {
        $nodes = $xpath->query($selector);
        if ($nodes->length > 0) {
            $content = '';
            foreach ($nodes as $node) {
                $content .= $dom->saveHTML($node);
            }
            return $content;
        }
    }

    // Fallback: Return the entire body content if no specific selector matches
    $body = $dom->getElementsByTagName('body');
    return $body->length > 0 ? $dom->saveHTML($body->item(0)) : $html;
}

public function mxchat_handle_sitemap_submission() {
    // Check if the form was submitted and the user has sufficient permissions
    if (!isset($_POST['submit_sitemap']) || !current_user_can('manage_options')) {
        return;
    }

    // Verify the nonce field for security
    check_admin_referer('mxchat_submit_sitemap_action', 'mxchat_submit_sitemap_nonce');

    // Sanitize and retrieve the submitted URL
    $submitted_url = esc_url_raw($_POST['sitemap_url']); // Accept either a sitemap URL or a regular URL

    // Fetch the content of the submitted URL
    $response = wp_remote_get($submitted_url);
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        set_transient('mxchat_admin_notice_error', 'Failed to fetch the URL. Please check the URL and try again.', 30);
        wp_safe_redirect(esc_url(admin_url('admin.php?page=mxchat-prompts')));
        exit;
    }

    $content_type = wp_remote_retrieve_header($response, 'content-type');
    $body_content = wp_remote_retrieve_body($response);

    // Check if the URL points to a sitemap (XML) or a regular HTML page
    if (strpos($content_type, 'xml') !== false || strpos($body_content, '<urlset') !== false) {
        // Handle Sitemap XML
        $xml = simplexml_load_string($body_content);
        if ($xml === false) {
            set_transient('mxchat_admin_notice_error', 'Invalid sitemap XML. Please provide a valid sitemap.', 30);
            wp_safe_redirect(esc_url(admin_url('admin.php?page=mxchat-prompts')));
            exit;
        }

        $embedding_success = true; // Flag to track if all embeddings are successful

        foreach ($xml->url as $url_element) {
            $page_url = (string)$url_element->loc;

            $page_response = wp_remote_get($page_url);
            if (is_wp_error($page_response) || wp_remote_retrieve_response_code($page_response) !== 200) {
                continue;
            }

            $page_html = wp_remote_retrieve_body($page_response);
            $page_content = $this->mxchat_extract_main_content($page_html);
            $sanitized_content = $this->mxchat_sanitize_content_for_api($page_content);

            if (!empty($sanitized_content)) {
                $embedding_vector = $this->mxchat_generate_embedding($sanitized_content);
                if (is_array($embedding_vector)) {
                    MxChat_Utils::submit_content_to_db($sanitized_content, $page_url, $this->options['api_key']);
                } else {
                    $embedding_success = false; // Set flag to false if any embedding fails
                }
            }
        }

        if ($embedding_success) {
            set_transient('mxchat_admin_notice_success', 'Sitemap content successfully submitted!', 30);
        } else {
            set_transient('mxchat_admin_notice_error', 'Some content failed to embed. Please check your API key and try again.', 30);
        }

    } else {
        // Handle Regular URL (HTML Page)
        $page_content = $this->mxchat_extract_main_content($body_content);
        $sanitized_content = $this->mxchat_sanitize_content_for_api($page_content);

        if (!empty($sanitized_content)) {
            $embedding_vector = $this->mxchat_generate_embedding($sanitized_content);
            if (is_array($embedding_vector)) {
                MxChat_Utils::submit_content_to_db($sanitized_content, $submitted_url, $this->options['api_key']);
                set_transient('mxchat_admin_notice_success', 'URL content successfully submitted!', 30);
            } else {
                set_transient('mxchat_admin_notice_error', 'Failed to generate embedding for the URL content. Please check your API key and try again.', 30);
            }
        } else {
            set_transient('mxchat_admin_notice_error', 'No valid content found on the provided URL.', 30);
        }
    }

    // Redirect after setting the transient
    wp_safe_redirect(esc_url(admin_url('admin.php?page=mxchat-prompts')));
    exit;
}



private function mxchat_sanitize_content_for_api($content) {
    // Remove script, style tags, and HTML comments
    $content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $content);
    $content = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $content);
    $content = preg_replace('/<!--(.|\s)*?-->/', '', $content);

    // Remove all HTML tags and decode HTML entities
    $content = wp_strip_all_tags($content);
    $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5);

    // Trim and normalize whitespace
    $content = trim(preg_replace('/\s+/', ' ', $content));

    return $content;
}



private function mxchat_fetch_loops_mailing_lists($api_key) {
    $url = 'https://app.loops.so/api/v1/lists';
    $response = wp_remote_get($url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        )
    ));

    if (is_wp_error($response)) {
        return array();
    }

    $body = wp_remote_retrieve_body($response);
    $lists = json_decode($body, true);

    return isset($lists) && is_array($lists) ? $lists : array();
}



}
?>
