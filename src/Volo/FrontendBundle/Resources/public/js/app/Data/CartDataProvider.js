var CartDataProvider = function() {
    var calculateCart = function(data) {
        var deferred = $.Deferred();

        var requestSettings = {
            url: Routing.generate('cart_calculate'),
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(_prepareCalculateRequest(data))
        };
        $.ajax(requestSettings).done(function(res) {
            deferred.resolve(res);
        });

        return deferred;
    };

    var _prepareCalculateRequest = function(data) {
        return {
            "expedition_type": "delivery",
            "vouchers": [],
            "products": data.products.map(function(item) {
                return {
                    "vendor_id": data.vendor_id,
                    "variation_id": item.product_variation_id,
                    "quantity": item.quantity,
                    "groupOrderUserName": "",
                    "toppings": _.cloneDeep(item.toppings),
                    "choices": _.cloneDeep(item.choices)
                };
            }),
            "location": data.location,
            "orderTime": "2015-05-12T07:31:20.795Z",
            "paymentTypeId": 0,
            "activeLanguage": 1,
            "groupCode": "",
            "groupOrderVersion": 0,
            "orderComment": "",
            "vendorPickupLocationId": 0,
            "deliveryTimeMode": ""
        };
    };

    return {
        calculateCart: calculateCart
    };
};