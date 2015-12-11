document.observe("dom:loaded", function() {
    var divido_product_plans = {
        initialize: function () {
            this.toggleFields();
            this.bindEvents();
        },

        bindEvents: function () {
            $('divido_plan_option').observe('change', this.toggleFields);
        },

        toggleFields: function () {
            var planSelection = $('divido_plan_option');
            var planListRow   = $('divido_plan_selection').up(1);
            if (planSelection.value == 'selected_plans') {
                planListRow.show();
            } else {
                planListRow.hide();
            }
        }
    }

    divido_product_plans.initialize();

});
