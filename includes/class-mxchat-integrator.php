<?php
if (!defined('ABSPATH')) {
    exit;
}

class MxChat_Integrator {
    private $options;
    private $chat_count;

public function __construct() {
    $this->options = get_option('mxchat_options');
    $this->chat_count = get_option('mxchat_chat_count', 0);

    // Add WooCommerce hooks
    add_action('wp_insert_post', array($this, 'mxchat_handle_product_change'), 10, 3);

    // Ensure embeddings are removed when a product is moved to trash or permanently deleted
    add_action('wp_trash_post', array($this, 'mxchat_handle_product_delete'));
    add_action('before_delete_post', array($this, 'mxchat_handle_product_delete'));

    add_action('wp_enqueue_scripts', array($this, 'mxchat_enqueue_scripts_styles'));
    add_action('wp_ajax_mxchat_handle_chat_request', array($this, 'mxchat_handle_chat_request'));
    add_action('wp_ajax_nopriv_mxchat_handle_chat_request', array($this, 'mxchat_handle_chat_request'));

    add_action('wp_ajax_mxchat_dismiss_pre_chat_message', array($this, 'mxchat_dismiss_pre_chat_message'));
    add_action('wp_ajax_nopriv_mxchat_dismiss_pre_chat_message', array($this, 'mxchat_dismiss_pre_chat_message'));
    // Add the AJAX actions for checking if the pre-chat message was dismissed
    add_action('wp_ajax_mxchat_check_pre_chat_message_status', array($this, 'mxchat_check_pre_chat_message_status'));
    add_action('wp_ajax_nopriv_mxchat_check_pre_chat_message_status', array($this, 'mxchat_check_pre_chat_message_status'));

    add_action('wp_ajax_mxchat_fetch_conversation_history', [$this, 'mxchat_fetch_conversation_history']);
    add_action('wp_ajax_nopriv_mxchat_fetch_conversation_history', [$this, 'mxchat_fetch_conversation_history']);


        add_action('wp_ajax_mxchat_add_to_cart', [$this, 'mxchat_add_to_cart']);
        add_action('wp_ajax_nopriv_mxchat_add_to_cart', [$this, 'mxchat_add_to_cart']);

    if (!wp_next_scheduled('mxchat_reset_rate_limits')) {
        wp_schedule_event(time(), 'daily', 'mxchat_reset_rate_limits');
    }

    add_action('mxchat_reset_rate_limits', array($this, 'mxchat_reset_rate_limits'));
}

public function mxchat_handle_product_change($post_id, $post, $update) {
    // Ensure this is a product post type
    if ($post->post_type !== 'product') {
        return;
    }

    // Only generate embeddings if the product is published
    if ($post->post_status === 'publish') {
        // Delay the embedding slightly to ensure all product data is available
        add_action('shutdown', function() use ($post_id) {
            $product = wc_get_product($post_id);
            if ($product && $product->get_price() !== '') {
                $this->mxchat_store_product_embedding($product);
            } else {
                // Optionally, log or handle the case where product data is incomplete
               // error_log("Product {$post_id} does not have complete data. Embedding not generated.");
            }
        });
    }
}

public function mxchat_handle_product_delete($post_id) {
    if (get_post_type($post_id) !== 'product') {
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'mxchat_system_prompt_content';

    // Delete the embedding associated with this product
    $wpdb->delete($table_name, array('source_url' => get_permalink($post_id)), array('%s'));
}

private function mxchat_store_product_embedding($product) {
    if (isset($this->options['enable_woocommerce_integration']) && $this->options['enable_woocommerce_integration'] === '1') {

        $source_url = get_permalink($product->get_id());
        $regular_price = $product->get_regular_price();
        $sale_price = $product->get_sale_price();
        $price = $sale_price ?: $regular_price;

        $description = $product->get_description() . "\n\n" .
                       "Short Description: " . $product->get_short_description() . "\n" .
                       "Price: " . $regular_price . "\n" .
                       "Sale Price: " . ($sale_price ?: 'N/A') . "\n" .
                       "SKU: " . $product->get_sku();

        global $wpdb;
        $table_name = $wpdb->prefix . 'mxchat_system_prompt_content';

        // Delete any existing embedding for this product
        $wpdb->delete($table_name, array('source_url' => $source_url), array('%s'));

        // Submit the new content and embedding to the database
        MxChat_Utils::submit_content_to_db($description, $source_url, $this->options['api_key']);
    }
}





    private function mxchat_increment_chat_count() {
        $chat_count = get_option('mxchat_chat_count', 0);
        $chat_count++;
        update_option('mxchat_chat_count', $chat_count);
    }

function mxchat_fetch_conversation_history() {
    if (empty($_POST['session_id'])) {
        wp_send_json_error(['message' => 'Session ID missing.']);
        wp_die();
    }

    $session_id = sanitize_text_field($_POST['session_id']);
    $history = get_option("mxchat_history_{$session_id}", []); // Retrieve stored history

    if (empty($history)) {
        wp_send_json_error(['message' => 'No history found.']);
        wp_die();
    }

    wp_send_json_success(['conversation' => $history]);
    wp_die();
}
private function mxchat_fetch_conversation_history_for_ajax($session_id) {
    $history = get_option("mxchat_history_{$session_id}", []); // Retrieve stored history based on session ID
    $formatted_history = [];

    // Format the history to align with the expected structure for OpenAI
    foreach ($history as $entry) {
        $formatted_history[] = [
            'role' => $entry['role'],  // Ensure this matches 'user' or 'assistant'
            'content' => $entry['content']
        ];
    }

    return $formatted_history;
}


private function mxchat_save_chat_message($session_id, $role, $message) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'mxchat_chat_transcripts';

    $user_id = is_user_logged_in() ? get_current_user_id() : 0;
    $user_identifier = MxChat_User::mxchat_get_user_identifier();
    $user_email = MxChat_User::mxchat_get_user_email();

    $history = get_option("mxchat_history_{$session_id}", []);
    $history[] = ['role' => $role, 'content' => $message];
    update_option("mxchat_history_{$session_id}", $history);

    $wpdb->insert($table_name, [
        'user_id' => $user_id,
        'user_identifier' => $user_identifier,
        'user_email' => $user_email,
        'session_id' => $session_id,
        'role' => $role,
        'message' => $message,
        'timestamp' => current_time('mysql', 1)
    ]);
}


public function mxchat_handle_chat_request() {
    global $wpdb;

    // Get and sanitize the user identifier
    $user_id = $this->mxchat_get_user_identifier();
    $user_id = sanitize_key($user_id);

    // Setup rate limiting
    $rate_limit_transient_key = 'mxchat_chat_limit_' . $user_id;
    $chat_count = get_transient($rate_limit_transient_key) ?: 0;

    // Retrieve the session ID from the client's POST data
    $session_id = isset($_POST['session_id']) ? sanitize_text_field($_POST['session_id']) : '';

    if (empty($session_id)) {
        wp_send_json_error('Session ID is missing.');
        wp_die();
    }

    // Check rate limit
    $rate_limit_option = $this->options['rate_limit'] ?? 'unlimited';
    if ($rate_limit_option !== 'unlimited' && $chat_count >= intval($rate_limit_option)) {
        wp_send_json_error(['message' => $this->options['rate_limit_message'] ?? 'Rate limit exceeded. Please try again later.']);
        wp_die();
    }

    // Increment chat count only if limit is not 'unlimited'
    if ($rate_limit_option !== 'unlimited') {
        set_transient($rate_limit_transient_key, $chat_count + 1, DAY_IN_SECONDS);
    }
    // Validate and sanitize the incoming message
    if (empty($_POST['message'])) {
        wp_send_json_error('No message received');
        wp_die();
    }

    $message = sanitize_text_field($_POST['message']);
    $this->mxchat_save_chat_message($session_id, 'user', $message);

    // Track email capture and WooCommerce flows with individual transients
    $email_capture_prompt = get_transient('mxchat_email_capture_' . $user_id);
    $interaction_count = get_transient('mxchat_email_interaction_count_' . $user_id) ?: 0;
    $woocommerce_prompt = get_transient('mxchat_woocommerce_prompt_' . $user_id);

    // Handle email capture flow
    if ($email_capture_prompt) {
        if (preg_match('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i', $message, $matches)) {
            $email = $matches[0];
            $this->add_email_to_loops($email);
            $response = $this->options['email_capture_response'] ?? 'Thank you for providing your email! You\'ve been added to our list.';

            delete_transient('mxchat_email_capture_' . $user_id);
            delete_transient('mxchat_email_interaction_count_' . $user_id);
            delete_transient('mxchat_woocommerce_prompt_' . $user_id);

            $this->mxchat_save_chat_message($session_id, 'bot', $response);
            wp_send_json(['message' => $response]);
            wp_die();
        } else {
            if ($interaction_count >= 3) {
                delete_transient('mxchat_email_capture_' . $user_id);
                delete_transient('mxchat_email_interaction_count_' . $user_id);
            } else {
                set_transient('mxchat_email_interaction_count_' . $user_id, ++$interaction_count, 5 * MINUTE_IN_SECONDS);
            }
        }
    }

    // Handle WooCommerce add-to-cart flow
    if (class_exists('WooCommerce') && stripos($message, 'add to cart') !== false) {
        $last_product_id = get_transient('mxchat_last_discussed_product_' . $user_id);
        if ($last_product_id) {
            $added = WC()->cart->add_to_cart($last_product_id);
            $product = wc_get_product($last_product_id);

            if ($added) {
                $response = "The product '{$product->get_name()}' has been added to your cart. To proceed to checkout, please type 'checkout'.";
                set_transient('mxchat_checkout_prompt_' . $user_id, true, 5 * MINUTE_IN_SECONDS);
                $this->mxchat_save_chat_message($session_id, 'bot', $response);
                wp_send_json(['message' => $response]);
                wp_die();
            } else {
                $response = "Sorry, I couldn't add the product to your cart. Please try again.";
                $this->mxchat_save_chat_message($session_id, 'bot', $response);
                wp_send_json(['message' => $response]);
                wp_die();
            }
        } else {
            $response = "I couldn't find the product to add. Please mention the product name again.";
            $this->mxchat_save_chat_message($session_id, 'bot', $response);
            wp_send_json(['message' => $response]);
            wp_die();
        }
    }

    // Handle checkout response
    if (class_exists('WooCommerce') && stripos($message, 'checkout') !== false) {
        $checkout_prompt = get_transient('mxchat_checkout_prompt_' . $user_id);
        if ($checkout_prompt && WC()->cart->get_cart_contents_count() > 0) {
            $checkout_url = wc_get_checkout_url();
            $response = "Great! Redirecting you to the checkout page...";
            delete_transient('mxchat_checkout_prompt_' . $user_id);

            $this->mxchat_save_chat_message($session_id, 'bot', $response);
            wp_send_json(['message' => $response, 'redirect_url' => $checkout_url]);
            wp_die();
        } else {
            $response = "It seems like there is no active checkout prompt or no items in your cart. Please add a product to the cart first.";
            $this->mxchat_save_chat_message($session_id, 'bot', $response);
            wp_send_json(['message' => $response]);
            wp_die();
        }
    }

    // Handle order-related queries
    if (class_exists('WooCommerce') && MxChat_WooCommerce::mxchat_is_order_related_query($message)) {
        $response = MxChat_WooCommerce::mxchat_fetch_user_orders_details();
        $this->mxchat_save_chat_message($session_id, 'bot', $response);
        wp_send_json(['message' => $response]);
        wp_die();
    }

    // Check for trigger keywords to initiate email capture
    $trigger_keywords = explode(',', $this->options['trigger_keywords'] ?? '');
    if (!empty($trigger_keywords) && $trigger_keywords[0] !== '') {
        foreach ($trigger_keywords as $keyword) {
            if (stripos($message, trim($keyword)) !== false) {
                $response = $this->options['triggered_phrase_response'] ?? "Would you like to join our mailing list? Please provide your email below.";
                set_transient('mxchat_email_capture_' . $user_id, true, 5 * MINUTE_IN_SECONDS);
                $this->mxchat_save_chat_message($session_id, 'bot', $response);
                wp_send_json(['message' => $response]);
                wp_die();
            }
        }
    }

    // Check if the user's query is product-related
    $productCardHtml = '';
    $productRelatedQuery = $this->is_product_related_query($message);

    // Check if WooCommerce is active
    if (class_exists('WooCommerce') && $productRelatedQuery) {
        // Check if there's a previously discussed product for follow-up queries
        $last_product_id = get_transient('mxchat_last_discussed_product_' . $user_id);
        $product_id = null;

        // Attempt to extract product ID directly from the message
        $product_id = MxChat_WooCommerce::mxchat_extract_product_id_from_message($message);

        // If no specific product ID is mentioned but there's a previous one, use it
        if (!$product_id && $last_product_id) {
            $product_id = $last_product_id;
        }

        // Generate the product card if a valid product ID is available
        if ($product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                $product_name = $product->get_name();
                $product_price = $product->get_price_html();
                $product_image_url = wp_get_attachment_url($product->get_image_id());
                $product_url = get_permalink($product_id);

                // Prepare the product card HTML for later inclusion
                $productCardHtml = "
                    <div class='product-card'>
                        <a href='" . esc_url($product_url) . "' target='_blank'>
                            <img src='" . esc_url($product_image_url) . "' alt='" . esc_attr($product_name) . "' class='product-image' />
                            <h3 class='product-name'>" . esc_html($product_name) . "</h3>
                        </a>
                        <div class='product-price'>{$product_price}</div>
                        <button class='add-to-cart-button' data-product-id='{$product_id}'>Add to Cart</button>
                    </div>
                ";

                // Update the last discussed product transient
                set_transient('mxchat_last_discussed_product_' . $user_id, $product_id, 3600);
            }
        }
    }

