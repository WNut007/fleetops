import Component from '@glimmer/component';
import { inject as service } from '@ember/service';

/**
 * Routes whose panel opens on the right of the live map, over the order list.
 */
export const PANEL_ROUTES = /^console\.fleet-ops\.operations\.orders\.index\.(details|new|edit)(\.|$)|^operations\.orders\.index\.(details|new|edit)(\.|$)/;

/**
 * The order list renders a card for every active and unassigned order and every driver's jobs:
 * close to 9,000 elements for 40 orders. It stays in the page while another panel covers it
 * (an order's details, a new order), and every data update during page load rendered it again,
 * for several seconds each time. So the list is only rendered while it can be seen.
 */
export function isOrderListVisible(isOpen, routeName) {
    return Boolean(isOpen) && !PANEL_ROUTES.test(String(routeName ?? ''));
}

export default class MapOrderListOverlayComponent extends Component {
    @service('order-list-overlay') overlay;
    @service fleetActions;
    @service orderActions;
    @service hostRouter;

    get isListVisible() {
        return isOrderListVisible(this.overlay.isOpen, this.hostRouter?.currentRouteName);
    }
}
