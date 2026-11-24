import Component from '@glimmer/component';
import { get } from '@ember/object';
import { inject as service } from '@ember/service';
import { dasherize } from '@ember/string';

export default class SensorPanelHeaderComponent extends Component {
    get resource() {
        return this.args.resource;
    }

    get name() {
        return (
            get(this.resource, 'displayName') ??
            get(this.resource, 'display_name') ??
            get(this.resource, 'name') ??
            get(this.resource, 'serial_number') ??
            get(this.resource, 'imei') ??
            get(this.resource, 'public_id') ??
            '-'
        );
    }

    get status() {
        return get(this.resource, 'status') ?? (get(this.resource, 'is_active') ? 'active' : 'inactive');
    }

    @service intl;

    get thresholdStatus() {
        return get(this.resource, 'threshold_status');
    }

    get thresholdLabel() {
        const status = this.thresholdStatus;
        const key = `fleetops-ui.components.sensor.details.${dasherize(String(status ?? ''))}`;
        return status && this.intl.exists(key) ? this.intl.t(key) : String(status ?? '').replaceAll('_', ' ');
    }

    get type() {
        return get(this.resource, 'type');
    }

    get identifier() {
        return get(this.resource, 'serial_number') ?? get(this.resource, 'imei') ?? get(this.resource, 'internal_id') ?? get(this.resource, 'public_id');
    }

    get reading() {
        const value = get(this.resource, 'last_value');
        const unit = get(this.resource, 'unit');

        if (value === null || value === undefined || value === '') {
            return null;
        }

        return unit ? `${value} ${unit}` : String(value);
    }

    get deviceName() {
        return (
            get(this.resource, 'device.displayName') ??
            get(this.resource, 'device.display_name') ??
            get(this.resource, 'device.name') ??
            get(this.resource, 'device_name') ??
            get(this.resource, 'device_uuid')
        );
    }

    get lastReadingAt() {
        return get(this.resource, 'last_reading_at') ?? get(this.resource, 'lastReadingAt');
    }
}
