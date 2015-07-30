var CheckoutPageView = Backbone.View.extend({
    events: {
        'click #checkout-finish-and-pay-button': '_submitOrder',
        'click #add_new_address_link': 'render',
        'click #edit_contact_information_form_link': '_openContactInformationForm'
    },

    initialize: function (options) {
        _.bindAll(this);

        this.vendorCode = this.$el.data().vendor_code;
        this.vendorId = this.$el.data().vendor_id;

        this.spinner = new Spinner();
        this.domObjects = {};
        this.domObjects.$header = options.$header;
        this.configuration = options.configuration;
        this.customerModel = options.customerModel;
        this.userAddressCollection = options.userAddressCollection;
        this.contactInformationView = new VOLO.CheckoutContactInformationView({
            el: this.$('.checkout__contact-information'),
            customerModel: this.customerModel,
            userAddressCollection: this.userAddressCollection
        });
        this.timePickerView = new TimePickerView({
            model: options.cartModel,
            vendor_id: this.vendorId
        });

        console.log('is guest user ', this.customerModel.isGuest);
        this.model.set('is_guest_user', this.customerModel.isGuest);

        this.listenTo(this.model, 'change', this.render, this);

        this.listenTo(this.model, 'payment:success', this.handlePaymentSuccess, this);
        this.listenTo(this.model, 'payment:error', this.handlePaymentError, this);
        this.listenTo(this.userAddressCollection, 'update', this.renderContactInformationStep);
    },

    render: function () {
        var addressFormVisible = this.$('#delivery_information_form_button').is(':visible');
        var contactFormVisible = this.$('#contact-information-form').is(':visible');

        console.log('CheckoutPageView:render', this.model.isValid());
        this.$('#checkout-finish-and-pay-button').toggleClass('button--disabled', this.userAddressCollection.length > 0 && !this.model.canBeSubmitted() || addressFormVisible || contactFormVisible);
        this.renderContactInformationStep();

        this.timePickerView.setElement(this.$('.checkout__time-picker'));
        this.timePickerView.render();

        return this;
    },

    renderContactInformationStep: function () {
        console.log('renderContactInformationStep', this.cid);
        this.contactInformationView.render();
    },

    unbind: function () {
        this.stopListening();
        this.undelegateEvents();
        this.contactInformationView.unbind();
        this.timePickerView.unbind();
    },

    _openContactInformationForm: function() {
        var $editContactInformationForm = this.$('#checkout-edit-contact-information'),
            editContactInformationFormIsVisible;

        if ($editContactInformationForm.length){
            $editContactInformationForm.toggleClass('hide');
            editContactInformationFormIsVisible = $editContactInformationForm.hasClass('hide');
            $('#contact_information').find('.checkout__item').toggleClass('hide', !editContactInformationFormIsVisible);
            this.render();
        }
    },

    _submitOrder: function () {
        var isSubscribedNewsletter = this.contactInformationView.isNewsletterSubscriptionChecked(),
            address = this.userAddressCollection.get(this.model.get('address_id'));

        if (this.$('#delivery-information-form-button').is(':visible')) {
            this.$('#error-message-delivery-not-saved').removeClass('hide');
            this._scrollToError(this.$('#checkout-delivery-information-address').offset().top);

            return false;
        }

        if (!this.model.canBeSubmitted()) {
            return false;
        }

        this.$('#error-message-delivery-not-saved').addClass('hide');
        this.spinner.spin(this.$('#checkout-finish-and-pay-button')[0]);

        //var address = this.userAddressCollection.get(this.model.get('address_id'));
        this.model.placeOrder(this.vendorCode, this.vendorId, this.customerModel, address, isSubscribedNewsletter);

        this.trigger('validationView:validateSuccessful', {
            newsletterSignup: isSubscribedNewsletter
        });

        this.$('.form__error-message').addClass('hide');
    },

    _scrollToError: function(msgOffset) {
        var paddingFromHeader = 16,
            scrollToOffset =  msgOffset - paddingFromHeader - this.domObjects.$header.outerHeight();

        this.$('#error_msg_delivery_not_saved').removeClass('hide');
        $('body').animate({
            scrollTop: scrollToOffset
        }, this.configuration.anchorScrollSpeed);
    },

    handlePaymentSuccess: function (data) {
        if (_.isNull(data.hosted_payment_page_redirect)) {
            this.model.cartModel.emptyCart(this.vendorId);
            Turbolinks.visit(Routing.generate('order_tracking', {orderCode: data.code}));
        } else {
            if (data.hosted_payment_page_redirect.method === 'post') { // adyen hpp
                var params = data.hosted_payment_page_redirect.parameters,
                    url = "https://" + window.location.hostname + Routing.generate('handle_payment', {'orderCode': data.code});

                params.countryCode = this.configuration.countryCode.toUpperCase();
                params.resURL = url;
                this.redirectPost(data.hosted_payment_page_redirect.url, params);
            } else {
                window.location.replace(data.hosted_payment_page_redirect.url); // paypal
            }
        }
        this.spinner.stop();
    },

    handlePaymentError: function (data) {
        var exists = _.get(data, 'exists', {exists: false});
        this.$('.form__error-message').removeClass('hide');

        if (_.isObject(data) && _.isString(_.get(data, 'error.errors.message'))) {
            this.$('.form__error-message').html(data.error.errors.message);
        } else if (exists) {
            this.contactInformationView.renderExistingUser();
        } else {
            alert('500 error');
            console.log(data);
        }
        this.spinner.stop();
    },

    redirectPost: function (location, args) {
        var compiled = _.template(this.$('#template__form__redirect').html()),
            view = compiled({location: location, args: args});

        this.$el.append(view);
        this.$('#checkout-form-redirect').submit();
    }
});
