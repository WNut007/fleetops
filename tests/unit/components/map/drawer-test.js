import { module, test } from 'qunit';
import { setupTest } from 'dummy/tests/helpers';
import { settled } from '@ember/test-helpers';
import MapDrawerComponent from '@fleetbase/fleetops-engine/components/map/drawer';

module('Unit | Component | map/drawer', function (hooks) {
    setupTest(hooks);

    test('changing tab shows the loading state first, then the tab content', async function (assert) {
        const changed = [];
        const drawer = Object.create(MapDrawerComponent.prototype);
        Object.defineProperty(drawer, 'mapDrawer', { value: { setActiveTab: (tab) => changed.push(tab.id) } });

        drawer.changeTab({ id: 'positions' });

        assert.true(drawer.isSwitchingTab, 'the spinner shows while the tab renders');
        assert.deepEqual(changed, ['positions'], 'the tab is switched straight away');

        await settled();

        assert.false(drawer.isSwitchingTab, 'the tab content replaces the spinner');
    });
});
