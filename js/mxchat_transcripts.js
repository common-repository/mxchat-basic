jQuery(document).ready(function($) {
    $('#mxchat-select-all-transcripts').click(function() {
        var checkedStatus = this.checked;
        $('#mxchat-transcripts').find('input[type=checkbox]').each(function() {
            $(this).prop('checked', checkedStatus);
        });
    });

    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'mxchat_fetch_chat_history'
        },
        success: function(response) {
            $('#mxchat-transcripts').html(response);
        }
    });

    $('#mxchat-delete-form').submit(function(e) {
        e.preventDefault();
        var checkedSessionIds = $('input[name="delete_session_ids[]"]:checked').map(function() {
            return $(this).val();
        }).get();
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mxchat_delete_chat_history',
                delete_session_ids: checkedSessionIds,
                security: $('#mxchat_delete_chat_nonce').val()
            },
            success: function(response) {
                var jsonResponse = JSON.parse(response);
                if (jsonResponse.success) {
                    alert("Success: " + jsonResponse.success);
                } else if (jsonResponse.error) {
                    alert("Error: " + jsonResponse.error);
                } else {
                    //console.log("Unexpected response format.");
                }
                location.reload();
            },
            error: function(xhr, status, error) {
                //console.error("AJAX Error: " + status + " - " + error);
                //console.log(xhr.responseText);
            }
        });
    });
});
