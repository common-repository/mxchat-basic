<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class MxChat_Public {
    private $options;

    public function __construct() {
        $this->options = get_option('mxchat_options', $this->get_default_options()); // Ensure defaults are used
        add_action('wp_enqueue_scripts', array($this, 'register_public_scripts_styles'));
        add_shortcode('mxchat_chatbot', array($this, 'render_chatbot_shortcode'));
        add_action('wp_footer', array($this, 'append_chatbot_to_body'));
    }

    private function get_default_options() {
        return array(
            'user_message_bg_color' => '#fff',
            'user_message_font_color' => '#212121',
            'bot_message_bg_color' => '#212121',
            'bot_message_font_color' => '#fff',
            'top_bar_bg_color' => '#212121',
            'send_button_font_color' => '#212121',
            'close_button_color' => '#fff',
            'chatbot_bg_color' => '#fff',
            'chatbot_background_color' => '#212121',
            'icon_color' => '#fff',
            'chat_input_font_color' => '#212121',
            'api_key' => '',
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
            'top_bar_title' => 'MxChat: Basic',
            'intro_message' => 'Hello! How can I assist you today?',
            'append_to_body' => 'on'
        );
    }

    public function register_public_scripts_styles() {
        $chat_style_version = '1.2.1'; // Replace with your actual version
        $chat_script_version = '1.2.1'; // Replace with your actual version

        // Correct path to the CSS file
        wp_register_style(
            'mxchat-chat-css',
            plugin_dir_url(__FILE__) . '../css/chat-style.css', // Correct path to css directory
            array(),
            $chat_style_version
        );

        // Correct path to the JS file
        wp_register_script(
            'mxchat-chat-js',
            plugin_dir_url(__FILE__) . '../js/chat-script.js', // Correct path to js directory
            array('jquery'), // Ensure jQuery is listed as a dependency
            $chat_script_version,
            true // Load in the footer
        );
    }

public function enqueue_public_scripts_styles() {
    wp_enqueue_style('mxchat-chat-css');
    wp_enqueue_script('mxchat-chat-js', plugin_dir_url(__FILE__) . 'js/chat-script.js', array('jquery', 'complianz'), '1.0.0', true);

    // Fetch options from the database
    $this->options = get_option('mxchat_options');

    // Localize script with necessary data, including nonce
    wp_localize_script('mxchat-chat-js', 'mxchatChat', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('mxchat_chat_nonce'), // Correct nonce creation
        'link_target' => isset($this->options['link_target_toggle']) ? $this->options['link_target_toggle'] : 'off',
        'rate_limit_message' => isset($this->options['rate_limit_message']) ? $this->options['rate_limit_message'] : 'Rate limit exceeded. Please try again later.',
        'complianz_toggle' => isset($this->options['complianz_toggle']) && $this->options['complianz_toggle'] === 'on' ? true : false,
        'user_message_bg_color' => isset($this->options['user_message_bg_color']) ? $this->options['user_message_bg_color'] : '#fff',
        'user_message_font_color' => isset($this->options['user_message_font_color']) ? $this->options['user_message_font_color'] : '#212121',
        'bot_message_bg_color' => isset($this->options['bot_message_bg_color']) ? $this->options['bot_message_bg_color'] : '#212121',
        'bot_message_font_color' => isset($this->options['bot_message_font_color']) ? $this->options['bot_message_font_color'] : '#fff',
        'top_bar_bg_color' => isset($this->options['top_bar_bg_color']) ? $this->options['top_bar_bg_color'] : '#212121',
        'send_button_font_color' => isset($this->options['send_button_font_color']) ? $this->options['send_button_font_color'] : '#212121',
        'close_button_color' => isset($this->options['close_button_color']) ? $this->options['close_button_color'] : '#fff',
        'chatbot_background_color' => isset($this->options['chatbot_background_color']) ? $this->options['chatbot_background_color'] : '#212121',
        'chatbot_bg_color' => isset($this->options['chatbot_bg_color']) ? $this->options['chatbot_bg_color'] : '#fff',
        'icon_color' => isset($this->options['icon_color']) ? $this->options['icon_color'] : '#fff',
        'chat_input_font_color' => isset($this->options['chat_input_font_color']) ? $this->options['chat_input_font_color'] : '#212121',
        // Add chat_persistence_toggle to the array
        'chat_persistence_toggle' => isset($this->options['chat_persistence_toggle']) ? $this->options['chat_persistence_toggle'] : 'off',
    ));
}


    public function append_chatbot_to_body() {
        $options = get_option('mxchat_options', $this->get_default_options()); // Use default options if not set
        if (isset($options['append_to_body']) && $options['append_to_body'] === 'on') {
            echo do_shortcode('[mxchat_chatbot floating="yes"]');
        }
    }

    private function mxchat_get_user_identifier() {
        return sanitize_text_field($_SERVER['REMOTE_ADDR']);
    }

    public function render_chatbot_shortcode($atts) {
        $this->enqueue_public_scripts_styles(); // Enqueue the styles and scripts here

        $attributes = shortcode_atts(array(
            'floating' => 'yes',
        ), $atts);

        $is_floating = $attributes['floating'] === 'yes';

        $this->options = get_option('mxchat_options', $this->get_default_options()); // Use default options if not set

        $bg_color = $this->options['chatbot_background_color'] ?? '#fff';
        $user_message_bg_color = $this->options['user_message_bg_color'] ?? '#fff';
        $user_message_font_color = $this->options['user_message_font_color'] ?? '#212121';
        $bot_message_bg_color = $this->options['bot_message_bg_color'] ?? '#212121';
        $bot_message_font_color = $this->options['bot_message_font_color'] ?? '#fff';
        $top_bar_bg_color = $this->options['top_bar_bg_color'] ?? '#212121';
        $send_button_font_color = $this->options['send_button_font_color'] ?? '#212121';
        $intro_message = $this->options['intro_message'] ?? 'Hello! How can I assist you today?';
        $top_bar_title = $this->options['top_bar_title'] ?? 'MxChat: Basic';
        $chatbot_background_color = $this->options['chatbot_background_color'] ?? '#212121';
        $icon_color = $this->options['icon_color'] ?? '#fff';
        $chat_input_font_color = $this->options['chat_input_font_color'] ?? '#212121';
        $close_button_color = $this->options['close_button_color'] ?? '#fff';
        $chatbot_bg_color = $this->options['chatbot_bg_color'] ?? '#fff';
        $pre_chat_message = isset($this->options['pre_chat_message']) ? sanitize_text_field(trim($this->options['pre_chat_message'])) : '';
        $user_id = sanitize_key($this->mxchat_get_user_identifier());
        $transient_key = 'mxchat_pre_chat_message_dismissed_' . $user_id;
        $input_copy = isset($this->options['input_copy']) ? esc_attr($this->options['input_copy']) : 'How can I assist?';
        $rate_limit_message = isset($this->options['rate_limit_message']) ? esc_attr($this->options['rate_limit_message']) : 'Rate limit exceeded. Please try again later.';

        $privacy_toggle = isset($this->options['privacy_toggle']) && $this->options['privacy_toggle'] === 'on';
        $privacy_text = isset($this->options['privacy_text']) ? wp_kses_post($this->options['privacy_text']) : 'By chatting, you agree to our <a href="https://example.com/privacy-policy" target="_blank">privacy policy</a>.';

        $popular_question_1 = isset($this->options['popular_question_1']) ? esc_html($this->options['popular_question_1']) : '';
        $popular_question_2 = isset($this->options['popular_question_2']) ? esc_html($this->options['popular_question_2']) : '';
        $popular_question_3 = isset($this->options['popular_question_3']) ? esc_html($this->options['popular_question_3']) : '';


        ob_start();

        // Check if floating attribute is set to 'yes' and wrap accordingly
        if ($is_floating) {
            echo '<div id="floating-chatbot" class="hidden">';
        }

        echo '<div id="mxchat-chatbot-wrapper">';
        echo '  <div class="chatbot-top-bar" id="exit-chat-button" style="background: ' . esc_attr($top_bar_bg_color) . ';">';
        echo '      <p class="chatbot-title" style="color: ' . esc_attr($close_button_color) . ';">' . esc_html($top_bar_title) . '</p>';
        echo '      <button class="exit-chat" type="button" aria-label="Minimize" style="color: ' . esc_attr($close_button_color) . ';">';
        echo '          <svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 0 24 24" width="24" id="ic-minimize" style="fill: ' . esc_attr($close_button_color) . ';">';
        echo '              <path d="M0 0h24v24H0z" fill="none"></path>';
        echo '              <path d="M11.67 3.87L9.9 2.1 0 12l9.9 9.9 1.77-1.77L3.54 12z"></path>';
        echo '          </svg>';
        echo '          <span>Minimize</span>';
        echo '      </button>';
        echo '  </div>';
        echo '  <div id="mxchat-chatbot" style="background-color: ' . esc_attr($chatbot_bg_color) . '">';
        echo '      <div id="chat-container">';
        echo '          <div id="chat-box">';
        echo '              <div class="bot-message" style="background: ' . esc_attr($bot_message_bg_color) . '; color: ' . esc_attr($bot_message_font_color) . ';">';
        echo                    esc_html($intro_message);
        echo '              </div>';
        echo '          </div>';

                // Add the popular questions section
                echo '<div id="mxchat-popular-questions">';
                echo '  <div class="mxchat-popular-questions-container">';

                if (!empty($popular_question_1)) {
                    echo '<button class="mxchat-popular-question">' . esc_html($popular_question_1) . '</button>';
                }
                if (!empty($popular_question_2)) {
                    echo '<button class="mxchat-popular-question">' . esc_html($popular_question_2) . '</button>';
                }
                if (!empty($popular_question_3)) {
                    echo '<button class="mxchat-popular-question">' . esc_html($popular_question_3) . '</button>';
                }

        echo '  </div>';
        echo '</div>';

        echo '          <div id="input-container">';
        echo '              <input type="text" id="chat-input" placeholder="' . esc_attr($input_copy) . '" style="color: ' . esc_attr($chat_input_font_color) . ';">';
        echo '              <button id="send-button">';
        echo '                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" style="fill: ' . esc_attr($send_button_font_color) . ';">';
        echo '                      <path d="M498.1 5.6c10.1 7 15.4 19.1 13.5 31.2l-64 416c-1.5 9.7-7.4 18.2-16 23s-18.9 5.4-28 1.6L284 427.7l-68.5 74.1c-8.9 9.7-22.9 12.9-35.2 8.1S160 493.2 160 480V396.4c0-4 1.5-7.8 4.2-10.7L331.8 202.8c5.8-6.3 5.6-16-.4-22s-15.7-6.4-22-.7L106 360.8 17.7 316.6C7.1 311.3 .3 300.7 0 288.9s5.9-22.8 16.1-28.7l448-256c10.7-6.1 23.9-5.5 34 1.4z"/>';
        echo '                  </svg>';
        echo '              </button>';
        echo '          </div>';
        echo '          <div class="chatbot-footer">';

                // Output the privacy notice if enabled
                if ($privacy_toggle && !empty($privacy_text)) {
                    echo '<p class="privacy-notice">' . $privacy_text . '</p>';
                }

        echo '          </div>';
        echo '      </div>';
        echo '  </div>';
        echo '</div>';

        if ($is_floating) {
            echo '</div>';

        if (!empty($pre_chat_message) && !get_transient($transient_key)) {
            echo '<div id="pre-chat-message">';
            echo esc_html($pre_chat_message);
            echo '<button class="close-pre-chat-message" aria-label="Close">&times;</button>';
            echo '</div>';
        }

            echo '<div class="hidden" id="floating-chatbot-button" style="background: ' . esc_attr($chatbot_background_color) . '; color: ' . esc_attr($send_button_font_color) . ';">';
            echo '  <svg id="widget_icon_10" style="height: 48px; width: 48px; fill: ' . esc_attr($icon_color) . '" viewBox="0 0 1120 1120" fill="none" xmlns="http://www.w3.org/2000/svg">';
            echo '      <path fill-rule="evenodd" clip-rule="evenodd" d="M252 434C252 372.144 302.144 322 364 322H770C831.856 322 882 372.144 882 434V614.459L804.595 585.816C802.551 585.06 800.94 583.449 800.184 581.405L763.003 480.924C760.597 474.424 751.403 474.424 748.997 480.924L711.816 581.405C711.06 583.449 709.449 585.06 707.405 585.816L606.924 622.997C600.424 625.403 600.424 634.597 606.924 637.003L707.405 674.184C709.449 674.94 711.06 676.551 711.816 678.595L740.459 756H629.927C629.648 756.476 629.337 756.945 628.993 757.404L578.197 825.082C572.597 832.543 561.403 832.543 555.803 825.082L505.007 757.404C504.663 756.945 504.352 756.476 504.073 756H364C302.144 756 252 705.856 252 644V434ZM633.501 471.462C632.299 468.212 627.701 468.212 626.499 471.462L619.252 491.046C618.874 492.068 618.068 492.874 617.046 493.252L597.462 500.499C594.212 501.701 594.212 506.299 597.462 507.501L617.046 514.748C618.068 515.126 618.874 515.932 619.252 516.954L626.499 536.538C627.701 539.788 632.299 539.788 633.501 536.538L640.748 516.954C641.126 515.932 641.932 515.126 642.954 514.748L662.538 507.501C665.788 506.299 665.788 501.701 662.538 500.499L642.954 493.252C641.932 492.874 641.126 492.068 640.748 491.046L633.501 471.462Z" ></path>';
            echo '      <path d="M771.545 755.99C832.175 755.17 881.17 706.175 881.99 645.545L804.595 674.184C802.551 674.94 800.94 676.551 800.184 678.595L771.545 755.99Z" ></path>';
            echo '  </svg>';
            echo '</div>';
        }

        return ob_get_clean();
    }


}
?>
