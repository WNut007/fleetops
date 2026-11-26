import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';
import { debug } from '@ember/debug';
import { task } from 'ember-concurrency';

export default class MapToolbarComponent extends Component {
    @service mapManager;
    @service orderListOverlay;
    @service mapDrawer;
    @service globalSearch;
    @service fetch;
    @tracked activeOrderCount = 0;

    constructor() {
        super(...arguments);
        this.getActiveOrderCount.perform();
    }

    @action calculatePosition(trigger) {
        let { width } = trigger.getBoundingClientRect();

        let style = {
            marginTop: '0px',
            left: `${width + 13}px`,
            top: '0px',
        };

        return { style };
    }

    @action zoomIn() {
        return this.mapManager.zoomIn?.();
    }

    @action zoomOut() {
        return this.mapManager.zoomOut?.();
    }

    /**
     * The count as it fits the badge: up to 999 as is, then 1.2k / 12k (the full number is in its title).
     */
    get activeOrderBadge() {
        const count = Number(this.displayedActiveOrderCount) || 0;
        if (count < 1000) {
            return count;
        }

        const thousands = count / 1000;
        return `${thousands < 10 ? Math.floor(thousands * 10) / 10 : Math.floor(thousands)}k`;
    }

    get displayedActiveOrderCount() {
        if (this.orderListOverlay.isOpen && this.orderListOverlay.loaded) {
            return this.orderListOverlay.activeOrdersCount;
        }

        return this.activeOrderCount;
    }

    @task *getActiveOrderCount() {
        try {
            const count = yield this.fetch.get('fleet-ops/metrics', { discover: ['active_live_orders'] });
            this.activeOrderCount = count.active_live_orders;
            return count;
        } catch (err) {
            debug('Failed to get active order count: ' + err.message);
        }
    }
}
