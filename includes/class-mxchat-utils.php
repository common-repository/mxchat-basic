<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class MxChat_Utils {

    /**
     * Submit content and its embedding to the database.
     *
     * @param string $content The content to be embedded.
     * @param string $source_url The source URL of the content.
     * @param string $api_key The API key used for generating embeddings.
     */
    public static function submit_content_to_db($content, $source_url, $api_key) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mxchat_system_prompt_content';

        // Sanitize the source URL
        $source_url = esc_url_raw($source_url);

        // Generate the embedding using the API key
        $embedding_vector = self::generate_embedding($content, $api_key);

        if (is_array($embedding_vector)) {
            $embedding_vector_serialized = serialize($embedding_vector);

            // Insert or update the record in the database
            $wpdb->replace(
                $table_name,
                array(
                    'article_content' => $content,
                    'embedding_vector' => $embedding_vector_serialized,
                    'source_url' => $source_url,
                ),
                array(
                    '%s',
                    '%s',
                    '%s',
                )
            );
        } else {
            //error_log('Embedding generation failed for content from ' . $source_url);
        }
    }

    /**
     * Generate an embedding for the given text.
     *
     * @param string $text The text to be embedded.
     * @param string $api_key The API key used for generating embeddings.
     * @return array|null The embedding vector or null on failure.
     */
    private static function generate_embedding($text, $api_key) {
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
            //error_log('Error generating embedding: ' . $response->get_error_message());
            return null;
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($response_body['data'][0]['embedding']) && is_array($response_body['data'][0]['embedding'])) {
            return $response_body['data'][0]['embedding'];
        } else {
            //error_log('Invalid response received from embedding API.');
            return null;
        }
    }
}

?>