    // Generate the AI response
    $user_message_embedding = $this->mxchat_generate_embedding($message, $this->options['api_key']);
    if (!is_array($user_message_embedding)) {
        wp_send_json_error('Error processing your message.');
        wp_die();
    }

    $relevant_content = $this->mxchat_find_relevant_content($user_message_embedding);
    $conversation_history = $this->mxchat_fetch_conversation_history_for_ajax($session_id);
    $this->mxchat_increment_chat_count();
    $response = $this->mxchat_generate_response(
        $relevant_content,
        $this->options['api_key'],
        $this->options['xai_api_key'],
        $this->options['claude_api_key'],
        $conversation_history
    );

    // Append product card HTML only if it was generated
    $fullResponse = $response; // Do not include HTML in the conversation history

    // Save response in chat history without HTML
    $this->mxchat_save_chat_message($session_id, 'bot', $response);

    // Send response and product card
    wp_send_json([
        'text' => $response,
        'html' => $productCardHtml ?? '',
        'session_id' => $session_id
    ]);

    wp_die();
}



// Refined function to detect if the user query is product-related
private function is_product_related_query($message) {
    // Keywords and phrases that indicate a product-related query
    $product_related_terms = [
        // General inquiries
        'price', 'cost', 'details', 'buy', 'order', 'purchase', 'specifications',
        'specs', 'features', 'information', 'info', 'tell me about', 'learn about',
        'interested in', 'looking for', 'show me', 'more about', 'more info',
        'product information', 'product details', 'product specs', 'product features',

        // Pricing and discounts
        'how much', 'what is the price', 'what does it cost', 'price of', 'cost of',

        // Availability and stock
        'availability', 'in stock', 'out of stock', 'stock availability', 'available',
        'when will it be available', 'is it available', 'back in stock', 'restock',
        'restocking soon', 'can i get', 'could i get', 'do you have', 'do you sell',
        'is there', 'are there any',

        // Purchase actions
        'how to buy',
        'how do i buy', 'how can i purchase', 'how to purchase', 'how to order',
        'how do i order', 'want to buy', 'want to purchase', 'want to order',
        'interested in buying', 'interested in purchasing',

        // Comparisons and recommendations
        'compare', 'comparison', 'difference between', 'differences between',
        'better than', 'which is better', 'recommend', 'recommendation', 'suggest',
        'suggestion', 'what do you recommend', 'what do you suggest',

                // Payment and financing
        'payment', 'payment options', 'pay with', 'credit card', 'paypal', 'financing',
        'installments', 'payment plans', 'layaway', 'buy now pay later', 'bnpl',

        // Miscellaneous
        'how does it work', 'explain', 'can you explain', 'tell me how',
        'show me how', 'video', 'demo', 'demonstration', 'tutorial', 'guide',
        'manual', 'instructions', 'datasheet', 'catalog', 'brochure',

        // Custom inquiries
        'can i see', 'i need', 'looking to', 'planning to', 'thinking of',
        'need more info', 'need details', 'provide information on', 'inquiring about',
        'have questions about', 'want to know', 'wondering about', 'curious about',

        // Action prompts
        'book', 'schedule', 'reserve', 'subscribe', 'sign up', 'enroll',
        'register', 'apply', 'get access to', 'download',
    ];

    // Normalize message for matching
    $normalized_message = strtolower($message);
    $normalized_message = preg_replace("/[^\w\s]/u", "", $normalized_message);

    // Check for any matching term
    foreach ($product_related_terms as $term) {
        if (stripos($normalized_message, $term) !== false) {
            return true; // Return true on the first match, avoiding double triggers
        }
    }

    return false; // Return false if no terms match
}


