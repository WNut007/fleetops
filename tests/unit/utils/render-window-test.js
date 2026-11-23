import { module, test } from 'qunit';
import { renderWindow, hiddenRowCount, isNearScrollEnd } from '@fleetbase/fleetops-engine/utils/render-window';

module('Unit | Utility | render-window', function () {
    test('renderWindow keeps short lists and cuts long ones', function (assert) {
        const rows = [1, 2, 3];
        assert.strictEqual(renderWindow(rows, 5), rows);
        assert.deepEqual(renderWindow([1, 2, 3, 4, 5, 6], 4), [1, 2, 3, 4]);
        assert.deepEqual(renderWindow(null, 4), []);
        assert.deepEqual(renderWindow(new Set([1, 2, 3]), 2), [1, 2]);
    });

    test('hiddenRowCount never goes negative', function (assert) {
        assert.strictEqual(hiddenRowCount([1, 2, 3], 2), 1);
        assert.strictEqual(hiddenRowCount([1], 2), 0);
        assert.strictEqual(hiddenRowCount(undefined, 2), 0);
    });

    test('isNearScrollEnd checks the distance to the end', function (assert) {
        assert.true(isNearScrollEnd({ scrollTop: 800, clientHeight: 200, scrollHeight: 1100 }));
        assert.false(isNearScrollEnd({ scrollTop: 0, clientHeight: 200, scrollHeight: 1100 }));
        assert.false(isNearScrollEnd(null));
    });
});
