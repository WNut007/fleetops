import Controller from '@ember/controller';
import { inject as service } from '@ember/service';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { buildIdentityStub } from '../../../utils/identity-cell-resource';
import relationValue from '../../../utils/relation-value';
import { openTransactionAction } from '../../../utils/fuel-transaction';

export default class ManagementFuelTransactionsIndexController extends Controller {
    @service intl;
    @service tableContext;
    @service modalsManager;
    @service notifications;
    @service hostRouter;

    @tracked queryParams = ['page', 'limit', 'sort', 'query', 'provider', 'sync_status', 'vehicle', 'connection', 'transaction_at'];
    @tracked page = 1;
    @tracked limit;
    @tracked sort = '-transaction_at';
    @tracked query;
    @tracked provider;
    @tracked sync_status;
    @tracked vehicle;
    @tracked connection;
    @tracked transaction_at;
    @tracked table;

    get viewingDetails() {
        return this.hostRouter.currentRouteName?.endsWith('management.fuel-transactions.index.details');
    }

    get hasFilters() {
        return Boolean(this.query || this.provider || this.sync_status || this.vehicle || this.transaction_at);
    }

    get hasRecords() {
        return Array.from(this.model ?? []).length > 0;
    }

    get emptyStateTitle() {
        if (this.sync_status === 'unmatched') {
            return 'No unmatched fuel transactions';
        }

        if (this.connection) {
            return 'No transactions imported for this integration';
        }

        return 'No fuel transactions imported yet';
    }

    get emptyStateMessage() {
        if (this.sync_status === 'unmatched') {
            return 'Fleet-Ops did not find imported provider bills that still need vehicle or trip matching.';
        }

        if (this.connection) {
            return 'Run a sync from the fuel integration detail page to import provider bills into this ledger.';
        }

        return 'Connect PetroApp or another fuel integration, then run a transaction sync. Imported provider bills appear here before linked Fuel Reports are created.';
    }

    get actionButtons() {
        return [
            {
                id: 'refresh',
                icon: 'refresh',
                onClick: this.refresh,
                helpText: this.intl.t('fleetops-ui.js.controllers.management.fuel-transactions.index.refresh'),
            },
            {
                id: 'fuel-integrations',
                icon: 'gas-pump',
                text: this.intl.t('fleetops-ui.js.controllers.management.fuel-transactions.index.fuel-integrations'),
                onClick: () => this.hostRouter.transitionTo('console.fleet-ops.connectivity.fuel-providers.index'),
                permission: 'fleet-ops list fuel-provider-connection',
            },
        ];
    }

    @action refresh() {
        this.target.send('refreshTransactions');
    }

    get bulkActions() {
        const selected = this.tableContext.getSelectedRows();

        return [
            {
                id: 'bulk-reprocess',
                label: `Reprocess ${selected.length} selected`,
                fn: () => this.confirmAction('reprocess', selected),
                permission: 'fleet-ops update fuel-provider-transaction',
            },
        ];
    }