// AJAX handler to add product to WooCommerce cart
public function mxchat_add_to_cart() {
    if (isset($_POST['product_id']) && class_exists('WooCommerce')) {
        $product_id = absint($_POST['product_id']);

        // Try to add the product to the WooCommerce cart
        $added = WC()->cart->add_to_cart($product_id);
        if ($added) {
            // Send the product name in the response for clarity in the chat
            $product = wc_get_product($product_id);
            $product_name = $product ? $product->get_name() : __('Product', 'mxchat');

            wp_send_json_success(['message' => "{$product_name} was added to your cart."]);
        } else {
            wp_send_json_error(['message' => 'Could not add product to cart.']);
        }
    } else {
        wp_send_json_error(['message' => 'Invalid product or WooCommerce not available.']);
    }
    wp_die();
}


public static function mxchat_extract_product_id_from_message($message) {
    // Convert Markdown links to plain URLs
    $message = preg_replace('/\[(.*?)\]\((.*?)\)/', '$2', $message);

    // Match all URLs in the message
    preg_match_all('/https?:\/\/[^\s]+/', $message, $matches);
    if (!empty($matches[0])) {
        foreach ($matches[0] as $url) {
            // Try to get the post ID from the URL
            $post_id = url_to_postid($url);
            if ($post_id) {
                // Check if the post type is 'product'
                $post_type = get_post_type($post_id);
                if ($post_type === 'product') {
                    return $post_id;
                }
            }
        }
    }
    return false;
}



