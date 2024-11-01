jQuery(document).ready(function ($) {
    $("#form-connect button").click(function (e) {
        e.preventDefault();

        //$("#woocommerce-echo-url-valid").hide();
        $("#form-connect .sp-error").hide();
        $("#form-connect .sp-spinner").show();
        $("#form-connect .sp-text").hide();

        jQuery.post(
            ajaxurl,
            {
                action: "smartpablo_connect",
                business_name: $('#form-connect input[name="business_name"]').val(),
            },
            function (response) {
                $("#form-connect .sp-text").show();
                $("#form-connect .sp-spinner").hide();

                if (response.status === 1) {
                    window.open(response.redirect_url);
                } else {
                    $("#form-connect .sp-error .message").text(response.message);
                    $("#form-connect .sp-error").show();
                }
            }
        );
    });
});