jQuery(function ($) {
    var processing = false;
    jQuery('#beekash-payment-button').click(function () {
        return paywithBeekashWoo();
    });

    var onComplete = function (response, closeModal) {
        if (response.status_code === "1000") {
            processing = true
            $( 'body' ).block( {
                message: null,
                overlayCSS: {
                    background: "#fff",
                    opacity: 0.8
                },
                css: {
                    cursor: "wait"
                }
            } );
            closeModal()
        }else{
            processing = false
        }
    };

    var onCloseCheckout = ()=>{
        processing = false
    }

    function paywithBeekashWoo() {

        // if (wc_params.phone_number && wc_params.phone_number.length > 11){
        //     alert('Invalid Phone number. A maximum of 11 characters is required.')
        // }
        if (processing) {
            processing = false;
            return true;
        }

        const today = new Date();
        const yyyy = today.getFullYear();
        let mm = today.getMonth() + 2; // Months start at 0!
        let dd = today.getDate();

        if (dd < 10) dd = '0' + dd;
        if (mm < 10) mm = '0' + mm;

        const formattedToday = mm + '/' + dd + '/' + yyyy;

        var options = {
            invoice_email: wc_params.invoice_email,
            publish_key: wc_params.publish_key,
            invoice_description: wc_params.invoice_description,
            invoice_recipient: wc_params.invoice_recipient,
            invoice_phone: wc_params.invoice_phone,
            invoice_return_url: wc_params.invoice_return_url,
            invoice_expiry_time: wc_params.invoice_expiry_time,
            invoice_currency: wc_params.invoice_currency,
            invoice_amount: wc_params.invoice_amount,
            pay_for_invoice_terminal_link: wc_params.pay_for_invoice_terminal_link,
        };

        //console.log("options", options);
        window.location.href = wc_params.pay_for_invoice_terminal_link;
        //window.BeekashPay(options, onComplete, onCloseCheckout);

        //window.MakeTheCall(paramsParsed,onComplete, onCloseCheckout);

        // $.ajax({
        //     url: wc_params.endpoint,
        //     type: 'POST',
        //     dataType: 'json',
        //     contentType: 'application/json',
        //     headers: {
        //         'Content-Type': "application/json; charset=utf-8",
        //     },
        //     data: JSON.stringify(paramsParsed),
        //     success: function(response) {
        //         //window.location.href = response.result.pay_for_invoice_terminal_link;
        //         var options = {
        //             publish_key: wc_params.publish_key,
        //             pay_for_invoice_terminal_link: response.result.pay_for_invoice_terminal_link,
        //         }
        //         window.BeekashPay(options, onComplete, onCloseCheckout);
        //     }
        // });
    }
});