// Function to add the captured email to Loops
private function add_email_to_loops($email) {
    $api_key = $this->options['loops_api_key'];
    $mailing_list_id = $this->options['loops_mailing_list'];

    $data = array(
        'email' => $email,
        'subscribed' => true,
        'source' => 'MxChat AI Chatbot',
        'mailingLists' => array($mailing_list_id => true),
    );

    $url = "https://app.loops.so/api/v1/contacts/create";
    $args = array(
        'body' => json_encode($data),
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ),
        'method' => 'POST',
        'timeout' => 45,
    );

    wp_remote_post($url, $args);
}


private function mxchat_get_user_identifier() {
    return MxChat_User::mxchat_get_user_identifier();
}



    private function mxchat_generate_embedding($text, $api_key) {
        $endpoint = 'https://api.openai.com/v1/embeddings';

        $body = wp_json_encode([
            'input' => $text,
            'model' => 'text-embedding-ada-002'
        ]);

        $args = [
            'body' => $body,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'timeout' => 60,
            'redirection' => 5,
            'blocking' => true,
            'httpversion' => '1.0',
            'sslverify' => true,
        ];

        $response = wp_remote_post($endpoint, $args);

        if (is_wp_error($response)) {
            return null;
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($response_body['data'][0]['embedding']) && is_array($response_body['data'][0]['embedding'])) {
            return $response_body['data'][0]['embedding'];
        } else {
            return null;
        }
    }

