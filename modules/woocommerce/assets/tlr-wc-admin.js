window.TextellerWC = window.TextellerWC || {};

window.tlrWCAdminData = window.tlrWCAdminData || {
    memberSendNonce: ''
};

(function( $, window, document, app )
{
    'use strict';

    app.handleSMSCount = function () {
        let textarea = $('.tlr-count');
        if ( !textarea.length ) {
            return;
        }

        textarea.each(function() {
            let smsContent = $(this);
            let html = '<div class="sms-counter-wrap"><ul class="sms-counter">' +
                '<li><span class="messages"></span> message(s)</li>' +
                '<li><span class="remaining"></span>/<span class="per_message"></span></li>' +
                '</ul></div>';
            if ( !!smsContent.data('sms-count-wrap') && 'before' === smsContent.data('sms-count-wrap') ) {
                smsContent.before(html);
                smsContent.countSms( smsContent.prev() );
            } else {
                smsContent.after(html);
                smsContent.countSms( smsContent.next() );
            }

        });
    };

    app.handleMemberSend = function() {
        let sendMessageButton = $("#send-message");
        if ( !sendMessageButton.length ) {
            return;
        }

        let messageContentInput = $('#message-content');
        let buttonDefaultValue = sendMessageButton.text();
        messageContentInput.on( 'keyup focusout change', function ()
        {
            let messageContent = $(this).val();
            if ( messageContent.length > 0 ) {
                sendMessageButton.removeAttr('disabled');
            } else {
                sendMessageButton.attr('disabled', 'true');
            }
        });

        sendMessageButton.on( 'click', function (event)
        {
            let sendResult = $('.send-result');

            sendResult.text('');
            event.preventDefault();
            sendMessageButton.attr('disabled', 'true');
            sendMessageButton.text('Please Wait...');

            let data = {
                'tlr_security': tlrWCAdminData.memberSendNonce,
                'member_id': $('#member-id').val(),
                'message_content': messageContentInput.val(),
                'gateway': $('#message-gateway').val(),
                'action': 'tlr_send_message',
                'trigger': 'tlr_manual_send_order'
            };

            $.post( ajaxurl, data, function (response)
            {
                if ( response.success === false) {
                    if (response.data) {
                        sendResult.text(response.data);
                        sendMessageButton.removeAttr('disabled');
                        sendMessageButton.text(buttonDefaultValue);
                    }
                } else {
                    let data = JSON.parse(response);
                    if (!data.hasOwnProperty('responseText') || !data.hasOwnProperty('responseCode')) {
                        sendResult.text('An error occurred. Please try again.');
                        sendMessageButton.removeAttr('disabled');
                        sendMessageButton.text(buttonDefaultValue);
                        return;
                    }
                    let rCode = parseInt(data.responseCode);
                    let rText = data.responseText;

                    if (rCode < 200) {
                        messageContentInput.val('');
                        sendResult.text('Success: ' + rText);
                        sendMessageButton.text(buttonDefaultValue);
                    } else {
                        sendMessageButton.removeAttr('disabled');
                        sendResult.text('Error: ' + rText);
                        sendMessageButton.text(buttonDefaultValue);
                    }
                }
            });
        });
    };

    $( document ).ready( function()
    {
        app.handleSMSCount();
        app.handleMemberSend();
    });

    return app;

})( jQuery, window, document, window.TextellerWC );