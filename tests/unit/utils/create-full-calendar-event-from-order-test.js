import { module, test } from 'qunit';
import { createOrderEventDescription } from '@fleetbase/fleetops-engine/utils/create-full-calendar-event-from-order';

module('Unit | Utility | create-full-calendar-event-from-order', function () {
    test('the description names the dropoff, not the pickup', function (assert) {
        const order = { scheduledAtTime: '10:30', pickupName: 'Warehouse A', dropoffName: 'Shop B' };

        assert.true(createOrderEventDescription(order).includes('Shop B'));
        assert.false(createOrderEventDescription(order).includes('Warehouse A'));
    });

    test('falls back to the pickup when there is no dropoff', function (assert) {
        assert.true(createOrderEventDescription({ scheduledAtTime: '10:30', pickupName: 'Warehouse A' }).includes('Warehouse A'));
    });
});