private function mxchat_find_relevant_content($user_embedding) {
    global $wpdb;
    $system_prompt_table = $wpdb->prefix . 'mxchat_system_prompt_content';

    // Define a cache key for embeddings
    $cache_key = 'mxchat_system_prompt_embeddings';

    // Attempt to get the embeddings from the cache
    $embeddings = wp_cache_get($cache_key, 'mxchat_system_prompts');

    if ($embeddings === false) {
        // Cache miss, query the database and cache the results
        $query = "SELECT id, embedding_vector FROM {$system_prompt_table}";
        $embeddings = $wpdb->get_results($query);

        if ($embeddings === null || empty($embeddings)) {
            //error_log("No embeddings found in the database.");
            return null; // Return null to handle no embeddings gracefully
        }

        // Cache the results if successful
        wp_cache_set($cache_key, $embeddings, 'mxchat_system_prompts', 3600); // Cache for 1 hour
    }

    $most_relevant_id = null;
    $highest_similarity = -INF;

    foreach ($embeddings as $embedding) {
        $database_embedding = maybe_unserialize($embedding->embedding_vector);

        // Debugging: Log the embeddings
        // if (!is_array($database_embedding)) {
        //     error_log("Invalid database embedding format for ID {$embedding->id}: " . print_r($database_embedding, true));
        //     continue;
        // }

        if (is_array($user_embedding)) {
            $similarity = $this->mxchat_calculate_cosine_similarity($user_embedding, $database_embedding);

            // Debugging: Log the similarity score
            // error_log("Calculated similarity for ID {$embedding->id}: {$similarity}");

            if ($similarity > $highest_similarity) {
                $highest_similarity = $similarity;
                $most_relevant_id = $embedding->id;
            }
        } else {
            // error_log("User embedding is not an array. Embedding data: " . print_r($user_embedding, true));
        }
    }

    if ($most_relevant_id !== null) {
        // Fetch content with product links
        return $this->fetch_content_with_product_links($most_relevant_id);
    }

    //error_log("No relevant content found. Most relevant ID was null.");
    return null; // Return null if no relevant content is found
}


