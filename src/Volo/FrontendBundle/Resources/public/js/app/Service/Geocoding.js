var GeocodingService = function(locale) {
    this.autocomplete = null;
    this._listeners = [];
    this.locale = locale;
    this.defaultTypes = ['(regions)'];
};

_.extend(GeocodingService.prototype, Backbone.Events, {
    init: function ($input, config) {
        _.bindAll(this);
        this.autocomplete = new google.maps.places.Autocomplete(
            $input[0],
            {
                types: config || this.defaultTypes,
                componentRestrictions: {
                    country: this.locale
                }
            }
        );

        this._initListeners($input);
    },

    _initListeners: function ($input) {
        var self = this;
        this._listeners.push(google.maps.event.addDomListener($input[0], 'keydown', this._keyDown));

        this._listeners.push(this.autocomplete.addListener('place_changed', function () {
            self.trigger('autocomplete:place_changed');
        }));

        this._listeners.push(google.maps.event.addDomListener($input[0], 'blur', this._blur));
    },

    _keyDown: function (e) {
        if (e.keyCode === 13) { //enter
            this.trigger('autocomplete:submit_pressed');
            e.preventDefault();
        } else if (e.keyCode === 9) { //tab
            this.trigger('autocomplete:tab_pressed');
        }
    },

    _blur: function () {
        google.maps.event.trigger(this, 'keydown', {
            keyCode: 9
        });

        this.trigger('autocomplete:place_changed');
    },

    removeListeners: function ($input) {
        console.log('removeListener ', this.autocomplete);
        google.maps.event.clearListeners($input[0]);
        //google.maps.event.clearListeners(this.autocomplete);
        _.each(this._listeners, function(listener) {
            google.maps.event.removeListener(listener);
        }, this);
    },

    /**
     * @returns $.Deferred
     */
    getLocation: function ($input) {
        var deferred = $.Deferred();

        this._getSearchPlace($input, this.autocomplete)
            .then(this._getLocationMeta, deferred.reject)
            .done(deferred.resolve);

        return deferred;
    },

    _getSearchPlace: function ($input, autocomplete) {
        var deferred = $.Deferred(),
            place = autocomplete.getPlace();

        if (!place || !place.place_id) {
            this._selectFirstResult().done(deferred.resolve).fail(deferred.reject);
        } else {
            deferred.resolve(place);
        }

        return deferred;
    },

    _selectFirstResult: function () {
        var deferred = $.Deferred(),
            firstResult = $(".pac-container .pac-item:first").text();

        var geocoder = new google.maps.Geocoder();

        geocoder.geocode({"address": firstResult}, function (results, status) {
            if (status == google.maps.GeocoderStatus.OK) {
                $(".pac-container .pac-item:first").addClass("pac-selected");
                $(".pac-container").css("display", "none");

                results[0].place = {
                    geometry: results[0].geometry
                };

                deferred.resolve(results[0]);
            } else {
                deferred.reject();
            }
        });

        return deferred;
    },

    _getLocationMeta: function (place) {
        var meta = this._getLocationMetaFromGeocode(place),
            deferred = $.Deferred();

        if (this._hasPostalCode(meta)) {
            meta.postcodeGuessed = false;
            deferred.resolve(meta);
        } else {
            this._enhanceLocationWithGeocode(meta).done(deferred.resolve);
        }

        return deferred;
    },

    _getLocationMetaFromGeocode: function (place) {
        return {
            formattedAddress: place.formatted_address,
            city: this._findLocalityInAddressComponents(place.address_components),
            postalCode: {
                value: this._findPostalCodeInAddressComponents(place.address_components),
                isReversed: false
            },
            street: this._findStreetNameInAddressComponents(place.address_components),
            building: this._findBuildingNumberInAddressComponents(place.address_components),
            lat: place.geometry.location.lat(),
            lng: place.geometry.location.lng()
        };
    },

    _hasPostalCode: function (meta) {
        return !!meta.postalCode.value;
    },

    _enhanceLocationWithGeocode: function (meta) {
        var geocoder = new google.maps.Geocoder(),
            deferred = $.Deferred(),
            self = this;

        geocoder.geocode({'latLng': new google.maps.LatLng(meta.lat, meta.lng)},
            function (results, status) {
                if (status === google.maps.GeocoderStatus.OK) {
                    meta.postalCode.value = self._findPostalCodeInGeocodeResults(results);
                    meta.postalCode.isReversed = true;
                }

                meta.postcodeGuessed = true;
                deferred.resolve(meta);
            }
        );

        return deferred;
    },

    _findPostalCodeInGeocodeResults: function (results) {
        var postalCode = null;
        _.each(results, function (result) {
            if (postalCode === null) {
                postalCode = this._findPostalCodeInAddressComponents(result.address_components);
            }
        }, this);

        return postalCode;
    },

    _findPostalCodeInAddressComponents: function (addressComponents) {
        return _.pluck(_.where(addressComponents, {'types': ['postal_code']}), 'short_name')[0];
    },

    _findLocalityInAddressComponents: function (addressComponents) {
        return _.pluck(_.where(addressComponents, {'types': ['locality']}), 'long_name')[0];
    },

    _findStreetNameInAddressComponents: function (addressComponents) {
        return _.chain(addressComponents)
            .where({'types': ['route']})
            .pluck('long_name')
            .first()
            .thru(function(first) {
                return first || '';
            })
            .value();
    },

    _findBuildingNumberInAddressComponents: function (addressComponents) {
        return _.chain(addressComponents)
            .where({'types': ['street_number']})
            .pluck('long_name')
            .first()
            .thru(function(first) {
                return first || '';
            })
            .value();
    }
});