    get columns() {
        return [
            {
                id: 'provider-transaction-id',
                sticky: true,
                label: this.intl.t('fleetops-ui.js.controllers.management.fuel-transactions.index.transaction'),
                valuePath: 'provider_transaction_id',
                cellComponent: 'cell/fuel-provider-transaction-identity',
                action: this.openDetails,
                resizable: true,
                sortable: true,
                filterable: true,
                filterParam: 'query',
                filterComponent: 'filter/string',
            },
            {
                id: 'provider',
                label: this.intl.t('fleetops-ui.js.controllers.management.fuel-transactions.index.provider'),
                valuePath: 'provider',
                resizable: true,
                sortable: true,
                filterable: true,
                filterComponent: 'filter/string',
            },
            {
                id: 'sync-status',
                label: this.intl.t('fleetops-ui.js.controllers.management.fuel-transactions.index.status'),
                valuePath: 'sync_status',
                cellComponent: 'fuel-transaction-status',
                resizable: true,
                sortable: true,
                filterable: true,
                filterComponent: 'filter/multi-option',
                filterOptions: ['imported', 'matched', 'unmatched', 'reviewed', 'ignored', 'duplicate', 'error'],
            },
            {
                id: 'vehicle-name',
                label: this.intl.t('fleetops-ui.js.controllers.management.fuel-transactions.index.vehicle'),
                valuePath: 'vehicle_name',
                cellComponent: 'cell/vehicle-identity',
                permission: 'fleet-ops view vehicle',
                resourcePath: (transaction) => relationValue(transaction, 'vehicle') ?? buildIdentityStub(transaction, { type: 'vehicle', load: () => transaction.get('vehicle') }),
                resizable: true,
                sortable: false,
                filterable: true,
                filterParam: 'vehicle',
                filterComponent: 'filter/model',
                model: 'vehicle',
                modelNamePath: 'displayName',
            },
            {
                id: 'vehicle-card-id',
                label: this.intl.t('fleetops-ui.js.controllers.management.fuel-transactions.index.card-internal'),
                valuePath: 'vehicle_card_id',
                resizable: true,
            },
            {
                id: 'trip-number',
                label: this.intl.t('fleetops-ui.js.controllers.management.fuel-transactions.index.trip'),
                valuePath: 'trip_number',
                resizable: true,
                hidden: true,
            },
            {
                id: 'station-name',
                label: this.intl.t('fleetops-ui.js.controllers.management.fuel-transactions.index.station'),
                valuePath: 'station_name',
                resizable: true,
            },
            {
                id: 'volume',
                label: this.intl.t('fleetops-ui.js.controllers.management.fuel-transactions.index.liters'),
                valuePath: 'volume',
                resizable: true,
                sortable: true,
            },
            {
                id: 'amount',
                label: this.intl.t('fleetops-ui.js.controllers.management.fuel-transactions.index.amount'),
                valuePath: 'amount',
                cellComponent: 'table/cell/currency',
                resizable: true,
                sortable: true,
            },
            {
                id: 'fuel-report-id',
                label: this.intl.t('fleetops-ui.js.controllers.management.fuel-transactions.index.fuel-report'),
                valuePath: 'fuel_report_id',
                cellComponent: 'cell/fuel-report-identity',
                resourcePath: (transaction) =>
                    relationValue(transaction, 'fuel_report') ??
                    buildIdentityStub(transaction, { type: 'fuel-report', nameKey: 'fuel_report_id', load: () => transaction.get('fuel_report') }),
                resizable: true,
            },
            {
                id: 'transaction-at',
                label: this.intl.t('fleetops-ui.js.controllers.management.fuel-transactions.index.date'),
                valuePath: 'transactionAt',
                sortParam: 'transaction_at',
                resizable: true,
                sortable: true,
                filterable: true,
                filterParam: 'transaction_at',
                filterComponent: 'filter/date',
            },
            {
                id: 'row-actions',
                label: '',
                cellComponent: 'table/cell/dropdown',
                ddButtonText: false,
                ddButtonIcon: 'ellipsis-h',
                ddButtonIconPrefix: 'fas',
                ddMenuLabel: this.intl.t('fleetops-ui.js.controllers.management.fuel-transactions.index.fuel-transaction-actions'),
                cellClassNames: 'overflow-visible',
                wrapperClass: 'flex items-center justify-end mx-2',
                sticky: 'right',
                width: 60,
                actions: [
                    {
                        id: 'open-details',
                        label: this.intl.t('fleetops-ui.js.controllers.management.fuel-transactions.index.review-details'),
                        fn: this.openDetails,
                        permission: 'fleet-ops view fuel-provider-transaction',
                    },
                    {
                        id: 'open-fuel-report',
                        label: this.intl.t('fleetops-ui.js.controllers.management.fuel-transactions.index.open-fuel-report'),
                        fn: this.openFuelReport,
                        isVisible: (transaction) => Boolean(transaction.fuel_report_id),
                        permission: 'fleet-ops view fuel-report',
                    },
                    { separator: true },
                    {
                        id: 'match-vehicle',
                        label: this.intl.t('fleetops-ui.js.controllers.management.fuel-transactions.index.match-to-vehicle'),
                        fn: this.matchVehicle,
                        permission: 'fleet-ops update fuel-provider-transaction',
                    },
                    {
                        id: 'match-order',
                        label: this.intl.t('fleetops-ui.js.controllers.management.fuel-transactions.index.match-to-order'),
                        fn: this.matchOrder,
                        permission: 'fleet-ops update fuel-provider-transaction',
                    },
                    {
                        id: 'reprocess',
                        label: this.intl.t('fleetops-ui.js.controllers.management.fuel-transactions.index.reprocess-rematch'),
                        fn: (transaction) => this.confirmAction('reprocess', transaction),
                        permission: 'fleet-ops update fuel-provider-transaction',
                    },
                    {
                        id: 'ignore',
                        label: this.intl.t('fleetops-ui.js.controllers.management.fuel-transactions.index.ignore-transaction'),
                        fn: (transaction) => this.confirmAction('ignored', transaction),
                        permission: 'fleet-ops review fuel-provider-transaction',
                    },
                    {
                        id: 'mark-reviewed',
                        label: this.intl.t('fleetops-ui.js.controllers.management.fuel-transactions.index.mark-reviewed'),
                        fn: (transaction) => this.confirmAction('reviewed', transaction),
                        permission: 'fleet-ops review fuel-provider-transaction',
                    },
                ],
                sortable: false,
                filterable: false,
                resizable: false,
                searchable: false,
            },
        ];
    }

    @action openDetails(transaction) {
        return this.hostRouter.transitionTo('console.fleet-ops.management.fuel-transactions.index.details', transaction);
    }

    @action openFuelReport(transaction) {
        if (!transaction?.fuel_report_id) {
            this.notifications.info(this.intl.t('fleetops-ui.js.controllers.management.fuel-transactions.index.this-transaction-does-not-have-a'));
            return;
        }

        return this.hostRouter.transitionTo('console.fleet-ops.management.fuel-reports.index.details', transaction.fuel_report_id);
    }

    @action matchVehicle(transaction) {
        return this.confirmAction('vehicle', transaction);
    }

    @action matchOrder(transaction) {
        return this.confirmAction('order', transaction);
    }

    @action confirmAction(mode, transactions) {
        return openTransactionAction(this.modalsManager, mode, transactions, this.refresh, this.intl);
    }
}