private function fetch_content_with_product_links($most_relevant_id) {
    global $wpdb;
    $system_prompt_table = $wpdb->prefix . 'mxchat_system_prompt_content';

    // Fetch the article content and associated product URL
    $query = $wpdb->prepare("SELECT article_content, source_url FROM {$system_prompt_table} WHERE id = %d", $most_relevant_id);
    $result = $wpdb->get_row($query);

    if ($result) {
        // Append the product link to the content if available
        $content = $result->article_content;
        if (!empty($result->source_url)) {
            $content .= "\n\nFor more details, check out this product: " . esc_url($result->source_url);
        }
        return $content;
    }

    return null;
}

private function mxchat_generate_response($relevant_content, $api_key, $xai_api_key, $claude_api_key, $conversation_history) {
    if (!$relevant_content) {
        return "I'm sorry, I couldn't find relevant information on that topic.";
    }

    // Check the selected model
    $selected_model = isset($this->options['model']) ? $this->options['model'] : 'gpt-3.5-turbo';

    // Call the appropriate function based on the selected model
    if (strpos($selected_model, 'claude') !== false) {
        return $this->mxchat_generate_response_claude($selected_model, $claude_api_key, $conversation_history, $relevant_content);
    } elseif ($selected_model === 'grok-beta') {
        return $this->mxchat_generate_response_xai($selected_model, $xai_api_key, $conversation_history, $relevant_content);
    } else {
        return $this->mxchat_generate_response_openai($selected_model, $api_key, $conversation_history, $relevant_content);
    }
}

private function mxchat_generate_response_openai($selected_model, $api_key, $conversation_history, $relevant_content) {
    // Get system prompt instructions from options
    $system_prompt_instructions = isset($this->options['system_prompt_instructions']) ? $this->options['system_prompt_instructions'] : '';

    // Add system prompt to relevant content
    $content_with_instructions = $system_prompt_instructions . " " . $relevant_content;

    // Prepend system instructions to the conversation history
    array_unshift($conversation_history, [
        'role' => 'system',
        'content' => "Here are your instructions: " . $content_with_instructions
    ]);

    // Ensure consistency: Replace 'bot' role with 'assistant' in conversation history
    foreach ($conversation_history as &$message) {
        if ($message['role'] === 'bot') {
            $message['role'] = 'assistant';
        }
    }

    // Build the request body
    $body = json_encode([
        'model' => $selected_model,
        'messages' => $conversation_history,
        'temperature' => 0.8,
        'stream' => false
    ]);

    // Set up the API request
    $args = [
        'body'        => $body,
        'headers'     => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ],
        'timeout'     => 60,
        'redirection' => 5,
        'blocking'    => true,
        'httpversion' => '1.0',
        'sslverify'   => true,
    ];

    // Make the API request
    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', $args);

    // Process the response
    if (is_wp_error($response)) {
        return "Sorry, there was an error processing your request.";
    }

    $response_body = json_decode(wp_remote_retrieve_body($response), true);

    if (isset($response_body['choices'][0]['message']['content'])) {
        return trim($response_body['choices'][0]['message']['content']);
    } else {
        return "Sorry, I couldn't process that request.";
    }
}

