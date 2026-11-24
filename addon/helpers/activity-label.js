import Helper from '@ember/component/helper';
import { inject as service } from '@ember/service';

/**
 * English texts the built-in order config templates give their activities. A tracking status
 * that still carries one of these is shown in the user's language (`activity.<code>.status` /
 * `.details`); anything an organization wrote itself (e.g. "ถึงท่าเรือ") is shown as written.
 */
const TEMPLATE_TEXTS = {
    created: ['Order Created', 'New order was created.', 'Trip requested', 'The customer requested a trip.'],
    dispatched: ['Order Dispatched', 'Order has been dispatched.', 'Finding a driver', 'Looking for a driver nearby.'],
    started: ['Order Started', 'Order has been started', 'Driver on the way', 'The driver accepted and is on the way to the pickup.'],
    enroute: ['Driver Enroute', 'Driver is en-route.'],
    arrived: ['Driver has arrived', 'The driver is waiting at the pickup.'],
    in_progress: ['Trip in progress', 'The passenger is on board.'],
    completed: ['Order Completed', 'Order has been completed.', 'Trip completed', 'The passenger was dropped off.'],
    canceled: ['Order canceled', 'Order Canceled', 'Order was canceled'],
};

export function activityLabel(intl, field, entry) {
    const text = entry?.[field];
    const code = String(entry?.code ?? '').toLowerCase();
    if (!text || !code || !TEMPLATE_TEXTS[code]?.includes(String(text).trim())) {
        return text;
    }
    const key = `activity-template.${code}.${field}`;
    return intl?.exists?.(key) ? intl.t(key) : text;
}

export default class ActivityLabelHelper extends Helper {
    @service intl;

    /** {{activity-label "status" trackingStatus}} / {{activity-label "details" trackingStatus}} */
    compute([field, entry]) {
        return activityLabel(this.intl, field, entry);
    }
}
