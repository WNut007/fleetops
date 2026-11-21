import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { isEmpty } from '@ember/utils';
import { task } from 'ember-concurrency';
import config from 'ember-get-config';
import { action } from '@ember/object';
import { buildIdentityStub } from '../../../utils/identity-cell-resource';
import relationValue from '../../../utils/relation-value';

export default class SettingsPaymentsIndexController extends Controller {
    @service intl;
    @service store;
    @service fetch;
    @tracked hasStripeConnectAccount = true;
    @tracked table;
    @tracked page = 1;
    @tracked limit = 30;
    @tracked sort = '-created_at';
    @tracked query = null;
    queryParams = ['page', 'limit', 'sort', 'query'];
    columns = [
        {
            label: this.intl.t('fleetops-ui.js.controllers.settings.payments.index.purchase-rate-id'),
            valuePath: 'public_id',
            cellComponent: 'click-to-copy',
        },
        {
            label: this.intl.t('fleetops-ui.js.controllers.settings.payments.index.service-quote'),
            valuePath: 'service_quote_id',
            cellComponent: 'cell/service-quote-identity',
            resourcePath: (payment) => relationValue(payment, 'service_quote') ?? buildIdentityStub(payment, { type: 'service-quote', nameKey: 'service_quote_id' }),
        },
        {
            label: this.intl.t('fleetops-ui.js.controllers.settings.payments.index.order'),
            valuePath: 'order_id',
            cellComponent: 'cell/order-identity',
            resourcePath: (payment) =>
                relationValue(payment, 'order') ??
                buildIdentityStub(payment, { type: 'order', nameKey: 'order_id', load: () => (payment.order_uuid ? this.store.findRecord('order', payment.order_uuid) : null) }),
        },
        {
            label: this.intl.t('fleetops-ui.js.controllers.settings.payments.index.customer'),
            valuePath: 'customer.name',
            cellComponent: 'cell/customer-identity',
            resourcePath: (payment) => relationValue(payment, 'customer') ?? buildIdentityStub(payment, { type: payment.customer_type ?? 'customer', nameKey: 'customer_name' }),
        },
        {
            label: this.intl.t('fleetops-ui.js.controllers.settings.payments.index.amount'),
            valuePath: 'amount',
            cellComponent: 'table/cell/currency',
        },
        {
            label: this.intl.t('fleetops-ui.js.controllers.settings.payments.index.date'),
            valuePath: 'created_at',
        },
    ];

    get isStripeEnabled() {
        return !isEmpty(config.stripe.publishableKey);
    }

    @task *lookupStripeConnectAccount() {
        try {
            const { hasStripeConnectAccount } = yield this.fetch.get('fleet-ops/payments/has-stripe-connect-account');
            this.hasStripeConnectAccount = hasStripeConnectAccount;
        } catch (error) {
            this.hasStripeConnectAccount = false;
        }
    }

    @action refreshPayments() {
        return this.lookupStripeConnectAccount.perform();
    }
}