private function mxchat_generate_response_xai($selected_model, $xai_api_key, $conversation_history, $relevant_content) {
    // Get system prompt instructions from options
    $system_prompt_instructions = isset($this->options['system_prompt_instructions']) ? $this->options['system_prompt_instructions'] : '';

    // Add system prompt to relevant content
    $content_with_instructions = $system_prompt_instructions . " " . $relevant_content;

    // Prepend system instructions to the conversation history
    array_unshift($conversation_history, [
        'role' => 'system',
        'content' => "Here are your instructions: " . $content_with_instructions
    ]);

    // Ensure consistency: Replace 'bot' role with 'assistant' in conversation history
    foreach ($conversation_history as &$message) {
        if ($message['role'] === 'bot') {
            $message['role'] = 'assistant';
        }
    }

    // Build the request body
    $body = json_encode([
        'model' => $selected_model,
        'messages' => $conversation_history,
        'temperature' => 0.8,
        'stream' => false
    ]);

    // Set up the API request
    $args = [
        'body'        => $body,
        'headers'     => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $xai_api_key,
        ],
        'timeout'     => 60,
        'redirection' => 5,
        'blocking'    => true,
        'httpversion' => '1.0',
        'sslverify'   => true,
    ];

    // Make the API request
    $response = wp_remote_post('https://api.x.ai/v1/chat/completions', $args);

    // Process the response
    if (is_wp_error($response)) {
        return "Sorry, there was an error processing your request.";
    }

    $response_body = json_decode(wp_remote_retrieve_body($response), true);

    if (isset($response_body['choices'][0]['message']['content'])) {
        return trim($response_body['choices'][0]['message']['content']);
    } else {
        return "Sorry, I couldn't process that request.";
    }
}

private function mxchat_generate_response_claude($selected_model, $claude_api_key, $conversation_history, $relevant_content) {
    // Get system prompt instructions from options for Claude's top-level system parameter
    $system_prompt_instructions = isset($this->options['system_prompt_instructions']) ? $this->options['system_prompt_instructions'] : '';

    // Ensure consistency: Replace 'bot' role with 'assistant' in conversation history
    foreach ($conversation_history as &$message) {
        if ($message['role'] === 'bot') {
            $message['role'] = 'assistant';
        }
    }

    // Add relevant content as the latest user message in conversation history
    $conversation_history[] = [
        'role' => 'user',
        'content' => $relevant_content
    ];

    // Build the request body with Claude's expected structure, using system instructions as a top-level parameter
    $body = json_encode([
        'model' => $selected_model,
        'max_tokens' => 1000,
        'temperature' => 0.8,
        'system' => $system_prompt_instructions, // Set the system prompt at the top level as required
        'messages' => $conversation_history
    ]);

    // Set up the API request with the necessary headers
    $args = [
        'body'        => $body,
        'headers'     => [
            'Content-Type' => 'application/json',
            'x-api-key' => $claude_api_key,
            'anthropic-version' => '2023-06-01',
        ],
        'timeout'     => 60,
        'redirection' => 5,
        'blocking'    => true,
        'httpversion' => '1.0',
        'sslverify'   => true,
    ];

    // Make the API request
    $response = wp_remote_post('https://api.anthropic.com/v1/messages', $args);

    // Check for errors and log the response for debugging
    if (is_wp_error($response)) {
        error_log("Claude API request error: " . print_r($response->get_error_message(), true));
        return "Sorry, there was an error processing your request.";
    }

    // Decode the response and parse according to the expected Claude response structure
    $response_body = json_decode(wp_remote_retrieve_body($response), true);
    error_log("Claude API response: " . print_r($response_body, true));

    // Check if the response has the expected 'content' array with 'text' blocks
    if (isset($response_body['content'][0]['text'])) {
        return trim($response_body['content'][0]['text']);
    } else {
        return "Sorry, I couldn't process that request.";
    }
}


public function mxchat_dismiss_pre_chat_message() {
    // Get and sanitize the user identifier
    $user_id = $this->mxchat_get_user_identifier();
    $user_id = sanitize_key($user_id);

    // Set a transient to track that the user has dismissed the pre-chat message
    $transient_key = 'mxchat_pre_chat_message_dismissed_' . $user_id;
    set_transient($transient_key, true, DAY_IN_SECONDS);

    wp_send_json_success();
}

