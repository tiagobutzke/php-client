var CartToppingView = Backbone.View.extend({
    tagName: 'span',
    className: 'summary__item__extra',

    initialize: function() {
        this.template = _.template($('#template-cart-summary-extra-item').html());
    },

    render: function() {
        this.$el.html(this.template(this.model.toJSON()));

        return this;
    }
});

var CartItemView = Backbone.View.extend({
    tagName: 'tr',
    className: 'cart__item',
    events: {
        'click .summary__item__name': '_editItem',
        'click .summary__item__price': '_editItem',
        'click .summary__item__sign': '_editItem',
        'click .summary__item__quantity': '_editItem',
        'click .summary__item__remove': '_removeItem',
        'click .summary__item__minus': '_decreaseQuantity',
        'click .summary__item__plus': '_increaseQuantity'
    },

    initialize: function(options) {
        _.bindAll(this);
        this.template = _.template($('#template-cart-item').html());
        this.cartModel = options.cartModel;
        this.vendorId = options.vendorId;
        this.listenTo(this.model, 'change', this.render);
    },

    render: function() {
        this.$el.html(this.template(this.model.toJSON()));
        this._renderToppingViews(this.model.get('toppings'));

        return this;
    },

    _renderToppingViews: function(toppings) {
        _.each(toppings, this._renderToppingView, this);
    },

    _renderToppingView: function(topping) {
        var view = new CartToppingView({
            model: new ToppingModel(topping)
        });

        this.$('.summary__extra__items').append(view.render().el);
    },

    _editItem: function() {
        var menuItemData = this._getMenuItemData(),
            menuToppings = this._getMenuItemToppings(menuItemData),
            allToppingsWithSelection = this._getAllToppingsWithSelection(this.model.toppings.toJSON(), menuToppings);

        this.model.toppings = new ToppingCollection(allToppingsWithSelection);

        var view = new ToppingsView({
            el: '.modal-dialogs',
            model: new CartItemModel(this.model.toJSON()),
            cartModel: this.cartModel,
            vendorId: this.vendorId,
            productToUpdate: this.model
        });

        view.render(); //render dialog
        $('#choices-toppings-modal').modal(); //show dialog
    },

    _removeItem: function() {
        this.cartModel.getCart(this.vendorId).removeItem(this.model);
    },

    _decreaseQuantity: function() {
        this.cartModel.getCart(this.vendorId).increaseQuantity(this.model, -1);
    },

    _increaseQuantity: function() {
        this.cartModel.getCart(this.vendorId).increaseQuantity(this.model, 1);
    },

    _getAllToppingsWithSelection: function(cartToppings, menuToppings) {
        var menuToppingsClone = _.cloneDeep(menuToppings);
        return _.each(menuToppingsClone, function(menuTopping) {
            _.each(menuTopping.options, function(option) {
                if (_.findWhere(cartToppings, {id: option.id})) {
                    option.selected = true;
                }
            });

            return menuTopping;
        });
    },

    _getMenuItemToppings: function(menuItemData) {
        return menuItemData.product_variations[0].toppings;
    },

    _getMenuItemData: function() {
        var $menuItems = $('.menu__item'),
            variationId = this.model.get('product_variation_id'),
            menuItem;

        menuItem = _.find($menuItems, function(menuItem) {
            var productVariations = $(menuItem).data().object.product_variations,
                productVariation = productVariations ? productVariations[0] : {};

            return productVariation.id === variationId;
        }, this);

        return menuItem ? $(menuItem).data().object : null;
    }
});

