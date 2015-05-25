var CartItemView = Backbone.View.extend({
    tagName: 'tr',
    initialize: function() {
        this.template = _.template($('#template-cart-item').html());
        this.listenTo(this.model, "change", this.render);
    },

    render: function() {
        this.$el.html(this.template(this.model.toJSON()));

        return this;
    }
});

var CartView = Backbone.View.extend({
    initialize: function (options) {
        _.bindAll(this, 'renderNewItem');

        this.subViews = [];

        this.template = _.template($('#template-cart').html());

        this.vendor_id = options.vendor_id;

        var cart = this.model.getCart(this.vendor_id);

        cart.products.on('add', this.render, this);

        this.listenTo(cart, 'cart:dirty', this.disableCart, this);
        this.listenTo(cart, 'cart:ready', this.enableCart, this);
        this.listenTo(this.model, 'change', this.render);

        this._registerAnchorLinks();
    },

    disableCart: function() {
        this.$el.css({ opacity: 0.5 });
    },

    enableCart: function() {
        this.$el.css({ opacity: 1 });
    },

    render: function() {
        this.$el.html(this.template(this.model.attributes));
        this.model.getCart(this.vendor_id).products.each(this.renderNewItem);

        this._makeCartAndMenuSticky();
        return this;
    },

    remove: function() {
        _.invoke(this.subViews, 'remove');
        Backbone.View.prototype.remove.apply(this, arguments);
    },

    renderNewItem: function(item) {
        var view = new CartItemView({
            model: item
        });
        this.subViews.push(view);

        this.$('.desktop-cart__products').append(view.render().el);
        this._toggleContainerVisibility();
    },

    _registerAnchorLinks: function() {
        var headerHeight = $('.header').outerHeight();

        $('.anchorNavigation').click(function() {
            $('html, body').animate({
                scrollTop: $($.attr(this, 'href')).offset().top - headerHeight
            }, 500);

            return false;
        });
    },

    _makeCartAndMenuSticky: function() {
        var $menuCache = $('.menu'),
            $headerCache = $('.header');

        new StickOnTop(
            $('.desktop-cart-container'),
            $('.desktop-cart'),
            function(){ return $headerCache.height(); },
            function(){ return $menuCache.position().top; },
            function(){ return $menuCache.offset().top + $menuCache.height(); }
        );

        new StickOnTop(
            $('.menu__categories nav'),
            $('.menu__categories'),
            function(){ return $headerCache.height(); },
            function(){ return $menuCache.offset().top + 140; },
            function(){ return $menuCache.offset().top + $menuCache.height(); }
        );
    },

    _toggleContainerVisibility: function() {
        var $productsContainer = this.$('.desktop-cart__products'),
            $cartMsg = this.$('.desktop-cart_order__message'),
            cartEmpty = this.model.vendorCart.get(this.vendor_id).products.length === 0;

        $cartMsg.toggle(cartEmpty);
        $productsContainer.toggle(!cartEmpty);
    }
});