public function mxchat_check_pre_chat_message_status() {
    // Get and sanitize the user identifier
    $user_id = $this->mxchat_get_user_identifier();
    $user_id = sanitize_key($user_id);

    // Check if the transient exists (i.e., if the message was dismissed)
    $transient_key = 'mxchat_pre_chat_message_dismissed_' . $user_id;
    $dismissed = get_transient($transient_key);

    // Log the result to see if it's being set correctly
    //error_log("Check pre-chat message dismissed for $user_id: " . ($dismissed ? 'Yes' : 'No'));

    if ($dismissed) {
        wp_send_json_success(['dismissed' => true]);
    } else {
        wp_send_json_success(['dismissed' => false]);
    }

    wp_die();
}





    private function mxchat_calculate_cosine_similarity($vectorA, $vectorB) {
        if (!is_array($vectorA) || !is_array($vectorB) || empty($vectorA) || empty($vectorB)) {
            return 0;
        }

        $dotProduct = array_sum(array_map(function ($a, $b) {
            return $a * $b;
        }, $vectorA, $vectorB));
        $normA = sqrt(array_sum(array_map(function ($a) {
            return $a * $a;
        }, $vectorA)));
        $normB = sqrt(array_sum(array_map(function ($b) {
            return $b * $b;
        }, $vectorB)));

        if ($normA == 0 || $normB == 0) {
            return 0;
        }

        return $dotProduct / ($normA * $normB);
    }

    public function mxchat_enqueue_scripts_styles() {
        // Define version numbers for the styles and scripts
        $chat_style_version = '1.2.1'; // Replace with your actual version
        $chat_script_version = '1.2.1'; // Replace with your actual version

        // Correct path to the script file
        wp_enqueue_script(
            'mxchat-chat-js', // Handle for the script
            plugin_dir_url(__FILE__) . '../js/chat-script.js', // Correct path using __FILE__
            array('jquery'), // Dependencies
            $chat_script_version, // Version for cache busting
            true // Load script in footer
        );

        // Enqueue the CSS file similarly
        wp_enqueue_style(
            'mxchat-chat-css', // Handle for the style
            plugin_dir_url(__FILE__) . '../css/chat-style.css', // Correct path using __FILE__
            array(), // No dependencies
            $chat_style_version // Version for cache busting
        );

        // Fetch options from the database
        $this->options = get_option('mxchat_options');

        // Prepare settings to pass to JavaScript
        $style_settings = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mxchat_chat_nonce'), // Nonce for security
            'rate_limit_message' => $this->options['rate_limit_message'] ?? 'Rate limit exceeded. Please try again later.',
            'appendWidgetToBody' => $this->options['append_to_body'] ?? 'off'
        );

        // Localize the script with necessary data
        wp_localize_script('mxchat-chat-js', 'mxchatChat', $style_settings);
    }



    public function mxchat_reset_rate_limits() {
        global $wpdb;

        // Define a cache key pattern for rate limits
        $cache_key_pattern = 'mxchat_chat_limit_%';

        // Retrieve all option names matching the pattern
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
        $option_names = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'mxchat_chat_limit_%'");

        // db call ok; no-cache ok
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- db call ok
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'mxchat_chat_limit_%'");

        // Clear the relevant cache entries
        foreach ($option_names as $option_name) {
            wp_cache_delete($option_name, 'options');
        }

        // Optionally, clear a general cache if you have one
        wp_cache_delete('mxchat_all_chat_limits', 'options');
    }


private function mxchat_fetch_woocommerce_products() {
    // Ensure WooCommerce is active
    if (!class_exists('WooCommerce')) {
        return [];
    }

    $args = array(
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => -1,
    );

    $products = get_posts($args);
    $product_data = [];

    foreach ($products as $product) {
        $product_id = $product->ID;
        $product_obj = wc_get_product($product_id);

        $product_data[] = array(
            'id' => $product_id,
            'name' => $product_obj->get_name(),
            'description' => $product_obj->get_description(),
            'short_description' => $product_obj->get_short_description(),
            'url' => get_permalink($product_id),
            'price' => $product_obj->get_regular_price(),
            'sale_price' => $product_obj->get_sale_price(),
            'stock_status' => $product_obj->get_stock_status(),
            'sku' => $product_obj->get_sku(),
            'in_stock' => $product_obj->is_in_stock(),
            'total_sales' => $product_obj->get_total_sales(),
        );
    }

    return $product_data;
}

}
?>
