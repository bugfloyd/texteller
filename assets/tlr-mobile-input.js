/***********************************************
 *                                             *
 *   Texteller Front-End Mobile Field Script   *
 *                                             *
 ***********************************************/

window.tlrMobileField = window.tlrMobileField || {};
window.tlrFrontData = window.tlrFrontData || {
    initialCountry: 'US',
    allowDropdown: 'yes',
    preferredCountries: ['US', 'IN', 'GB'],
    utilsURL: ''
};

(function( $, window, document, app )
{
    app.intlTelInput = {};

    app.intlTelInput.init = function(input = null) {
        let mobileInput;
        if ( null === input ) {
            mobileInput = $('#tlr_national_mobile');
        } else {
            mobileInput = $(input);
        }
        if ( 1 !== mobileInput.length ) {
            return;
        }

        // Init intlTelInput
        app.intlTelInput.instance = window.intlTelInput(mobileInput[0], app.intlTelInput.getOptions(mobileInput[0]) );

        // Validate the entered number on page load and value change
        window.intlTelInputGlobals.loadUtils(tlrFrontData.utilsURL).then(function(){
            app.intlTelInput.validate(mobileInput);
        });
        mobileInput.on('keyup focusout change', function () {
            app.intlTelInput.validate($(this));
        });
    };

    app.intlTelInput.getOptions = function(mobileInput = null) {
        let intlTelOptions = {
            initialCountry: tlrFrontData.initialCountry,
            allowDropdown: tlrFrontData.allowDropdown,
            preferredCountries: tlrFrontData.preferredCountries
        };
        if ( !!tlrFrontData.utilsURL ) {
            intlTelOptions.utilsScript = tlrFrontData.utilsURL;
            intlTelOptions.autoPlaceholder = 'aggressive';
        }
        if ( null !== mobileInput ) {
            let hiddenInputName = $(mobileInput).data('hidden-input-name');
            if ( !! hiddenInputName ) {
                intlTelOptions.hiddenInput = hiddenInputName;
            }
        }
        return intlTelOptions;
    };

    app.intlTelInput.validate = function(mobileInput)
    {
        if ( !mobileInput || !mobileInput.length) {
            return false;
        }

        let value = mobileInput.val();
        let isValid = false;
        if (!!value) {
            if ( !!tlrFrontData.utilsURL ) {
                isValid = app.intlTelInput.instance.isValidNumber();
            } else {
                let illegalChars = /[!@#$%^&*+=\[\]{};':"\\|,.<>\/?]/;
                isValid = value.length > 7 ? !illegalChars.test(value) : false;
            }
        } else {
            return;
        }
        if (isValid) {
            mobileInput.removeClass('tlr-invalid').addClass('tlr-valid');
            return true;
        } else {
            mobileInput.removeClass('tlr-valid').addClass('tlr-invalid');
            return false;
        }
    };

    $(document).ready(function() {
        app.intlTelInput.init();
    });

})( jQuery, window, document, window.tlrMobileField );