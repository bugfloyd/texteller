/***********************************************
 *                                             *
 *   Texteller Front-End Mobile Field Script   *
 *                                             *
 ***********************************************/

window.tlrNewsletterData = window.tlrNewsletterData || {
    intlTelOptions: {
        initialCountry: 'US',
        allowDropdown: 'yes',
        preferredCountries: ['US', 'IN', 'GB'],
        utilsURL: ''
    },
    submitWorkingLabel: 'Please Wait...',
    ajaxURL: '',
    errorText: 'An error occurred please try again.',
    retryText: 'Retry',
    doneText: 'Done'
};

window.tlrNewsletter = window.tlrNewsletter || {
    constants: window.tlrNewsletterData
};


(function( $, window, document, app )
{
    app.inputs = $('.tlr-fields-wrapper input:not(.tlr-submit), .tlr-fields-wrapper select');

    app.initMobileInput = function() {
        let mobileInput = $('.tlr-registration-form .tlr-mobile-field');
        if ( 1 !== mobileInput.length ) {
            return;
        }

        let intlTelOptions = {
            initialCountry: app.constants.intlTelOptions.initialCountry,
            allowDropdown: app.constants.intlTelOptions.allowDropdown,
            preferredCountries: app.constants.intlTelOptions.preferredCountries
        };
        if ( !!app.constants.intlTelOptions.utilsURL ) {
            intlTelOptions.utilsScript = app.constants.intlTelOptions.utilsURL;
            intlTelOptions.autoPlaceholder = 'aggressive';
        }

        // Init intlTelInput
        app.intlTelInputInstance = window.intlTelInput(mobileInput[0], intlTelOptions );
    };

    app.addInputsValidateEvent = function() {
        if ( !!app.inputs && !!app.inputs.length ) {
            app.inputs.on('keyup focusout change', function () {
                app.isFieldDataValid($(this));
            });
        }
    };

    app.isFieldDataValid = function(field) {
        let value = field.val();
        let isValid = false;

        if ( !!value ) {
            if ( field.hasClass('tlr-mobile-field') ) {
                if ( !!app.constants.intlTelOptions.utilsURL ) {
                    isValid = app.intlTelInputInstance.isValidNumber();
                } else {
                    let forbidden_chars = /[!@#$%^&*+=\[\]{};':"\\|,.<>\/?]/;
                    isValid = value.length > 7 ? !forbidden_chars.test(value) : false;
                }
            } else if ( field.hasClass('tlr-email-field') ) {
                let email_format = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
                isValid = value.length > 8 ? email_format.test(String(value).toLowerCase()) : false;
            } else {
                let forbiddenChars = /[!@#$%^&*+=\[\]{};':"\\|,.<>\/?]/;
                isValid = value.length > 1 ? !forbiddenChars.test(value) : false;
            }
        } else {
            isValid = !field.data('tlr-required');
        }
        if (isValid) {
            field.removeClass('tlr-invalid').addClass('tlr-valid');
            return true;
        } else {
            field.removeClass('tlr-valid').addClass('tlr-invalid');
            return false;
        }
    };

    app.handleFormSubmit = function() {
        let submitButton = $('.tlr-submit-registration');
        if ( !submitButton.length ) {
            return;
        }
        submitButton.click(function(e) {
            e.preventDefault();

            if ( !!app.inputs && !!app.inputs.length ) {
                let isFormValid = true;

                app.inputs.each(function(){
                    let input = $(this);
                    if ( !app.isFieldDataValid(input) ) {
                        isFormValid = false;
                        input.focus();
                        return false;
                    }
                });

                if (isFormValid) {
                    let submitInitialLabel = submitButton.text();
                    submitButton.addClass('tlr-working');
                    submitButton.text( app.constants.submitWorkingLabel );

                    let postData = {
                        'tlr_first_name': $('.tlr-first-name-field').val(),
                        'tlr_last_name': $('.tlr-last-name-field').val(),
                        'tlr_mobile': app.intlTelInputInstance.getNumber(),
                        'tlr_title': $('.tlr-title-field').val(),
                        'tlr_member_group': $('.tlr-member-group-field').val(),
                        'tlr_email': $('.tlr-email-field').val(),
                        'tlrCheck': $('#tlr-registration-check').val(),
                        'action': 'tlr_registration'
                    };

                    $.post(app.constants.ajaxURL, postData, function( response )
                    {
                        let data = JSON.parse( response );
                        let responseContainer = $('.tlr-response');
                        responseContainer.empty();

                        submitButton.removeClass('tlr-working');
                        submitButton.text( submitInitialLabel );

                        // If the ajax response isn't well formatted in case any error occurs
                        if ( !data.hasOwnProperty('response') || !data.hasOwnProperty('code')) {
                            responseContainer.html(
                                "<span class='tlr-result-text error'>"
                                + app.constants.errorText + "</span>"
                            );
                            responseContainer.append(
                                '<div class="tlr-response-submit-wrap"><button class="tlr-submit tlr-submit-retry" type="button">'
                                + app.constants.retryText + '</button></div>'
                            );
                            return;
                        }

                        responseContainer.html( data.response );

                        if( 'notice' !== data.code ) {
                            let buttonText, buttonClass = '';
                            responseContainer.append('<div class="tlr-response-submit-wrap"></div>');
                            if( 'success' === data.code ) {
                                buttonText = app.constants.doneText;
                                buttonClass = 'reset';
                            } else {
                                buttonText = app.constants.retryText;
                                buttonClass = 'retry';
                            }
                            responseContainer.find('.tlr-response-submit-wrap').html(
                                '<button class="tlr-submit tlr-submit-' + buttonClass + '" type="button">' + buttonText + '</button>'
                            );
                        }

                        let resultsWrapper = $('.tlr-results-wrapper');
                        let overlay = $('.tlr-overlay');
                        overlay.fadeIn();
                        resultsWrapper.fadeIn('slow');

                        $('.tlr-submit-retry').on('click', function(e) {
                            e.preventDefault();
                            resultsWrapper.fadeOut('slow');
                            overlay.fadeOut();
                        });

                        $('.tlr-submit-reset').on('click', function(e) {
                            e.preventDefault();
                            app.inputs.each(function(){
                                let input = $(this);
                                if ( 'tlr-registration-check' !== input.attr('id') ) {
                                    $(this).removeClass('tlr-invalid tlr-valid').val('');
                                }
                            });
                            resultsWrapper.fadeOut('slow');
                            overlay.fadeOut();
                        });
                    });
                }
            }
        });
    };

    app.handleVerification = function() {
        let verificationWrap = $('.tlr-response .tlr-verification');
        if ( verificationWrap.length > 0 && 'none' === verificationWrap.css('display') ) {
            $('.tlr-verification-wrap').append( '<button class="tlr-submit tlr-submit-reset" type="button">' + app.constants.doneText + '</button>' );
        }
    };

    $(document).ready(function () {
        app.initMobileInput();
        app.addInputsValidateEvent();
        app.handleFormSubmit();
        app.handleVerification();

        // Display the form
        $('.tlr-fields-wrapper').fadeIn('slow');
    });

})( jQuery, window, document, window.tlrNewsletter );