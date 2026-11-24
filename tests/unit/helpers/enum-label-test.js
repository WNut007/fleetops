import { module, test } from 'qunit';
import { enumLabel } from '@fleetbase/fleetops-engine/helpers/enum-label';

const intl = {
    keys: { 'enum.part-type.filter': 'ไส้กรอง', 'ember-ui.status.in_stock': 'มีสต็อก' },
    exists(key) {
        return key in this.keys;
    },
    t(key) {
        return this.keys[key];
    },
};

module('Unit | Helper | enum-label', function () {
    test('translates enum values and statuses, humanizes the rest', function (assert) {
        assert.strictEqual(enumLabel(intl, 'part-type', 'filter'), 'ไส้กรอง');
        assert.strictEqual(enumLabel(intl, 'status', 'in_stock'), 'มีสต็อก');
        assert.strictEqual(enumLabel(intl, 'part-type', 'brake_pad'), 'Brake Pad');
        assert.strictEqual(enumLabel(intl, 'part-type', null), '');
    });
});
