import { module, test } from 'qunit';
import { loadActiveOrders } from '@fleetbase/fleetops-engine/routes/operations/scheduler/index';

function fakeStore(total) {
    const calls = [];
    return {
        calls,
        async query(_model, params) {
            calls.push(params);
            const start = (params.page - 1) * params.limit;
            const records = Array.from({ length: Math.max(0, Math.min(params.limit, total - start)) }, (_, i) => ({ id: start + i }));
            records.meta = { last_page: Math.ceil(total / params.limit) };
            return records;
        },
    };
}

module('Unit | Route | operations/scheduler | loadActiveOrders', function () {
    test('pages through the orders, newest first', async function (assert) {
        const store = fakeStore(230);
        const orders = await loadActiveOrders(store, { perPage: 100, maxPages: 5 });

        assert.strictEqual(orders.length, 230);
        assert.deepEqual(
            store.calls.map((c) => c.page),
            [1, 2, 3]
        );
        assert.true(store.calls.every((c) => c.sort === '-created_at'));
    });

    test('stops at the page cap', async function (assert) {
        const store = fakeStore(1000);
        const orders = await loadActiveOrders(store, { perPage: 100, maxPages: 2 });

        assert.strictEqual(orders.length, 200);
    });
});
