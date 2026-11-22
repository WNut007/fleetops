import Component from '@glimmer/component';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';
import { isArray } from '@ember/array';
import { tracked } from '@glimmer/tracking';
import { later } from '@ember/runloop';

export default class MapDrawerComponent extends Component {
    @service intl;
    @service mapDrawer;
    @service universe;
    @service('universe/menu-service') menuService;

    get tabs() {
        const registeredTabs = this.menuService.getMenuItems('fleet-ops:component:map:drawer');
        return [
            this.universe._createMenuItem(this.intl.t('fleetops-ui.js.components.map.drawer.vehicles'), null, { icon: 'car', component: 'map/drawer/vehicle-listing' }),
            this.universe._createMenuItem(this.intl.t('fleetops-ui.js.components.map.drawer.drivers'), null, { icon: 'id-card', component: 'map/drawer/driver-listing' }),
            this.universe._createMenuItem(this.intl.t('fleetops-ui.js.components.map.drawer.places'), null, { icon: 'building', component: 'map/drawer/place-listing' }),
            this.universe._createMenuItem(this.intl.t('fleetops-ui.js.components.map.drawer.positions'), null, { icon: 'map-marker', component: 'map/drawer/position-listing' }),
            this.universe._createMenuItem(this.intl.t('fleetops-ui.js.components.map.drawer.geofences'), null, { icon: 'map-pin', component: 'map/drawer/geofence-event-listing' }),
            this.universe._createMenuItem(this.intl.t('fleetops-ui.js.components.map.drawer.events'), null, { icon: 'stream', component: 'map/drawer/device-event-listing' }),
            ...(isArray(registeredTabs) ? registeredTabs : []),
        ];
    }

    /** True while a newly chosen tab waits for the browser to paint its loading state. */
    @tracked isSwitchingTab = false;

    /**
     * Switch tabs, showing a spinner first: a listing can take a moment to render, and
     * without this the old tab stayed on screen with no sign that anything was happening.
     */
    @action changeTab(tab) {
        this.isSwitchingTab = true;
        this.mapDrawer.setActiveTab(tab);
        // Give the browser a frame to paint the spinner before the listing renders.
        later(
            this,
            () => {
                if (!this.isDestroying && !this.isDestroyed) {
                    this.isSwitchingTab = false;
                }
            },
            30
        );
    }

    @action setDrawerContext(drawerContextApi) {
        this.mapDrawer.setDrawer(drawerContextApi, this);
    }
}