var CartView = Backbone.View.extend({
    initialize: function (options) {
        console.log('CartView.initialize ', this.cid);
        _.bindAll(this);

        this.subViews = [];

        this.template = _.template($('#template-cart').html());
        this.templateSubTotal = _.template($('#template-cart-subtotal').html());

        this.vendor_id = this.$el.data().vendor_id;

        this.domObjects = {};
        this.domObjects.$header = options.$header;
        this.domObjects.$menuMain = options.$menuMain;
        this.$window = options.$window;

        // margin of the menu height from the bottom edge of the window
        this.cartBottomMargin = VOLO.configuration.cartBottomMargin;
        this.itemsOverflowingClassName = VOLO.configuration.itemsOverflowingClassName;
        this.fixedCartElementsHeight = null;

        this.initListener();

        // initializing cart sticking behaviour
        this.stickOnTopCart = new StickOnTop({
            $container: this.$el,
            stickOnTopValueGetter: function() {
                return this.domObjects.$header.outerHeight();
            }.bind(this),
            startingPointGetter: function() {
                return this.$el.offset().top;
            }.bind(this),
            endPointGetter: function() {
                return this.domObjects.$menuMain.offset().top + this.domObjects.$menuMain.outerHeight();
            }.bind(this)
        });
    },

    // attaching cart resize also to items scroll to avoid a bug not triggering resize
    // when scrolling page from summary list

    events: {
        'change #order-delivery-time': 'updateDeliveryTime',
        'change #order-delivery-date': 'updateDeliveryTime',
        'scroll .checkout__summary' : '_updateCartHeight'
    },

    unbind: function() {
        // unbinding cart sticking behaviour
        this.stickOnTopCart.remove();
        // unbinding cart height resize behaviour
        this.$window.off('resize', this._updateCartHeight).off('scroll', this._updateCartHeight);

        _.invoke(this.subViews, 'unbind');
        _.invoke(this.subViews, 'remove');
        this.stopListening();
        this.undelegateEvents();
        this.domObjects = {};
    },

    initListener: function () {
        var vendorCart = this.model.getCart(this.vendor_id);
        this.listenTo(vendorCart, 'cart:dirty', this.disableCart, this);
        this.listenTo(vendorCart, 'cart:ready', this.enableCart, this);
        this.listenTo(vendorCart, 'change', this.renderSubTotal);
        this.listenTo(vendorCart, 'change:orderTime', this.renderTimePicker, this);
        this.listenTo(vendorCart.products, 'update', this.renderProducts, this);
        this.listenTo(vendorCart.products, 'update', this._toggleContainerVisibility, this);
        this.listenTo(vendorCart.products, 'update', this._updateCartIcon, this);
        this.listenTo(vendorCart.products, 'change', this._updateCartIcon, this);
        this.listenTo(vendorCart, 'cart:ready', this.renderSubTotal, this);

        // initializing cart height resize behaviour
        this.$window.on('resize', this._updateCartHeight).on('scroll', this._updateCartHeight);
    },

    _updateCartIcon: function() {
        var productsCount = this.model.getCart(this.vendor_id).getProductsCount(),
            $header = this.domObjects.$header,
            productCounter = $header ? $header.find('.header__cart__products__count') : null;

        if (productCounter) {
            productCounter.text(productsCount);
        }
    },

    disableCart: function() {
        this.$el.css({ opacity: 0.5 });
    },

    enableCart: function() {
        this.$el.css({ opacity: 1 });
    },

    render: function() {
        this.$el.html(this.template(this.model.getCart(this.vendor_id).attributes));
        this.renderSubTotal();
        this.renderProducts();
        this.renderTimePicker();

        this._calculateFixedCartElementsHeight();
        // recalculating cart scrolling position
        this.stickOnTopCart.init(this.$('.desktop-cart-container'));
        this._updateCartHeight();
        this._toggleContainerVisibility();
        this._updateCartIcon();

        return this;
    },

    renderSubTotal: function () {
        this.$('.desktop-cart__order__subtotal__container').html(
            this.templateSubTotal(this.model.getCart(this.vendor_id).attributes)
        );

        return this;
    },

    renderProducts: function () {
        console.log('CartView renderProducts ', this.cid);
        _.invoke(this.subViews, 'unbind');
        _.invoke(this.subViews, 'remove');
        this.subViews.length = 0;
        this.model.getCart(this.vendor_id).products.each(this.renderNewItem);
    },

    renderNewItem: function(item) {
        var view = new CartItemView({
            model: item,
            cartModel: this.model,
            vendorId: this.vendor_id
        });
        this.subViews.push(view);

        this.$('.desktop-cart__products').append(view.render().el);

        // recalculating cart scrolling position
        this.stickOnTopCart.updateCoordinates();
        this._calculateFixedCartElementsHeight();
        this._updateCartHeight();
    },

    _calculateFixedCartElementsHeight: function () {
        this.fixedCartElementsHeight = this.$('.desktop-cart__header').outerHeight() + this.$('.desktop-cart__footer').outerHeight();
    },

    _updateCartHeight: function () {
        var $checkoutSummary = this.$('.checkout__summary');

        // if cart is sticking then adjust the product list max height
        if (this.$el.hasClass(this.stickOnTopCart.stickingOnTopClass)) {
            $checkoutSummary.css({
                'max-height': (this.$window.outerHeight() - this.domObjects.$header.outerHeight() - this.fixedCartElementsHeight - this.cartBottomMargin) + 'px'
            });
        // if not remove all adjusting
        } else {
            $checkoutSummary.css({
                'max-height': (this.$window.outerHeight() - (this.$el.offset().top - this.$window.scrollTop()) - this.fixedCartElementsHeight - this.cartBottomMargin) + 'px'
            });
        }

        // adding css styling in case of scrolling of summary list
        if ($checkoutSummary.find('.summary__items').outerHeight() > $checkoutSummary.outerHeight()) {
            $checkoutSummary.addClass(this.itemsOverflowingClassName);
        } else {
            $checkoutSummary.removeClass(this.itemsOverflowingClassName);
        }
    },

    _toggleContainerVisibility: function() {
        var $productsContainer = this.$('.desktop-cart__products'),
            $cartMsg = this.$('.desktop-cart_order__message'),
            cartEmpty = this.model.getCart(this.vendor_id).products.length === 0;

        $cartMsg.toggle(cartEmpty);
        $productsContainer.toggle(!cartEmpty);
    },

    updateDeliveryTime: function() {
        var time = this.$('#order-delivery-time').val().split(':'),
            date = this.$('#order-delivery-date').val().split('-'),
            datetime = new Date(date[0], date[1] - 1, date[2], time[0], time[1]);

        this.model.getCart(this.vendor_id).set('orderTime', datetime);
    },

    renderTimePicker: function() {
        var date = this.model.getCart(this.vendor_id).get('orderTime');

        if (_.isDate(date)) {
            this.$('#order-delivery-date').val(date.toISOString().split('T')[0]);
            this.$('#order-delivery-time').val(date.toTimeString().substring(0, 5));
        }
    }
});
