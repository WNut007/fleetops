import { module, test } from 'qunit';
import { setupTest } from 'dummy/tests/helpers';
import LeafletLiveMapComponent from '@fleetbase/fleetops-engine/components/map/leaflet-live-map';

module('Unit | Component | map/leaflet-live-map', function (hooks) {
    setupTest(hooks);

    function makeMap() {
        const closed = [];
        let next = 0;
        const map = Object.create(LeafletLiveMapComponent.prototype);
        map.mapPanelId = null;
        Object.defineProperty(map, 'resourceContextPanel', { value: { close: async (id) => closed.push(id) } });
        Object.defineProperty(map, 'mapManager', { value: {} });
        const actions = { opened: [], panel: { view: async (resource, options) => (actions.opened.push([resource, options.size]), `panel-${++next}`) } };

        return { map, closed, actions };
    }

    test('a marker click replaces the panel the previous marker click opened', async function (assert) {
        const { map, closed, actions } = makeMap();

        assert.strictEqual(await map.openMapPanel(actions, 'vehicle-1'), 'panel-1');
        assert.deepEqual(closed, [], 'nothing to replace on the first click');

        assert.strictEqual(await map.openMapPanel(actions, 'vehicle-1'), 'panel-2');
        assert.strictEqual(await map.openMapPanel(actions, 'driver-2'), 'panel-3');

        assert.deepEqual(closed, ['panel-1', 'panel-2'], 'each click closes the previous map panel');
        assert.deepEqual(
            actions.opened,
            [
                ['vehicle-1', 'xs'],
                ['vehicle-1', 'xs'],
                ['driver-2', 'xs'],
            ],
            'each click opens the clicked resource'
        );
        assert.strictEqual(map.mapPanelId, 'panel-3');
    });

    test('marker click actions route to the matching resource panel', async function (assert) {
        const { map, actions } = makeMap();
        Object.defineProperty(map, 'driverActions', { value: actions });
        Object.defineProperty(map, 'vehicleActions', { value: actions });
        Object.defineProperty(map, 'placeActions', { value: actions });

        await map.onDriverClicked('d');
        await map.onVehicleClicked('v');
        await map.onPlaceClicked('p');

        assert.deepEqual(
            actions.opened.map(([resource]) => resource),
            ['d', 'v', 'p']
        );
    });
});
