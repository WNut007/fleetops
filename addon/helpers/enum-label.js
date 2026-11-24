import Helper from '@ember/component/helper';
import { inject as service } from '@ember/service';
import smartHumanize from '@fleetbase/ember-ui/utils/smart-humanize';

/**
 * Label for a stored enum value in the user's language: `enum.<group>.<value>`, or for the
 * "status" group the shared `ember-ui.status.<value>` badge labels; otherwise the humanized value.
 *
 *   {{enum-label "part-type" @resource.type}}
 */
export function enumLabel(intl, group, value) {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    const raw = String(value);
    const keys = group === 'status' ? [`ember-ui.status.${raw.toLowerCase().replace(/[\s-]+/g, '_')}`] : [`enum.${group}.${raw}`];
    for (const key of keys) {
        if (intl?.exists?.(key)) {
            return intl.t(key);
        }
    }

    return smartHumanize(raw);
}

export default class EnumLabelHelper extends Helper {
    @service intl;

    compute([group, value]) {
        return enumLabel(this.intl, group, value);
    }
}
