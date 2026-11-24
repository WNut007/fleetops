import { module, test } from 'qunit';
import { optionLabel } from '@fleetbase/fleetops-engine/components/cell/option-label';

const intl = {
    keys: { 'prio.high': 'สูง' },
    exists(key) {
        return key in this.keys;
    },
    t(key) {
        return this.keys[key];
    },
};

module('Unit | Component | cell/option-label', function () {
    test('uses the options list label', function (assert) {
        assert.strictEqual(optionLabel(null, { optionsKey: 'workOrderCategories' }, 'preventive_maintenance'), 'Preventive Maintenance (PM)');
    });

    test('uses a translation prefix', function (assert) {
        assert.strictEqual(optionLabel(intl, { translationPrefix: 'prio.' }, 'high'), 'สูง');
    });

    test('falls back to the humanized value, and a dash for nothing', function (assert) {
        assert.strictEqual(optionLabel(intl, {}, 'in_progress'), 'In Progress');
        assert.strictEqual(optionLabel(intl, {}, null), '-');
    });
});
