VOLO.FloodBannerView = Backbone.View.extend({
    initialize: function(options) {
        _.bindAll(this);

        this.$body = options.$body;
        this.locationModel = options.locationModel;
    },

    events: {
        'click .flood-banner__close-button': 'hide'
    },

    show: function() {
        this.$body.addClass('show-flood-banner');
        this.$body.addClass('show-banner');

        //resize called because sticky parts of site needs to be updated
        this.$body.resize();
    },

    hide: function() {
        this.$body.removeClass('show-flood-banner');
        this.model.set('hiddenByUser', true);

        this.trigger('flood-banner:hide');
    },

    shouldBeDisplayed: function() {
        var deferred = $.Deferred(),
            lat = this.locationModel.get('latitude'),
            lng = this.locationModel.get('longitude');


        if (this.model.get('hiddenByUser')) { //user hide msg, it should stay hidden
            deferred.resolve(false);
        } else if (!this.$body.hasClass('menu') && !this.$body.hasClass('restaurants')) { //only on vendors/menu pages
            deferred.resolve(false);
        } else if (!lat || !lng) { //we need lat/lng, if we don't have it, don't show it
            deferred.resolve(false);
        } else { //else ask server
            var xhr = this._getEvents(lat, lng);

            xhr.then(function(response) {
                if (response.items && response.items.length) {
                    this.$('.flood-banner__content').text(response.items[0]);

                    deferred.resolve(true);
                } else {
                    deferred.resolve(false);
                }
            }.bind(this), deferred.reject);
        }

        return deferred;
    },

    _getEvents: function(lat, lng) {
        return $.ajax({
            method: 'GET',
            url: Routing.generate('api_events_get', {latitude: lat, longitude: lng})
        });
    },

    unbind: function() {
        this.stopListening();
        this.undelegateEvents();
    }
});