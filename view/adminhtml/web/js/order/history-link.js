define([], function () {
    'use strict';

    return function (config, element) {
        element.addEventListener('click', function (event) {
            var tab = document.getElementById(config.tab);

            if (!tab) {
                return;
            }
            event.preventDefault();
            tab.click();
            tab.scrollIntoView({block: 'nearest'});
        });
    };
});
