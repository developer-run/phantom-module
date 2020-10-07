/**
 *
 */
(function ($, window) {

    // Leak Phantom namespace
    window.Phantom = {
        config: {
            timeout: 500,
            captureLink: '',
        },

        selectorAnimationEnd: 'webkitAnimationEnd mozAnimationEnd MSAnimationEnd oanimationend animationend',
        selectorApp: '.main-wrapper',

        captures: null,
        capturesCount: 0,
        captureCounter: 0,
        // captureLink: '',

        init: function (config) {
            $.extend(this.config, config);

            this.captures = $('[data-capture-route]');
            this.capturesCount = this.captures.length;
            this.captureCounter = 0;

            if (this.capturesCount > 0) {
                this.drawOverlay(this.captures);
                this.capture(this.captures[this.captureCounter]);
            }
        },

        drawOverlay: function (el) {
            var overlay = $(el).parent();
            var icon = $("<i class='fa fa-refresh fa-spin'></i>");
            $(overlay).addClass('overlay-loading').append(icon);
        },

        removeOverlay: function (el) {
            var overlay = $(el).parent();
            var icon = $(overlay).find('i');

            $(icon).remove();
            $(overlay).removeClass('overlay-loading');
        },

        capture: function (el) {
            var self = this;
            const id = $(el).data('capture-route');
            const params = $(el).data('capture-params');

            if (id) {
                if (!self.config['captureLink']) {
                    console.warn("set captureLink first!");
                    return;
                }

                $.ajax({
                    url: self.config['captureLink'],
                    data: {'id': id, 'params': params},
                    start: function () {
                        // self.drawOverlay(el);
                    },
                    success: function (response) {

                        if (response['result'] === true) {
                            self.removeOverlay(el);

                            $(el).attr('src', '/' + response['image']['data_dir'] + "/" + response['image']['identifier'])
                            self.captureCounter++;
                        }

                        if (self.captureCounter < self.capturesCount) {
                            setTimeout(function () {
                                self.capture(self.captures[self.captureCounter]);
                            }, self.config['timeout']);
                        }
                    }
                });

            } else {
                console.warn("capture: route ID not found");
            }
        },

    };

/*
    $(function () {
        Phantom.init();
    });
*/

})(jQuery, window);
