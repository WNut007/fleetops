import { module, test } from 'qunit';
import { isOrderListVisible } from '@fleetbase/fleetops-engine/components/map/order-list-overlay';

module('Unit | Component | map/order-list-overlay visibility', function () {
    test('the list renders only while open and not covered by an order panel', function (assert) {
        assert.true(isOrderListVisible(true, 'console.fleet-ops.operations.orders.index.index'));
        assert.false(isOrderListVisible(true, 'console.fleet-ops.operations.orders.index.details.virtual'), 'order details cover it');
        assert.false(isOrderListVisible(true, 'console.fleet-ops.operations.orders.index.new'), 'a new order covers it');
        assert.false(isOrderListVisible(false, 'console.fleet-ops.operations.orders.index.index'), 'closed');
        assert.true(isOrderListVisible(true, undefined));
    });
});
