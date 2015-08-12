VOLO.CustomerModel = Backbone.Model.extend({
    defaults: {
        first_name: '',
        last_name: '',
        email: '',
        mobile_number: '',
        mobile_country_code: ''
    },

    initialize: function (data, options) {
        _.bindAll(this);
        this.isGuest = options.isGuest;
        if (this.isGuest) {
            this.set('id', 'anon.');
        }
    },

    urlRoot: function() {
        return Routing.generate('api_customers_get');
    },

    validate: function(attrs, options) {
        if (!attrs.first_name) {
            return 'first_name not valid';
        }
        if (!attrs.last_name) {
            return 'last_name not valid';
        }
        if (!attrs.email) {
            return 'email not valid';
        }
        if (!attrs.mobile_number) {
            return 'mobile number not valid';
        }
    },

    getFullMobileNumber: function () {
        var mobileNumber = '';
        if (this.get('mobile_country_code') && this.get('mobile_number')) {
            mobileNumber = '+' + this.get('mobile_country_code') + ' '  + this.get('mobile_number');
        }

        return mobileNumber;
    },

    getFullName: function() {
        if (this.isValid()) {
            return this.get('first_name') + ' ' + this.get('last_name');
        }

        return '';
    }
});