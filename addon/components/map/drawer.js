import Component from '@glimmer/component';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';
import { isArray } from '@ember/array';

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

    @action setDrawerContext(drawerContextApi) {
        this.mapDrawer.setDrawer(drawerContextApi, this);
    }
}
