jQuery(document).ready(function($) {
   //console.log('mxchatChat object:', mxchatChat);

    // Initialize color settings
    var userMessageBgColor = mxchatChat.user_message_bg_color;
    var userMessageFontColor = mxchatChat.user_message_font_color;
    var botMessageBgColor = mxchatChat.bot_message_bg_color;
    var botMessageFontColor = mxchatChat.bot_message_font_color;
    var linkTarget = mxchatChat.link_target === 'on' ? '_blank' : '_self';



function getChatSession() {
    var sessionId = getCookie('mxchat_session_id');
    //console.log("Session ID retrieved from cookie: ", sessionId);

    if (!sessionId) {
        sessionId = generateSessionId();
        //console.log("Generated new session ID: ", sessionId);
        setChatSession(sessionId);
    }

    //console.log("Final session ID: ", sessionId);
    return sessionId;
}

function setChatSession(sessionId) {
    // Set the cookie with a 24-hour expiration (86400 seconds)
    document.cookie = "mxchat_session_id=" + sessionId + "; path=/; max-age=86400; SameSite=Lax";
}

// Get cookie value by name
function getCookie(name) {
    let value = "; " + document.cookie;
    let parts = value.split("; " + name + "=");
    if (parts.length == 2) return parts.pop().split(";").shift();
}

// Generate a new session ID
function generateSessionId() {
    return 'mxchat_chat_' + Math.random().toString(36).substr(2, 9);
}

// Function to send the message to the chatbot (backend)
function sendMessageToChatbot(message) {
    var sessionId = getChatSession(); // Reuse the session ID logic

    // Hide the popular questions section
    $('#mxchat-popular-questions').hide();

    // Show thinking indicator (no need to append the user's message again)
    appendThinkingMessage();
    scrollToBottom();

    //console.log("Sending message to chatbot:", message); // Log the message
    //console.log("Session ID:", sessionId); // Log the session ID

    // Call the chatbot using the same call logic as sendMessage
    callMxChat(message, function(response) {
        // ** Ensure temporary thinking message is removed before adding new response **
        $('.temporary-message').remove();

        // Replace thinking indicator with actual response
        replaceLastMessage("bot", response);
    });
}





function sendMessage() {
    var message = $('#chat-input').val();
    if (message) {
        appendMessage("user", message);
        $('#chat-input').val('');

        // Hide the popular questions section
        $('#mxchat-popular-questions').hide();

        // Show typing indicator
        appendThinkingMessage();
        scrollToBottom();

        callMxChat(message, function(response) {
            // Replace typing indicator with actual response
            replaceLastMessage("bot", response);
        });
    }
}


    // Function to append a thinking message with animation
    function appendThinkingMessage() {
        // Remove any existing thinking dots first
        $('.thinking-dots').remove();

        // Retrieve the bot message font color and background color
        var botMessageFontColor = mxchatChat.bot_message_font_color;
        var botMessageBgColor = mxchatChat.bot_message_bg_color;

        var thinkingHtml = '<div class="thinking-dots-container">' +
                           '<div class="thinking-dots">' +
                           '<span class="dot" style="background-color: ' + botMessageFontColor + ';"></span>' +
                           '<span class="dot" style="background-color: ' + botMessageFontColor + ';"></span>' +
                           '<span class="dot" style="background-color: ' + botMessageFontColor + ';"></span>' +
                           '</div>' +
                           '</div>';

        // Append the thinking dots to the chat container (or within the temporary message div)
        $("#chat-box").append('<div class="bot-message temporary-message" style="background-color: ' + botMessageBgColor + ';">' + thinkingHtml + '</div>');
        scrollToBottom();
    }

    // Trigger send button click when "Enter" key is pressed in the input field
    $('#chat-input').keypress(function(e) {
        if (e.which == 13) {
            e.preventDefault();
            $('#send-button').click();
        }
    });

    // Handle send button click
    $('#send-button').click(function() {
        sendMessage();
    });

    // Handle click on popular questions
    $('.mxchat-popular-question').on('click', function () {
        var question = $(this).text(); // Get the text of the clicked question

        // Append the question as if the user typed it
        appendMessage("user", question);

        // Send the question to the server (backend)
        sendMessageToChatbot(question);
    });


 // Use the linkTarget in your linkify function
function linkify(inputText) {
    // Check for already linked URLs and skip them
    // We use negative lookaheads to skip anything already in an <a> tag
    var markdownLinkPattern = /\[([^\]]+)\]\((https?:\/\/[^\s]+)\)/g;
    var replacedText = inputText.replace(markdownLinkPattern, '<a href="$2" target="' + linkTarget + '">$1</a>');

    // Replace standalone URLs not already in an <a> tag
    var urlPattern = /(^|[^">])(https?:\/\/[^\s<]+)/gim;
    replacedText = replacedText.replace(urlPattern, '$1<a href="$2" target="' + linkTarget + '">$2</a>');

    // Replace "www." prefixed URLs not already in an <a> tag
    var wwwPattern = /(^|[^">])(www\.[\S]+(\b|$))(?![^<]*<\/a>)/gim;
    replacedText = replacedText.replace(wwwPattern, '$1<a href="http://$2" target="' + linkTarget + '">$2</a>');

    return replacedText;
}




function appendMessage(sender, messageText = '', messageHtml = '', isTemporary = false) {
    var messageClass = sender === "user" ? "user-message" : "bot-message";
    var bgColor = sender === "user" ? userMessageBgColor : botMessageBgColor;
    var fontColor = sender === "user" ? userMessageFontColor : botMessageFontColor;

    //console.log(`Appending message from ${sender}:`, { messageText, messageHtml });

    var messageDiv = $('<div>').addClass(messageClass).css({
        'background': bgColor,
        'color': fontColor
    });

    // Detect if messageText is an array or a single string
    var fullMessage = Array.isArray(messageText) ?
                      messageText.map(item => linkify(formatBoldText(convertNewlinesToBreaks(item)))).join("<br>") :
                      linkify(formatBoldText(convertNewlinesToBreaks(messageText)));

    //console.log("Formatted message text:", fullMessage);

    // Append additional HTML if provided
    if (messageHtml) {
        fullMessage += '<br><br>' + messageHtml;
    }

    messageDiv.html(fullMessage);

    if (isTemporary) {
        messageDiv.addClass('temporary-message');
    }

    messageDiv.hide().appendTo('#chat-box').fadeIn(300);
    scrollToBottom();
}



function replaceLastMessage(sender, responseText, responseHtml = '') {
    var messageClass = sender === "user" ? "user-message" : "bot-message";
    var lastMessageDiv = $('#chat-box').find('.' + messageClass + '.temporary-message').last();

    //console.log(`Replacing last message from ${sender} with:`, { responseText, responseHtml });

    // Detect if responseText is an array or a single string
    var fullMessage = Array.isArray(responseText) ?
                      responseText.map(item => linkify(formatBoldText(convertNewlinesToBreaks(item)))).join("<br>") :
                      linkify(formatBoldText(convertNewlinesToBreaks(responseText)));

    //console.log("Formatted replacement message:", fullMessage);

    // Append additional HTML if provided
    if (responseHtml) {
        fullMessage += '<br><br>' + responseHtml;
    }

    if (lastMessageDiv.length) {
        lastMessageDiv.fadeOut(200, function() {
            $(this).html(fullMessage).removeClass('temporary-message').fadeIn(200);
        });
    } else {
        appendMessage(sender, responseText, responseHtml);
    }

    scrollToBottom();
}



// Optimized scrollToBottom function for instant scrolling
function scrollToBottom(instant = false) {
    var chatBox = $('#chat-box');
    if (instant) {
        // Instantly set the scroll position to the bottom
        chatBox.scrollTop(chatBox.prop("scrollHeight"));
    } else {
        // Use requestAnimationFrame for smoother scrolling if needed
        let start = null;
        const scrollHeight = chatBox.prop("scrollHeight");
        const initialScroll = chatBox.scrollTop();
        const distance = scrollHeight - initialScroll;
        const duration = 500; // Duration in ms

        function smoothScroll(timestamp) {
            if (!start) start = timestamp;
            const progress = timestamp - start;
            const currentScroll = initialScroll + (distance * (progress / duration));
            chatBox.scrollTop(currentScroll);

            if (progress < duration) {
                requestAnimationFrame(smoothScroll);
            } else {
                chatBox.scrollTop(scrollHeight); // Ensure it's exactly at the bottom
            }
        }

        requestAnimationFrame(smoothScroll);
    }
}


    // Function to format text with **bold** inside double asterisks
    function formatBoldText(text) {
        return text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    }

    // Function to convert newline characters to HTML line breaks and handle paragraph spacing
    function convertNewlinesToBreaks(text) {
        var lines = text.split('\n');
        var formattedText = '';

        for (var i = 0; i < lines.length; i++) {
            formattedText += lines[i] + '<br>';
        }

        return formattedText;
    }

    // Copy to clipboard function
    // Function to copy text to clipboard
    function copyToClipboard(text) {
        var tempInput = $('<input>');
        $('body').append(tempInput);
        tempInput.val(text).select();
        document.execCommand('copy');
        tempInput.remove();
    }


// Initialize session ID
var sessionId = getChatSession();


function callMxChat(message, callback) {
    var sessionId = getChatSession();
    //console.log("Initiating call to mxchat API with message:", message);

    $.ajax({
        url: mxchatChat.ajax_url,
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'mxchat_handle_chat_request',
            message: message,
            session_id: sessionId,
            nonce: mxchatChat.nonce
        },
        success: function(response) {
            //console.log("Received response from mxchat API:", response);

            // Check for Claude's `completion` field and log its structure
            if (response.completion && Array.isArray(response.completion.content)) {
                //console.log("Claude response detected with content array:", response.completion.content);
                const joinedText = response.completion.content.map(item => item.text).join("\n");
                callback(joinedText);
            } else if (response.text) {
                // If `completion` is missing, log all keys to inspect the structure
                console.warn("Claude-specific field 'completion' not detected. Response structure:", Object.keys(response));
                replaceLastMessage("bot", response.text, response.html);
            } else if (response.message) {
                //console.log("Standard response.message received:", response.message);
                replaceLastMessage("bot", response.message);
            } else if (response.data && response.data.message) {
                //console.log("Rate limit or other specific message received:", response.data.message);
                appendMessage("bot", response.data.message);
            } else {
                //console.warn("Unknown response format received, defaulting to error message.");
                appendMessage("bot", "Sorry, I couldn't process the response.");
            }
        },
        error: function(xhr, status, error) {
            console.error("Error communicating with the server:", error);
            removeThinkingDots();
            appendMessage("bot", "Error communicating with the server.");
        }
    });
}



function loadChatHistory() {
    var sessionId = getChatSession();
    var chatPersistenceEnabled = mxchatChat.chat_persistence_toggle === 'on';

    if (chatPersistenceEnabled && sessionId) {
        $.ajax({
            url: mxchatChat.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'mxchat_fetch_conversation_history',
                session_id: sessionId
            },
            success: function(response) {
                if (response.success && response.data && Array.isArray(response.data.conversation)) {
                    var $chatBox = $('#chat-box');
                    var $fragment = $(document.createDocumentFragment());

                    $.each(response.data.conversation, function(index, message) {
                        var messageElement = $('<div>').addClass(message.role === 'user' ? 'user-message' : 'bot-message')
                            .css({
                                'background': message.role === 'user' ? userMessageBgColor : botMessageBgColor,
                                'color': message.role === 'user' ? userMessageFontColor : botMessageFontColor
                            });

                        // Process content only if it's plain text; otherwise, leave HTML intact
                        if (!message.content.includes("product-card")) {
                            // Run message content through linkify to format links and Markdown
                            var formattedContent = linkify(
                                formatBoldText(
                                    convertNewlinesToBreaks(message.content)
                                )
                            );
                            messageElement.html(formattedContent);
                        } else {
                            // Product card HTML, append directly without modification
                            messageElement.html(message.content);
                        }

                        $fragment.append(messageElement);
                    });

                    $chatBox.append($fragment);
                    scrollToBottom(true);

                    if (response.data.conversation.length > 0) {
                        $('#mxchat-popular-questions').hide();
                    }
                } else {
                    console.warn("No conversation history found.");
                }
            },
            error: function(xhr, status, error) {
                console.error("Error loading chat history:", status, error);
                appendMessage("bot", "Unable to load chat history.");
            }
        });
    } else {
        console.warn("Chat persistence is disabled or no session ID found. Not loading history.");
    }
}



$(document).ready(function() {
    loadChatHistory();
});



    // Helper function to check if a string is an image HTML
    function isImageHtml(str) {
        return str.startsWith('<img') && str.endsWith('>');
    }

    // Function to remove thinking dots
    function removeThinkingDots() {
        $('.thinking-dots').closest('.temporary-message').remove();
    }

    function isMobile() {
        // This can be a simple check, or more sophisticated detection of mobile devices
        return window.innerWidth <= 768; // Example threshold for mobile devices
    }

    function disableScroll() {
        if (isMobile()) {
            $('body').css('overflow', 'hidden');
        }
    }

    function enableScroll() {
        if (isMobile()) {
            $('body').css('overflow', '');
        }
    }

 // Function to show the chatbot widget (moved outside the Complianz logic)
    function showChatWidget() {
        setTimeout(function() {
            $('#floating-chatbot-button').css('display', 'flex').fadeTo(500, 1);
        }, 250);
    }

    // Function to hide the chatbot widget
    function hideChatWidget() {
        $('#floating-chatbot-button').css('display', 'none');
    }

// Pre-chat dismissal check function (wrapped in a function for reuse)
    function checkPreChatDismissal() {
        $.ajax({
            url: mxchatChat.ajax_url,
            type: 'POST',
            data: {
                action: 'mxchat_check_pre_chat_message_status',
                _ajax_nonce: mxchatChat.nonce
            },
            success: function(response) {
                if (response.success && !response.data.dismissed) {
                    $('#pre-chat-message').fadeIn(250);
                } else {
                    $('#pre-chat-message').hide();
                }
            },
            error: function() {
                console.error('Failed to check pre-chat message dismissal status.');
            }
        });
    }

    // Function to dismiss pre-chat message for 24 hours
    function handlePreChatDismissal() {
        $('#pre-chat-message').fadeOut(200);
        $.ajax({
            url: mxchatChat.ajax_url,
            type: 'POST',
            data: {
                action: 'mxchat_dismiss_pre_chat_message',
                _ajax_nonce: mxchatChat.nonce
            },
            success: function() {
                $('#pre-chat-message').hide();
            },
            error: function() {
                console.error('Failed to dismiss pre-chat message.');
            }
        });
    }

    // Handle pre-chat message dismissal on button click
    $(document).on('click', '.close-pre-chat-message', function(e) {
        e.stopPropagation();
        handlePreChatDismissal();
    });

    // Function for Complianz logic
    var applyComplianzLogic = mxchatChat.complianz_toggle;
    if (applyComplianzLogic) {
        function checkConsentAndShowChat() {
            var consentStatus = typeof cmplz_has_consent === "function" && cmplz_has_consent('marketing');
            var consentType = typeof complianz !== 'undefined' ? complianz.consenttype : null;

            if (consentType === 'optin' && !consentStatus) {
                hideChatWidget();
            } else if (consentType === 'optout' && !consentStatus) {
                hideChatWidget();
            } else {
                showChatWidget();
                checkPreChatDismissal(); // Ensure we check dismissal after consent is handled
            }
        }

        checkConsentAndShowChat();
        $(document).on('cmplz_status_change', function(event, category) {
            checkConsentAndShowChat();
        });
    } else {
        showChatWidget();
        checkPreChatDismissal(); // Always check pre-chat dismissal when consent logic is not applied
    }

    // Toggle chatbot visibility on floating button click
    $(document).on('click', '#floating-chatbot-button', function() {
        var chatbot = $('#floating-chatbot');
        if (chatbot.hasClass('hidden')) {
            chatbot.removeClass('hidden').addClass('visible');
            $(this).addClass('hidden');
            disableScroll();
            // Hide the pre-chat message without dismissing it
            $('#pre-chat-message').fadeOut(250);
        } else {
            chatbot.removeClass('visible').addClass('hidden');
            $(this).removeClass('hidden');
            enableScroll();
            // Show the pre-chat message again if it hasn't been dismissed
            checkPreChatDismissal();
        }
    });

    $(document).on('click', '#exit-chat-button', function() {
        $('#floating-chatbot').addClass('hidden').removeClass('visible');
        $('#floating-chatbot-button').removeClass('hidden');
        enableScroll();
    });

    // Close pre-chat message on click
    $(document).on('click', '.close-pre-chat-message', function(e) {
        e.stopPropagation(); // Prevent triggering the parent .pre-chat-message click
        $('#pre-chat-message').fadeOut(200, function() {
            $(this).remove();
        });
    });

    // Open chatbot when pre-chat message is clicked
    $(document).on('click', '#pre-chat-message', function() {
        var chatbot = $('#floating-chatbot');
        if (chatbot.hasClass('hidden')) {
            chatbot.removeClass('hidden').addClass('visible');
            $('#floating-chatbot-button').addClass('hidden');
            $('#pre-chat-message').fadeOut(250); // Hide pre-chat message
            disableScroll(); // Disable scroll when chatbot opens
        }
    });

    // If the chatbot is initially hidden, ensure the button is visible
    if ($('#floating-chatbot').hasClass('hidden')) {
        $('#floating-chatbot-button').removeClass('hidden');
    }

    function setFullHeight() {
        var vh = $(window).innerHeight() * 0.01;
        $(':root').css('--vh', vh + 'px');
    }

    // Set the height when the page loads
    $(document).ready(function() {
        setFullHeight();
    });

    // Set the height on resize and orientation change events
    $(window).on('resize orientationchange', function() {
        setFullHeight();
    });


    // Now handle the close button to dismiss the pre-chat message for 24 hours
    var closeButton = document.querySelector('.close-pre-chat-message');
    if (closeButton) {
        closeButton.addEventListener('click', function() {
            $('#pre-chat-message').fadeOut(200); // Hide the message

            // Send an AJAX request to set the transient flag for 24 hours
            $.ajax({
                url: mxchatChat.ajax_url,
                type: 'POST',
                data: {
                    action: 'mxchat_dismiss_pre_chat_message',
                    _ajax_nonce: mxchatChat.nonce
                },
                success: function() {
                    //console.log('Pre-chat message dismissed for 24 hours.');

                    // Ensure the message is hidden after dismissal
                    $('#pre-chat-message').hide();
                },
                error: function() {
                    //console.error('Failed to dismiss pre-chat message.');
                }
            });
        });
    }



    
// Event listener for Add to Cart button
$(document).on('click', '.add-to-cart-button', function() {
    var productId = $(this).data('product-id'); // Get product ID from data attribute

    // Simulate user message first for proper ordering
    appendMessage("user", "add to cart"); // Display the user's "add to cart" message first


    // Use existing function to send the "add to cart" command to the chatbot
    sendMessageToChatbot("add to cart"); // Triggers the chatbot response as though user typed it
});



});
