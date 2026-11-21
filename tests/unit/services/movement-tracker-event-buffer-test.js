import { module, test } from 'qunit';
import { EventBuffer, serverTime } from '@fleetbase/fleetops-engine/services/movement-tracker';

// A driver record stand-in: records what the buffer writes to it.
function driver() {
    return {
        id: 'driver_1',
        writes: [],
        setProperties(props) {
            this.writes.push(props);
            Object.assign(this, props);
        },
    };
}

const point = (lng, lat) => ({ type: 'Point', coordinates: [lng, lat] });
const noMarkers = { hasMarker: () => false };

module('Unit | Service | movement-tracker | EventBuffer', function () {
    test('a location from the driver app updates the record, not only the marker', async function (assert) {
        const model = driver();
        const buffer = new EventBuffer(model, { mapManager: noMarkers });

        buffer.add({
            event: 'driver.location_changed',
            created_at: '2026-11-20T01:00:00Z',
            data: { id: 'driver_1', location: point(100.5, 13.7), speed: 10, heading: 90, additionalData: [] },
        });
        await buffer.process.perform();

        assert.deepEqual(model.location, point(100.5, 13.7));
        assert.strictEqual(model.speed, 10);
        assert.strictEqual(model.updated_at.toISOString(), '2026-11-20T01:00:00.000Z', 'last seen moves to when the location was sent');
    });

    test('an older location never replaces a newer one', async function (assert) {
        const model = driver();
        const buffer = new EventBuffer(model, { mapManager: noMarkers });

        buffer.add({ event: 'driver.location_changed', created_at: '2026-11-20T01:00:05Z', data: { location: point(100.6, 13.8) } });
        await buffer.process.perform();
        buffer.add({ event: 'driver.location_changed', created_at: '2026-11-20T01:00:00Z', data: { location: point(100.5, 13.7) } });
        await buffer.process.perform();

        assert.deepEqual(model.location, point(100.6, 13.8));
        assert.strictEqual(model.writes.length, 1);
    });

    test('telematics positions still go by their own timestamp and leave last seen alone', async function (assert) {
        const model = driver();
        const buffer = new EventBuffer(model, { mapManager: noMarkers });

        buffer.add({ event: 'driver.location_changed', created_at: '2026-11-20T01:00:00Z', data: { location: point(100.5, 13.7), additionalData: { position_at: '2026-11-20T00:59:58Z' } } });
        await buffer.process.perform();

        assert.deepEqual(model.location, point(100.5, 13.7));
        assert.strictEqual(model.updated_at, undefined);
    });

    test('a server time stamp without a zone is read as UTC', async function (assert) {
        const model = driver();
        const buffer = new EventBuffer(model, { mapManager: noMarkers });

        buffer.add({ event: 'driver.location_changed', created_at: '2026-11-21 04:44:12', data: { location: point(100.7381, 13.7262) } });
        await buffer.process.perform();

        assert.strictEqual(model.updated_at.toISOString(), '2026-11-21T04:44:12.000Z', 'not 7 hours off in Bangkok');
    });
});

module('Unit | Service | movement-tracker | serverTime', function () {
    test('only zone-less stamps are marked UTC', function (assert) {
        assert.strictEqual(serverTime('2026-11-21 04:44:12'), '2026-11-21T04:44:12Z');
        assert.strictEqual(serverTime('2026-11-21T04:44:12'), '2026-11-21T04:44:12Z');
        assert.strictEqual(serverTime('2026-11-21T04:44:12.000000Z'), '2026-11-21T04:44:12.000000Z');
        assert.strictEqual(serverTime('2026-11-21T11:44:12+07:00'), '2026-11-21T11:44:12+07:00');
        assert.strictEqual(serverTime(undefined), undefined);
    });
});
