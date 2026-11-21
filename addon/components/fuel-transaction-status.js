import { inject as service } from '@ember/service';
import Component from '@glimmer/component';
import { transactionStatuses, transactionStatusLabel } from '../utils/fuel-transaction';

export default class FuelTransactionStatusComponent extends Component {
    @service intl;
    get status() {
        const key = this.args.status ?? this.args.value;
        if (transactionStatuses[key]) return { ...transactionStatuses[key], label: transactionStatusLabel(this.intl, key) };
        return { label: this.intl.t('fleetops-ui.js.components.fuel-transaction-status.unknown'), tone: 'default' };
    }
}
