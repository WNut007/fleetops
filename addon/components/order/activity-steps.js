import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';

const VISIBLE = 3;

/**
 * The order's tracking statuses, three at a time, with arrows to page through them.
 *
 * Replaces ember-ui's Timeline, which (a) copied @activity once, so statuses recorded after the
 * panel opened never showed, (b) both sliced the visible window and translated the container
 * by a third per step, so each arrow press moved twice as far and the last statuses were never
 * reachable (pages went blank), and (c) opened on the oldest three. This one reads @activity on
 * every render, moves one status per press, and opens on (and keeps following) the newest
 * three until the user pages back.
 */
export default class OrderActivityStepsComponent extends Component {
    /** First shown index once the user has paged; null follows the newest statuses. */
    @tracked offset = null;

    get activity() {
        const activity = this.args.activity;

        if (!activity) {
            return [];
        }

        return Array.isArray(activity) ? activity : Array.from(activity);
    }

    get lastStart() {
        return Math.max(0, this.activity.length - VISIBLE);
    }

    get start() {
        return this.offset === null ? this.lastStart : Math.min(this.offset, this.lastStart);
    }

    get visible() {
        const activeStatus = typeof this.args.activeStatus === 'string' ? this.args.activeStatus.toLowerCase() : null;

        return this.activity.slice(this.start, this.start + VISIBLE).map((status) => ({
            status,
            isActive: typeof status?.isActive === 'boolean' ? status.isActive : activeStatus !== null && typeof status?.code === 'string' && status.code.toLowerCase() === activeStatus,
        }));
    }

    get canGoBack() {
        return this.start > 0;
    }

    get canGoForward() {
        return this.start < this.lastStart;
    }

    @action previous() {
        if (this.canGoBack) {
            this.offset = this.start - 1;
        }
    }

    @action next() {
        if (this.canGoForward) {
            const start = this.start + 1;
            // Back at the newest page: follow new statuses again.
            this.offset = start >= this.lastStart ? null : start;
        }
    }
}
