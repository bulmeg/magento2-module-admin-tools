define([
    'Magento_Ui/js/grid/columns/select'
], function (Column) {
    'use strict';

    var COLOR_PATTERN = /^[a-z]+$/;

    return Column.extend({
        defaults: {
            bodyTmpl: 'Bulmeg_AdminTools/grid/cells/status',
            statusColors: {}
        },

        getStatusClass: function (row) {
            var status = row[this.index],
                color;

            if (!this.statusColors || !Object.prototype.hasOwnProperty.call(this.statusColors, status)) {
                return '';
            }
            color = String(this.statusColors[status]);

            return COLOR_PATTERN.test(color) ? 'bulmeg-status bulmeg-status-' + color : '';
        }
    });
});
