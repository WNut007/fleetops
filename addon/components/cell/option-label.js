import Component from '@glimmer/component';
import { inject as service } from '@ember/service';
import smartHumanize from '@fleetbase/ember-ui/utils/smart-humanize';
import { getFleetOpsOptionLabel } from '../../helpers/get-fleet-ops-option-label';

/**
 * A table cell for stored enum values (categories, types, priorities): shows the option's
 * label in the user's language instead of the humanized English value.
 *
 * Column options: `optionsKey` (a fleet-ops-options list) and/or `translationPrefix`
 * (a key prefix the dasherized value is appended to).
 */
export function optionLabel(intl, column, value) {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    if (column?.optionsKey) {
        const label = getFleetOpsOptionLabel(column.optionsKey, value, intl);
        if (label) {
            return label;
        }
    }

    if (column?.translationPrefix && intl) {
        const key = `${column.translationPrefix}${String(value).replace(/_/g, '-')}`;
        if (intl.exists(key)) {
            return intl.t(key);
        }
    }

    return smartHumanize(String(value));
}

export default class CellOptionLabelComponent extends Component {
    @service intl;

    get label() {
        return optionLabel(this.intl, this.args.column, this.args.value);
    }
}
