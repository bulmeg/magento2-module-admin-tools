define([], function () {
    'use strict';

    return function (config, element) {
        element.addEventListener('click', function (event) {
            var tab = document.querySelector('a.tab-item-link[href="#' + config.tab + '_content"]');

            if (!tab) {
                return;
            }
            event.preventDefault();
            tab.click();
            tab.scrollIntoView({block: 'nearest'});
        });
    };
});
