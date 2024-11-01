jQuery(document).ready(function ($) {
    var toggle_fields = function (show) {
        if (show) {
            jQuery('#billing_company_field').show();
            jQuery('#billing_business_id_field').show();
            jQuery('#billing_tax_id_field').show();
            jQuery('#billing_vat_no_field').show();
        }
        else {
            jQuery('#billing_company_field').hide();
            jQuery('#billing_business_id_field').hide();
            jQuery('#billing_tax_id_field').hide();
            jQuery('#billing_vat_no_field').hide();
        }
    }

    jQuery('#billing_as_company').change(function () {
        toggle_fields(jQuery(this).is(':checked'));
    });

    toggle_fields(jQuery('#billing_as_company').is(':checked'))
});