jQuery(document).ready(function($) {
    $('#mxchat-activation-form').on('submit', function(event) {
        event.preventDefault();

        var formData = {
            action: 'mxchat_activate_license',
            email: $('#mxchat_pro_email').val(),
            key: $('#mxchat_activation_key').val(),
            security: mxchatAdmin.nonce
        };

        $.post(mxchatAdmin.ajax_url, formData, function(response) {
            if (response.success) {
                $('#mxchat-license-status').text('Active');
            } else {
                $('#mxchat-license-status').text('Inactive');
                alert(response.data);
            }
        });
    });

    $('.mxchat-nav-tab').on('click', function(e) {
        e.preventDefault();
        $('.mxchat-nav-tab').removeClass('mxchat-nav-tab-active');
        $(this).addClass('mxchat-nav-tab-active');
        $('.mxchat-tab-content').removeClass('active').hide();
        var activeTab = $(this).attr('href');
        $(activeTab).addClass('active').show();
    });

    // Activate the first tab by default
    $('.mxchat-nav-tab-active').trigger('click');

    // Toggle visibility of API key
    $('#toggleApiKeyVisibility').on('click', function() {
        var apiKeyInput = $('#api_key');
        if (apiKeyInput.attr('type') === 'password') {
            apiKeyInput.attr('type', 'text');
            $(this).text('Hide');
        } else {
            apiKeyInput.attr('type', 'password');
            $(this).text('Show');
        }
    });
    
    // Toggle visibility of WooCommerce Consumer Secret
    $('#toggleWooCommerceSecretVisibility').on('click', function() {
        var secretInput = $('#woocommerce_consumer_secret');
        if (secretInput.attr('type') === 'password') {
            secretInput.attr('type', 'text');
            $(this).text('Hide');
        } else {
            secretInput.attr('type', 'password');
            $(this).text('Show');
        }
    });
    
    // Toggle visibility of Loops API Key
    $('#toggleLoopsApiKeyVisibility').on('click', function() {
        var loopsApiKeyInput = $('#loops_api_key');
        if (loopsApiKeyInput.attr('type') === 'password') {
            loopsApiKeyInput.attr('type', 'text');
            $(this).text('Hide');
        } else {
            loopsApiKeyInput.attr('type', 'password');
            $(this).text('Show');
        }
    });
    
    // Toggle visibility of X.AI API Key
    $('#toggleXaiApiKeyVisibility').on('click', function() {
        var xaiApiKeyInput = $('#xai_api_key');
        if (xaiApiKeyInput.attr('type') === 'password') {
            xaiApiKeyInput.attr('type', 'text');
            $(this).text('Hide');
        } else {
            xaiApiKeyInput.attr('type', 'password');
            $(this).text('Show');
        }
    });
    
        // Toggle visibility of Claude API Key
    $('#toggleClaudeApiKeyVisibility').on('click', function() {
        var claudeApiKeyInput = $('#claude_api_key');
        if (claudeApiKeyInput.attr('type') === 'password') {
            claudeApiKeyInput.attr('type', 'text');
            $(this).text('Hide');
        } else {
            claudeApiKeyInput.attr('type', 'password');
            $(this).text('Show');
        }
    });
    
    
      // Click the Edit button
    $('.edit-button').on('click', function() {
        var row = $(this).closest('tr');
        row.find('.content-view, .url-view').hide();
        row.find('.content-edit, .url-edit').show();
        row.find('.edit-button').hide();
        row.find('.save-button').show();
    });

    // Click the Save button
    $('.save-button').on('click', function() {
        var button = $(this);
        var row = button.closest('tr');
        var id = button.data('id');
        var newContent = row.find('.content-edit').val();
        var newUrl = row.find('.url-edit').val();

        // Send an AJAX request to save the changes
        $.ajax({
            url: ajaxurl, // ajaxurl is automatically available in WP admin
            type: 'POST',
            data: {
                action: 'mxchat_save_inline_prompt',
                id: id,
                article_content: newContent,
                article_url: newUrl,
                _ajax_nonce: mxchatInlineEdit.nonce // Use localized nonce
            },
            success: function(response) {
                if (response.success) {
                    row.find('.content-view').html(newContent.replace(/\n/g, "<br>"));
                    row.find('.url-view a').attr('href', newUrl).text(newUrl);
                    row.find('.content-edit, .url-edit').hide();
                    row.find('.content-view, .url-view').show();
                    row.find('.save-button').hide();
                    row.find('.edit-button').show();
                } else {
                    alert('Error saving content.');
                }
            },
            error: function() {
                alert('An error occurred.');
            }
        });
    });
    

});




