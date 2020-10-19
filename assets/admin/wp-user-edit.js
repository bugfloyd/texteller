(function($) {

    function bindMembershipForm() {
        let $formWrapper,
            $registerButton,
            $cancelButton,
            $memberID;

        $formWrapper = $( 'div.tlr-register' );
        $registerButton  = $( 'button.tlr-register-member' );
        $cancelButton = $( 'button.tlr-cancel-reg' );
        $memberID = $('#tlr-member-id').val();

        function showForm()
        {
            let createUserForm = $("#createuser");
            createUserForm.find("#email").closest(".form-required").removeClass("form-required").find(".description").hide();

            $registerButton.hide();
            $formWrapper.show();
            $formWrapper.find( 'input' ).prop('disabled', false);
            $formWrapper.find( 'select' ).prop('disabled', false);
        }

        function hideForm()
        {
            let createUserForm = $("#createuser");
            createUserForm.find("#email").closest(".form-field").addClass("form-required").find(".description").show();

            $registerButton.show().focus();
            $formWrapper.hide();
            $formWrapper.find( 'input' ).prop('disabled', true);
            $formWrapper.find( 'select' ).prop('disabled', true);
        }

        if ( $registerButton.length && ! $memberID > 0 ) {
            hideForm();
        } else {
            showForm();
        }

        $registerButton.show();
        $registerButton.on( 'click', showForm );
        $cancelButton.on( 'click', hideForm );
    }

    $(document).ready( function() {
        bindMembershipForm();
    });
}) (jQuery);