import { module, test } from 'qunit';
import escapeHtml from '@fleetbase/fleetops-engine/utils/escape-html';

module('Unit | Utility | escape-html', function () {
    test('record text stays text inside calendar HTML', function (assert) {
        assert.strictEqual(escapeHtml('กิตติ บุญมา'), 'กิตติ บุญมา', 'Thai names are unchanged');
        assert.strictEqual(escapeHtml('<img src=x onerror=alert(1)>'), '&lt;img src=x onerror=alert(1)&gt;');
        assert.strictEqual(escapeHtml(`Tom & "Jerry's"`), 'Tom &amp; &quot;Jerry&#39;s&quot;');
        assert.strictEqual(escapeHtml(null), '');
        assert.strictEqual(escapeHtml(undefined), '');
        assert.strictEqual(escapeHtml(3), '3');
    });
});
