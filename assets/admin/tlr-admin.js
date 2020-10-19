/************************************
 * Texteller Admin Dashboard Script *
 ************************************/

window.Texteller = window.Texteller || {};
window.tlrAdminData = window.tlrAdminData || {
    memberSelectorNonce: '',
    userSelectorNonce: '',
    memberSendNonce: '',
    sendStatusCheckNonce: '',
    manualSendNonce: '',
    gatewayDataNonce: '',
    memberSelectorPlaceholder: 'Select Members',
    userSelectorPlaceholder : 'Select a User',
    staffSelectorPlaceholder: 'Select Staff',
    waitText: 'Please Wait.',
    mayCloseNowText: 'You can close this window. Sending process would be continued in the background.',
    intlTelOptions: {
        preferredCountries: ['US', 'IN', 'GB'],
        initialCountry: 'US',
        utilsURL: ''
    }
};

(function( $, window, document, app )
{
    'use strict';

    app.l10n = window.tlrAdminData;

    app.memberSelectorInit = function() {
        let memberSelectors = $( '.member-selector');
        if( !memberSelectors.length ) {
            return;
        }
        memberSelectors.each( function( index, memberSelector) {
            $(memberSelector).select2({
                multiple: true,
                maximumSelectionLength: 100,
                placeholder : app.l10n.memberSelectorPlaceholder,
                allowClear: true,
                width: '100%',
                debug: false,
                cache: false,
                minimumInputLength: 3,
                ajax : {
                    cache: false,
                    url: ajaxurl,
                    dataType: 'json',
                    data: function (params) {
                        return {
                            q: params.term,
                            action: 'tlr_get_members',
                            tlr_nonce: app.l10n.memberSelectorNonce
                        }
                    },
                    processResults: function (data) {
                        return app.select2Data(data, 'member_id');
                    }
                }
            });
        });
        app.handleMemberSelectorSave();
    };
    app.userSelectorInit = function() {
        app.$userSelector = $( document.getElementById('tlr_user_id') );
        app.$userSelector.select2({
            placeholder : app.l10n.userSelectorPlaceholder,
            allowClear: true,
            width: '48%',
            debug: false,
            cache: false,
            minimumInputLength: 2,
            ajax : {
                cache: false,
                url: ajaxurl,
                dataType: 'json',
                data: function (params) {
                    return {
                        q: params.term,
                        action: 'tlr_get_users',
                        tlr_nonce: app.l10n.userSelectorNonce
                    }
                },
                processResults: function (data) {
                    return app.select2Data(data, 'user_id');
                }
            }
        });
    };
    app.select2Data = function( ajax_data, objectType ) {
        let items = [];
        $.each( ajax_data.data, function( i, item ) {
            let new_item = {
                'id' : item[objectType],
                'text' : item.text
            };
            items.push( new_item );
        });
        return { results: items };
    };
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
    app.handleVariableLists = function() {
        //todo
        let variableField = $('.variable-field');
        if ( !variableField.length ) {
            return;
        }

        $('a.add-variable').on( 'click', function(e){
            e.preventDefault();
            let theWrapper = $(this).closest('.variable-field');
            let fieldHTML = '<div><input type="text" name="' + $(this).prev().attr('name') +'" value="">'
                + '<a href="javascript:void(0);" class="remove-variable" title="Remove variable">Remove</a></div>';

            //Check maximum number of input fields
            if( theWrapper.children().length < 15){
                theWrapper.append(fieldHTML);
            }
        });
        variableField.on('click', 'a.remove-variable', function(e){
            e.preventDefault();
            $(this).parent('div').remove();
        });
    };

    /**
     * Handles radiogroup data attribute.
     * Un-checks other radio inputs in the same group, if one radio input in the group is checked.
     * Handles radio hidden content in the same way.
     */
    app.handleFieldContent = function() {
        let radios = $('input[type="radio"]');
        radios.change(function() {
            let currentRadio = $(this);
            $('.' + currentRadio.attr('id') + '-content').fadeIn('fast');
            $('label[for=' + currentRadio.attr('id') + ']').addClass('has-active-content');

            let radioGroup = currentRadio.data('radiogroup');
            if( !!radioGroup ) {
                $('*[data-radiogroup=' + radioGroup + ']').not(this).each( function() {
                    let otherRadio = $(this);
                    otherRadio.prop('checked', false);
                    if ( otherRadio.hasClass('has-content') ) {
                        $('.' + otherRadio.attr('id') + '-content').hide();
                        $('label[for=' + otherRadio.attr('id') + ']').removeClass('has-active-content')
                    }
                });
            }
        });

        let contentCheck = $('.has-content:checkbox, .has-content:radio');
        if ( ! contentCheck.length ) {
            return;
        }
        contentCheck.each(function(){
            let contentTrigger = $(this);
            let content = $('.' + contentTrigger.attr('id') + '-content');

            // Display the content of a checked input on page load
            if ( contentTrigger.prop('checked') ) {
                content.fadeIn();
            } else {
                content.fadeOut();
            }
            // Toggle content on check/uncheck for checkboxes
            if ( 'checkbox' === contentTrigger.attr('type') ) {
                contentTrigger.on( 'change', function() {

                    let label = $('label[for=' + contentTrigger.attr('id') + ']');
                    if ( contentTrigger.prop('checked') ) {
                        content.fadeIn('fast');
                        label.addClass('has-active-content')
                    } else {
                        content.fadeOut('fast');
                        label.removeClass('has-active-content')
                    }
                });
            }
        });
    };

    app.handleSortableFields = function() {
        let sortable =  $('.tlr-sortable-fields');
        if ( !sortable.length ) {
            return;
        }

        if ( !!sortable.length ) {
            sortable.sortable({
                axis: 'y',
                curosr: 'move'
            });
        }
        let sortableItem =  $('.sortable-item');
        sortableItem.mouseover(function() {
            $(this).find('.sort-arrows').stop(true, true).show();
        });
        sortableItem.mouseout(function() {
            $(this).find('.sort-arrows').stop(true, true).hide();
        });
    };

    app.handleColorSelectors = function() {
        let colorField = $('.tlr-color-field');
        if ( ! colorField.length ) {
            return;
        }
        colorField.wpColorPicker();
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
                'tlr_security': tlrAdminData.memberSendNonce,
                'member_id': $('#member-id').val(),
                'message_content': messageContentInput.val(),
                'gateway': $('#message-gateway').val(),
                'action': 'tlr_send_message',
                'trigger': 'tlr_manual_send_member'
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
    app.handleStaffSelector = function() {
        let staffSelectors = $('.staff-selector');
        if ( !! staffSelectors.length ) {
            staffSelectors.each( function() {
                $(this).select2({
                    multiple: true,
                    maximumSelectionLength: 10,
                    placeholder : app.l10n.staffSelectorPlaceholder,
                    allowClear: true,
                    width: '100%'
                });
            });
        }
    };

    app.addRemoveCustomNumbers = function() {
        let addRemoveEvent = function (removeButton = null) {
            if ( null === removeButton ) {
                removeButton = $('.remove-number');
            }
            removeButton.on('click', function(e){
                e.preventDefault();
                let numberWrap = $(this).closest('.recipient-number');
                let inputID = numberWrap.find('.tlr-mobile').data('intl-tel-input-id');
                if ( typeof app.mobileInput.instances[inputID] !== 'undefined' ) {
                    delete app.mobileInput.instances[inputID];
                }
                numberWrap.remove();
            });
            $(this).blur();
        };
        $('.add-number').on( 'click', function(){
            event.preventDefault();

            let input = $(this).prev().find('.tlr-mobile');
            let inputName = input.attr('name');
            let name = !!inputName ? ' name="' + inputName + '"' : '';
            let hiddenInputName = input.data('hidden-input-name');
            hiddenInputName = !!hiddenInputName ? hiddenInputName : '';
            let hiddenName = !!hiddenInputName ? ' data-hidden-input-name="' + hiddenInputName + '"' : '';
            let mobileInput = '<input type="text" value="" class="tlr-mobile"'+name+hiddenName+'>';
            let theWrapper = $(this).closest('.recipient-type-fields-wrap');
            let fieldHTML = '<div class="recipient-number">' + mobileInput +
                '<button class="remove-variable remove-number" title="Remove Number">Remove</button></div>';

            if( theWrapper.children().length < 10 ){
                let addedNumberWrap = theWrapper.append(fieldHTML).children().last();
                let mobile = addedNumberWrap.find('.tlr-mobile');
                app.mobileInput.init(mobile);
                addRemoveEvent(theWrapper.children().last().find('.remove-number'));
            }
        });
        addRemoveEvent();
    };

    /**
     * Options: Staff Selector - Adds single staff inputs to DOM to get saved on page submit.
     */
    app.handleMemberSelectorSave = function() {
        let memberSelectors = $( '.member-selector, .staff-selector');
        if( !memberSelectors.length ) {
            return;
        }
        memberSelectors.each(function(){
            let memberSelector = $(this);
            if ( 'needsExtraField' === memberSelector.data('selector-tag') ) {
                let wrap = memberSelector.parent();
                memberSelector.on("change.select2", function ()
                {
                    let membersIDs = memberSelector.val();
                    wrap.find('.member-selector-data').remove();
                    let name = memberSelector.data('selector-name');

                    $.each( membersIDs, function(i, memberID) {
                        wrap.append('<input hidden class="member-selector-data" name="' + name +'" value="' + memberID + '">');
                    });
                });
            }
        });
    };

    app.mobileInput = {};
    app.mobileInput.instances = {};
    app.mobileInput.getOptions = function(mobileInput = null) {
        let intlTelOptions = {
            preferredCountries: app.l10n.intlTelOptions.preferredCountries,
            initialCountry:  app.l10n.intlTelOptions.initialCountry
        };
        if ( null !== mobileInput ) {
            let hiddenInputName = $(mobileInput).data('hidden-input-name');
            if ( !! hiddenInputName ) {
                intlTelOptions.hiddenInput = hiddenInputName;
            }
        }
        if( !!app.l10n.intlTelOptions.utilsURL ) {
            intlTelOptions.utilsScript =  app.l10n.intlTelOptions.utilsURL;
            intlTelOptions.autoPlaceholder= 'aggressive';
        }

        if( $('.button.save_member').length ) {
            intlTelOptions.hiddenInput = 'tlr_mobile'
        }
        return intlTelOptions;
    };
    app.mobileInput.init = function( inputs = null ) {
        let mobileInputs;
        if ( null === inputs ) {
            mobileInputs = $('.mobile-input, .tlr-mobile');
        } else {
            mobileInputs = $(inputs);
        }
        if ( !mobileInputs || !mobileInputs.length ) {
            return;
        }

        mobileInputs.each( function( index, mobileInput) {
            let field = $(mobileInput);
            let intlTelInput = window.intlTelInput(mobileInput, app.mobileInput.getOptions(mobileInput) );

            // If we want to get the data by JS, add it to the instances
            if ( !field.data('hidden-input-name') ) {
                app.mobileInput.instances[field.data('intl-tel-input-id')] = intlTelInput;
            }
        });
    };

    app.handleMessageTags = function( tags, contentField ) {
        tags.click(function () {
            if ( !tags.length || !contentField.length || 'none' === contentField.closest('.trigger-recipient-content').css('display') ) {
                return;
            }
            let cursorPos = contentField.prop('selectionStart');
            let currentText =  contentField.val();
            contentField.val([currentText.slice(0, cursorPos), $(this).text(), currentText.slice(cursorPos)].join(''));
        });
    };

    app.notifications = {};
    app.notifications.init = function() {
        let triggers = $('.notification-trigger');
        if ( !triggers.length ) {
            return;
        }

        $('.recipient-type-label, .trigger-recipient-type-label').on( 'click', function(){
            let label = $(this);
            if ( label.hasClass('has-active-content') ) {
                label.removeClass('has-active-content');
                $('.' + label.attr('for') + '-content' ).fadeOut('fast');
                //$('#' + label.attr('for')).change();
            } else {
                $('#' + label.attr('for')).change();
            }
        });

        app.gateways = {};

        app.addRemoveCustomNumbers();
        app.notifications.handleGateway();
        app.notifications.handleInterfaceSelector();

        $('.recipient-content').each(function () {
            let container = $(this);
            container.find('textarea.tlr-text-content').each(function() {
                app.handleMessageTags( container.find('.content-tags-wrap code.tlr-tag'), $(this) );
            });
        });
    };
    app.notifications.handleGateway = function() {
        function getGatewayData(gateway, interfaceSelector) {
            $.ajax({
                url: ajaxurl,
                type: 'GET',
                data: {
                    'action': 'tlr_get_gateway_data',
                    'gateway': gateway,
                    '_ajax_nonce': app.l10n.gatewayDataNonce
                },
                timeout: 15000,
                success: (response) => {
                    app.gateways[gateway] = {};
                    app.gateways[gateway].interfaces = response.interfaces;
                    app.gateways[gateway].defaultInterface = response.defaultInterface;
                    app.gateways[gateway].contentTypes = response.contentTypes;

                    interfaceSelector.find('option').remove();
                    $.each( response.interfaces, function(key, title) {
                        let selected = '';
                        if ( key === response.defaultInterface ) {
                            selected = ' selected="selected"';
                        }
                        let contentType = 'text';
                        if ( 'undefined' !== response.contentTypes[key] ) {
                            contentType = response.contentTypes[key];
                        }
                        interfaceSelector.append('<option value="'+key+'" data-content-type="'+contentType +'"'
                            + selected + '>' +title+ '</option>');
                    } );
                    interfaceSelector.change();
                }
            })
        }

        let gatewaySelectors = $('.gateway-selector');
        gatewaySelectors.each(function(){
           let gatewaySelector = $(this);
           let interfaceSelector = gatewaySelector.closest('.action-field-wrap').next().find('.interface-selector');

            gatewaySelector.change(function(){
                interfaceSelector.find('option').remove();
                interfaceSelector.append('<option value="">Loading...</option>');

                let gateway = $(this).val();

                /**
                 * If we don't have gateway data in the app cache, get the data using Ajax
                 */
                if(
                    !app.gateways.hasOwnProperty(gateway)
                    || !app.gateways[gateway].hasOwnProperty('interfaces')
                    || !app.gateways[gateway].hasOwnProperty('contentTypes')
                ) {
                    getGatewayData(gateway, interfaceSelector);
                }

                /**
                 * If we have gateway data in app cache, handle gateway fields
                 */
                else {
                    interfaceSelector.find('option').remove();
                    $.each( app.gateways[gateway].interfaces, function(key, title) {
                        let selected = '';
                        if ( key === app.gateways[gateway].defaultInterface ) {
                            selected = ' selected="selected"';
                        }
                        let contentType = 'text';
                        if ( 'undefined' !== app.gateways[gateway].contentTypes[key] ) {
                            contentType = app.gateways[gateway].contentTypes[key];
                        }

                        interfaceSelector.append('<option value="'+key+'" data-content-type="'+contentType +'"'
                            +selected+'>'+title+'</option>');
                        interfaceSelector.change();
                    } );
                }
            });
        });
    };
    app.notifications.handleInterfaceSelector = function() {
        let interfaceSelectors = $('.interface-selector');
        interfaceSelectors.each(function() {
            //app.notifications.updateInterfaceContent(this);
            $(this).change(app.notifications.updateInterfaceContent);
            app.notifications.updateInterfaceContent(this);
        });

    };
    app.notifications.updateInterfaceContent = function(selector = null) {
        let interfaceSelector =  (null === selector || !!selector.target) ? $(this) : $(selector);
        let selectedInterface = interfaceSelector.val();
        let contentType = interfaceSelector.find('option[value="'+selectedInterface+'"]').data('content-type');
        let recipientContentWrap = interfaceSelector.closest('.basic-fields').siblings('.recipient-message-content');
        let gatewayContentWrap = recipientContentWrap.find('.gateway-content');
        let textContentWrap = recipientContentWrap.find('.text-content');
        let interfaceContent = gatewayContentWrap.find('.' + selectedInterface + '-content');

        if ( !!interfaceContent.length ) {
            if ( 'extra' === contentType || 'replace' === contentType ) {
                gatewayContentWrap.fadeIn('fast');
            } else {
                gatewayContentWrap.hide();
            }
        } else {
            gatewayContentWrap.hide();
        }


        if ( 'extra' === contentType || 'text' === contentType ) {
            textContentWrap.fadeIn('fast');
        } else {
            textContentWrap.hide();
        }
    };

    app.manualSend = {};
    app.manualSend.init = function() {
        let filterButton = $("#tlr-filter-members");
        if( !filterButton.length ) {
            return;
        }
        let buttonDefaultValue = filterButton.text();
        let filterResult = $('.member-filter-result');
        let content = $('#message-content');
        let sendResult = $('.send-result');
        let sendButton = $('#tlr-send');
        app.manualSend.sendButton = sendButton;
        app.manualSend.sendResult = sendResult;

        // Filter members recipient type
        app.manualSend.handleFilterCheckboxes();
        filterButton.on( 'click', function (event) {
            event.preventDefault();
            filterButton.attr('disabled', 'true');
            filterResult.text( app.l10n.waitText );

            let data = app.manualSend.getMemberFiltersData();
            data.action = 'tlr_filter_members';
            data.tlrSecurity = $('#filter-nonce').val();

            $.post( ajaxurl, data, function (response) {
                filterButton.removeAttr('disabled');
                filterButton.text(buttonDefaultValue);

                if ( response.success === false) {
                    if (response.data) {
                        filterResult.text(response.data);
                    }
                } else {
                    let data = JSON.parse(response);
                    if ( !data.hasOwnProperty('responseText') ) {
                        filterResult.text('An error occurred. Please try again.');
                        return;
                    }
                    filterResult.text(data.responseText);
                }
            } );
        });

        // Custom numbers recipient type
        app.addRemoveCustomNumbers();

        // Message tags
        app.handleMessageTags( $('code.tlr-tag'), content );

        // Enable send button if content is not empty
        content.on('input selectionchange propertychange change keyup click', function() {
            if( content.val().length ) {
                sendButton.removeAttr('disabled');
            } else {
                sendButton.attr('disabled', 'disabled' );
            }
        });

        // Send Messages
        sendButton.on( 'click', function() {
            event.preventDefault();
            sendButton.attr('disabled', 'true');
            sendResult.text( app.l10n.waitText );

            let numbers = [];
            if ( !!app.mobileInput.instances ) {
                $.each(app.mobileInput.instances, function(index, intlTelInput) {
                    numbers.push(intlTelInput.getNumber());
                });
            }

            let data = app.manualSend.getMemberFiltersData();
            data.action = 'tlr_manual_send';
            data.tlrSecurity = app.l10n.manualSendNonce;
            //data.recipients = $("input[name='tlr_recipients']:checked").val();

            data.content = content.val();
            data.gateway = $('select#gateway').val();
            data.membersCheck = $('#select-members').prop('checked') ? 1 : 0;
            data.selectedMembers = $('#selected-members').val();
            data.staffCheck = $('#site-staff').prop('checked') ? 1 : 0;
            data.selectedStaff = $('#selected-staff').val();
            data.filterMembersCheck = $('#filter-members').prop('checked') ? 1 : 0;
            data.numbersCheck = $('#custom-numbers').prop('checked') ? 1 : 0;
            data.customNumbers = numbers;

            $.post( ajaxurl, data, function (response) {
                if ( response.hasOwnProperty('success') && false === response.success && response.hasOwnProperty('data')) {
                    sendResult.text( response.data );
                    sendButton.removeAttr('disabled');
                    return;
                } else if ( response.hasOwnProperty('responseText') ) {
                    sendResult.text(response.responseText);
                    setTimeout( function() {
                        app.manualSend.checkStatus();
                    }, 2000 );
                    $('#send-result-wrap').append( '<div><span class="close-now">' + app.l10n.mayCloseNowText +  '</span></div>' );
                    return;
                }

                sendResult.text('An error occurred. Please try again.');
                sendButton.removeAttr('disabled');
            } );
        });
    };

    app.manualSend.handleFilterCheckboxes = function() {
        let checkboxes = $('.filters-container input[type="checkbox"][value="any"]');
        if ( !!checkboxes.length ) {
            checkboxes.each( function () {
                let checkbox = $(this);
                let childCheckboxes = checkbox.closest('.filter-group-container').find('input[type="checkbox"]:not([value="any"])');
                if ( checkbox.prop('checked') ) {
                    childCheckboxes.prop('disabled',true);
                }
                checkbox.change(function(){
                    childCheckboxes.prop('disabled', !!this.checked);
                });
            });
        }
    };

    app.manualSend.getMemberFiltersData = function() {
        let memberGroups = [];
        $.each($("input[name='member_group[]']:checked"), function(){
            memberGroups.push($(this).val());
        });

        let regOrigins = [];
        $.each($("input[name='registration_origin[]']:checked"), function(){
            regOrigins.push($(this).val());
        });

        let userLinked = [];
        $.each($("input[name='user_linked[]']:checked"), function(){
            userLinked.push($(this).val());
        });

        let status = [];
        $.each($("input[name='status[]']:checked"), function(){
            status.push($(this).val());
        });

        let title = [];
        $.each( $("input[name='title[]']:checked"), function(){
            title.push($(this).val());
        });

        return {
            'member_groups': memberGroups,
            'member_reg_origin': regOrigins,
            'statuses': status,
            'user_linked': userLinked,
            'title': title
        };
    };
    app.manualSend.checkStatus = function() {
        let data = {
            'tlr_security' : app.l10n.sendStatusCheckNonce,
            'send_nonce' : app.l10n.manualSendNonce,
            'action' : 'tlr_manual_send_status',
        };

        $.post( ajaxurl, data, function (response) {

            if ( response.hasOwnProperty('responseStatus') && response.hasOwnProperty('responseText') ) {
                app.manualSend.sendResult.text(response.responseText);

                if ( 'sending' === response.responseStatus ) {
                    setTimeout( function() {
                        app.manualSend.checkStatus();
                    }, 2000 );
                } else if( 'done' === response.responseStatus ) {
                    $('.close-now').hide();
                    app.manualSend.sendButton.removeAttr('disabled');
                }
            } else {
                setTimeout( function() {
                    checkStatus();
                }, 4000 );
            }
        } );
    };

    $( document ).ready( function()
    {
        app.memberSelectorInit();
        app.userSelectorInit();
        app.handleSMSCount();
        app.handleVariableLists();
        app.handleFieldContent();
        app.handleSortableFields();
        app.handleColorSelectors();
        app.handleMemberSend();
        app.handleStaffSelector();
        app.notifications.init();
        app.manualSend.init();
        app.mobileInput.init();
    });

    return app;

})( jQuery, window, document, window.Texteller );