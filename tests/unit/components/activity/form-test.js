import { module, test } from 'qunit';
import { statusFromCode } from '@fleetbase/fleetops-engine/components/activity/form';

module('Unit | Component | activity/form | statusFromCode', function () {
    test('a code becomes the status suggested for it', function (assert) {
        assert.strictEqual(statusFromCode('arrived_port'), 'Arrived Port');
        assert.strictEqual(statusFromCode('customs_cleared'), 'Customs Cleared');
        assert.strictEqual(statusFromCode('dispatched'), 'Dispatched');
        assert.strictEqual(statusFromCode(''), '');
        assert.strictEqual(statusFromCode(null), '');
        assert.strictEqual(statusFromCode(undefined), '');
    });
});
