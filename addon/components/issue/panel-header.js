import { inject as service } from '@ember/service';
import Component from '@glimmer/component';
import { getFleetOpsOptionLabel } from '../../helpers/get-fleet-ops-option-label';

export default class IssuePanelHeaderComponent extends Component {
    @service intl;
    get resource() {
        return this.args.resource;
    }

    get title() {
        return this.resource?.title || this.intl.t('fleetops-ui.js.components.issue.panel-header.issue-reported-on-value', { value: this.resource?.createdAt || 'unknown date' });
    }

    get tags() {
        return Array.isArray(this.resource?.tags) ? this.resource.tags.filter(Boolean) : [];
    }

    get subtitle() {
        return [
            [this.resource?.type, 'issueTypes'],
            [this.resource?.category, 'issueCategories'],
        ]
            .filter(([value]) => Boolean(value))
            .map(([value, key]) => getFleetOpsOptionLabel(key, value, this.intl) || value.replace(/[-_]/g, ' '))
            .join(' / ');
    }
}
