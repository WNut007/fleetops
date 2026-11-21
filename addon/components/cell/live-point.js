import Component from '@glimmer/component';
import { action, get } from '@ember/object';
import { isArray } from '@ember/array';

/**
 * A "lat lng" table cell that follows its record. ember-ui's table/cell/point reads the point
 * once, when the cell is created, so a location pushed over the socket never showed until the
 * page was reloaded. This one reads it on every render.
 */
export default class CellLivePointComponent extends Component {
    get point() {
        const { row, column } = this.args;
        const path = column?.valuePath;

        return row && path ? get(row, path) : null;
    }

    get display() {
        const point = this.point;

        if (point && typeof point === 'object' && isArray(point.coordinates)) {
            return `${point.coordinates[1]} ${point.coordinates[0]}`;
        }

        return '';
    }

    get isClickable() {
        const column = this.args.column;
        return typeof column?.onClick === 'function' || typeof column?.action === 'function';
    }

    @action onClick(...args) {
        const { row, column } = this.args;

        if (typeof column?.onClick === 'function') {
            column.onClick(row, ...args);
        }

        if (typeof column?.action === 'function') {
            column.action(row);
        }
    }
}
