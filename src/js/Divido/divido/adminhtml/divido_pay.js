document.observe("dom:loaded", function() {

    var divido = {
        initialize: function () {
            var apiKeyField = $('payment_pay_api_key');
            if (apiKeyField == null) {
                return false;
            }

            this.toggleFields();
            this.bindEvents();
        },
        bindEvents: function () {
            $('payment_pay_product_options').observe('change', this.toggleFields);
            $('payment_pay_finances_displayed').observe('change', this.toggleFields);
        },

        toggleFields: function () {
            var apiKeyField = $('payment_pay_api_key');

            if (! apiKeyField.value) {
                var optionRow = apiKeyField.up(1);
                var siblings  = optionRow.siblings();

                siblings.invoke('hide');
            }

            var productSelection  = $('payment_pay_product_options');
            var priceTreshholdRow = $('row_payment_pay_product_price_treshold');
            if (productSelection.value == 'products_price_treshold') {
                priceTreshholdRow.show();
            } else {
                priceTreshholdRow.hide();
            }

            var planSelection = $('payment_pay_finances_displayed');
            var planListRow   = $('row_payment_pay_finances_list');
            if (planSelection.value == 'selected_finances') {
                planListRow.show();
            } else {
                planListRow.hide();
            }
        }
    }

    divido.initialize();

});
