<?php

if (!defined('ABSPATH')) {
    exit;
}

class MxChat_WooCommerce {

    public static function init() {
        add_action('wp_ajax_mxchat_fetch_user_orders', array(__CLASS__, 'mxchat_fetch_user_orders'));
        add_action('wp_ajax_nopriv_mxchat_fetch_user_orders', array(__CLASS__, 'mxchat_fetch_user_orders'));
        
        add_action('wp_ajax_mxchat_add_to_cart', array(__CLASS__, 'mxchat_add_to_cart'));
add_action('wp_ajax_nopriv_mxchat_add_to_cart', array(__CLASS__, 'mxchat_add_to_cart'));

    }
    
    // New function to handle add to cart requests
public static function mxchat_add_to_cart() {
    // Validate nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mxchat_nonce')) {
        wp_send_json_error('Invalid nonce.');
        wp_die();
    }

    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    if (!$product_id) {
        wp_send_json_error('Invalid product ID.');
        wp_die();
    }

    // Add to WooCommerce cart
    $added = WC()->cart->add_to_cart($product_id);
    if ($added) {
        $product = wc_get_product($product_id);
        wp_send_json_success(['message' => "The product '{$product->get_name()}' has been added to your cart."]);
    } else {
        wp_send_json_error('Error adding product to cart.');
    }
    wp_die();
}


    // Fetch user orders via AJAX
    public static function mxchat_fetch_user_orders() {
        // Check if WooCommerce order access is enabled in the settings
        if (!self::is_order_access_enabled()) {
            wp_send_json_error(__('Order tracking is disabled. Please contact support for more information.', 'mxchat'));
            wp_die();
        }

        // Validate the AJAX request with a nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mxchat_nonce')) {
            wp_send_json_error(__('Invalid request.', 'mxchat'));
            wp_die();
        }

        // Check if the user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You need to be logged in to view your orders.', 'mxchat'));
            wp_die();
        }

        // Fetch the logged-in user ID
        $user_id = get_current_user_id();

        // Fetch orders associated with the user
        $orders = wc_get_orders(array(
            'customer_id' => $user_id,
            'limit' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ));

        if (empty($orders)) {
            wp_send_json_error(__('No orders found.', 'mxchat'));
            wp_die();
        }

        $order_data = array();
        foreach ($orders as $order) {
            $order_id = $order->get_id();
            $order_date = $order->get_date_created()->date('F j, Y');
            $order_total = wc_price($order->get_total());
            $order_status = wc_get_order_status_name($order->get_status());
            $order_items = array_map(function ($item) {
                return $item->get_name();
            }, $order->get_items());

            $order_data[] = array(
                'order_id' => $order_id,
                'order_date' => $order_date,
                'order_total' => $order_total,
                'order_status' => $order_status,
                'order_items' => $order_items,
            );
        }

        // Send the order data as a success response
        wp_send_json_success($order_data);
        wp_die();
    }

    // Helper function to determine if the query is related to orders
    public static function mxchat_is_order_related_query($message) {
        $keywords = array('my orders', 'order status', 'previous orders', 'order history');
        foreach ($keywords as $keyword) {
            if (stripos($message, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }

    // Function to fetch the user's order details as a string
    public static function mxchat_fetch_user_orders_details() {
        // Ensure the session is active
        if (!session_id()) {
            session_start();
        }

        // Check if WooCommerce order access is enabled
        if (!self::is_order_access_enabled()) {
            return "Order tracking is disabled. Please contact support for more information.";
        }

        // Check if the user is logged in
        if (!is_user_logged_in()) {
            return 'User is not logged in.';
        }

        // Fetch the logged-in user ID
        $user_id = get_current_user_id();

        // Fetch all orders associated with the user, regardless of status
        $orders = wc_get_orders(array(
            'customer_id' => $user_id,
            'limit' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ));

        if (empty($orders)) {
            return 'No orders found for the user.';
        }

        $order_details = "Your recent orders:\n";
        foreach ($orders as $order) {
            $items = implode(', ', array_map(function ($item) {
                return $item->get_name();
            }, $order->get_items()));

            $order_details .= "Order #{$order->get_id()} on {$order->get_date_created()->date('F j, Y')} - Status: {$order->get_status()} - Total: {$order->get_total()} - Items: {$items}\n";
        }

        return $order_details;
    }

    // Function to check if order access is enabled in the admin settings
    public static function is_order_access_enabled() {
        $options = get_option('mxchat_options', array());
        return isset($options['enable_woocommerce_order_access']) && $options['enable_woocommerce_order_access'] === '1';
    }

    // Check if there are items in the cart
    public static function cart_has_items() {
        return WC()->cart && WC()->cart->get_cart_contents_count() > 0;
    }

public static function mxchat_extract_product_id_from_message($message) {
    // Ensure WooCommerce functions are available
    if (!function_exists('wc_get_products')) {
        error_log("WooCommerce is not active or not available.");
        return null; // Exit if WooCommerce is not active
    }

    // Normalize the message
    $message = sanitize_text_field(strtolower($message));

    // Add debug log for the incoming message
    error_log("Product extraction message: " . $message);

    // Split the message into individual words to search more effectively
    $search_terms = explode(' ', $message);

    // Log the search terms
    error_log("Search terms: " . implode(', ', $search_terms));

    // Search for the product in WooCommerce catalog by title, slug, and SKU
    $args = array(
        'status' => 'publish',
        'limit'  => 5, // Retrieve multiple products to better match
        'return' => 'objects',
    );

    $products = wc_get_products($args);

    // Log how many products were fetched
    error_log("Total products found: " . count($products));

    foreach ($products as $product) {
        // Check if the product title, slug, or SKU matches any of the search terms
        $product_name = strtolower($product->get_name());
        $product_slug = strtolower($product->get_slug());
        $product_sku = strtolower($product->get_sku());

        foreach ($search_terms as $term) {
            if (stripos($product_name, $term) !== false || stripos($product_slug, $term) !== false || stripos($product_sku, $term) !== false) {
                // Log which product was matched
                error_log("Matched product: " . $product_name . " (ID: " . $product->get_id() . ")");
                return $product->get_id(); // Return the first matching product ID
            }
        }
    }

    // If no product matches, log the failure
    error_log("No product matched for message: " . $message);

    return null;
}


    // Store the product ID in session when discussed
    public static function store_last_discussed_product($product_id) {
        set_transient('mxchat_last_product', $product_id, 12 * HOUR_IN_SECONDS);
    }

    // Retrieve the last discussed product ID
    public static function get_last_discussed_product() {
        return get_transient('mxchat_last_product');
    }

}

MxChat_WooCommerce::init();
