import { module, test } from 'qunit';
import FullView from '@fleetbase/fleetops-engine/components/tracking-page/full-view';

// A full view without a running app: just the args and locale the time helpers read.
function view(company, locale = 'en-US') {
    const component = Object.create(FullView.prototype);
    component.args = { company };
    Object.defineProperty(component, 'locale', { value: locale });
    return component;
}

module('Unit | Component | tracking-page/full-view | time zone', function () {
    test('the company time zone is used when it is a real zone', function (assert) {
        assert.strictEqual(view({ timezone: 'Asia/Bangkok' }).timeZone, 'Asia/Bangkok');
        assert.strictEqual(view({ timezone: 'Mars/Base' }).timeZone, undefined, 'an unknown zone falls back to the device');
        assert.strictEqual(view({}).timeZone, undefined, 'no zone, the device zone');
        assert.strictEqual(view(undefined).timeZone, undefined, 'no company, the device zone');
    });

    test('times, dates and calendar days follow the company time zone', function (assert) {
        const bangkok = view({ timezone: 'Asia/Bangkok' });
        // 23:30 UTC on 19 Nov is 06:30 on 20 Nov in Bangkok (UTC+7).
        const lateUtc = '2026-11-19T23:30:00Z';

        assert.strictEqual(bangkok.time(lateUtc).replace(/\s/g, ' '), '6:30 AM');
        assert.strictEqual(bangkok.dayKey(lateUtc), '2026-11-20');
        assert.strictEqual(bangkok.date(lateUtc), 'Fri, Nov 20');
        assert.strictEqual(bangkok.time(null), '', 'no time, nothing shown');
        assert.strictEqual(bangkok.date(undefined), '', 'no date, nothing shown');
    });

    test('Thai pages format without throwing', function (assert) {
        const thai = view({ timezone: 'Asia/Bangkok' }, 'th-th');
        assert.ok(thai.time('2026-11-19T05:00:00Z').includes('12:00'));
    });
});
