import Controller from '@ember/controller';
import { inject as service } from '@ember/service';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { task } from 'ember-concurrency';

export default class ConnectivityFuelProvidersIndexController extends Controller {
    @service fetch;
    @service notifications;
    @service tableContext;
    @service fuelIntegrationActions;
    @service intl;
    @service modalsManager;

    @tracked queryParams = ['page', 'limit', 'sort', 'query', 'provider', 'status', 'environment'];
    @tracked page = 1;
    @tracked limit;
    @tracked sort = '-updated_at';
    @tracked query;
    @tracked provider;
    @tracked status;
    @tracked environment;
    @tracked table;
    @tracked providers = [];

    constructor() {
        super(...arguments);
        this.loadProviders.perform();
    }

    get actionButtons() {
        return [
            {
                icon: 'refresh',
                onClick: this.refresh,
                helpText: this.intl.t('common.reload'),
            },
            {
                icon: 'plus',
                text: this.intl.t('fleetops-ui.js.controllers.connectivity.fuel-providers.index.connect-integration'),
                type: 'primary',
                onClick: () => this.fuelIntegrationActions.transition.create(),
                permission: 'fleet-ops create fuel-provider-connection',
            },
        ];
    }

    get bulkActions() {
        const selected = this.tableContext.getSelectedRows();

        return [
            {
                label: this.intl.t('fleetops-ui.js.controllers.connectivity.fuel-providers.index.sync-length-selected', { length: selected.length }),
                fn: () => selected.forEach((connection) => this.syncConnection(connection)),
                permission: 'fleet-ops sync fuel-provider-connection',
            },
        ];
    }

    get columns() {
        return [
            {
                sticky: true,
                label: this.intl.t('fleetops-ui.js.controllers.connectivity.fuel-providers.index.integration'),
                valuePath: 'displayName',
                sortParam: 'name',
                cellComponent: 'table/cell/anchor',
                action: this.openConnection,
                resizable: true,
                sortable: true,
                filterable: true,
                filterParam: 'query',
                filterComponent: 'filter/string',
            },
            {
                label: this.intl.t('fleetops-ui.js.controllers.connectivity.fuel-providers.index.provider-key'),
                valuePath: 'provider',
                resizable: true,
                sortable: true,
                filterable: true,
                filterComponent: 'filter/string',
            },
            {
                label: this.intl.t('fleetops-ui.js.controllers.connectivity.fuel-providers.index.status'),
                valuePath: 'status',
                cellComponent: 'table/cell/status',
                resizable: true,
                sortable: true,
                filterable: true,
                filterComponent: 'filter/multi-option',
                filterOptions: ['configured', 'connected', 'active', 'error', 'disabled'],
            },
            {
                label: this.intl.t('fleetops-ui.js.controllers.connectivity.fuel-providers.index.last-sync'),
                valuePath: 'lastSyncedAt',
                sortParam: 'last_synced_at',
                resizable: true,
                sortable: true,
            },
            {
                label: this.intl.t('fleetops-ui.js.controllers.connectivity.fuel-providers.index.new-in-last-sync'),
                valuePath: 'lastImported',
                resizable: true,
                sortable: false,
            },
            {
                label: this.intl.t('fleetops-ui.js.controllers.connectivity.fuel-providers.index.unmatched-in-last-sync'),
                valuePath: 'lastUnmatched',
                resizable: true,
                sortable: false,
            },
            {
                label: this.intl.t('fleetops-ui.js.controllers.connectivity.fuel-providers.index.last-error'),
                valuePath: 'last_error',
                resizable: true,
                hidden: true,
            },
            {
                label: '',
                cellComponent: 'table/cell/dropdown',
                ddButtonText: false,
                ddButtonIcon: 'ellipsis-h',
                ddButtonIconPrefix: 'fas',
                ddMenuLabel: this.intl.t('fleetops-ui.js.controllers.connectivity.fuel-providers.index.fuel-integration-actions'),
                cellClassNames: 'overflow-visible',
                wrapperClass: 'flex items-center justify-end mx-2',
                sticky: 'right',
                width: 60,
                actions: [
                    {
                        label: this.intl.t('fleetops-ui.js.controllers.connectivity.fuel-providers.index.open-integration'),
                        fn: this.openConnection,
                        permission: 'fleet-ops view fuel-provider-connection',
                    },
                    {
                        label: this.intl.t('fleetops-ui.js.controllers.connectivity.fuel-providers.index.edit-settings'),
                        fn: this.editConnection,
                        permission: 'fleet-ops update fuel-provider-connection',
                    },
                    { separator: true },
                    {
                        label: this.intl.t('fleetops-ui.js.controllers.connectivity.fuel-providers.index.test-connection'),
                        fn: this.testConnection,
                        permission: 'fleet-ops update fuel-provider-connection',
                    },
                    {
                        label: this.intl.t('fleetops-ui.js.controllers.connectivity.fuel-providers.index.sync-transactions'),
                        fn: this.syncConnection,
                        permission: 'fleet-ops sync fuel-provider-connection',
                    },
                ],
                sortable: false,
                filterable: false,
                resizable: false,
                searchable: false,
            },
        ];
    }

    @task *loadProviders() {
        try {
            this.providers = yield this.fetch.get('fuel-provider-connections/providers');
        } catch (error) {
            this.notifications.serverError(error);
        }
    }

    @action refresh() {
        this.target.send('refreshFuelConnections');
    }

    @action openConnection(connection) {
        return this.fuelIntegrationActions.transition.view(connection);
    }

    @action editConnection(connection) {
        return this.fuelIntegrationActions.transition.edit(connection);
    }

    @action testConnection(connection) {
        return this.modalsManager.show('modals/fuel-connection-diagnostics', {
            title: this.intl.t('fleetops-ui.js.controllers.connectivity.fuel-providers.index.test-fuel-connection'),
            acceptButtonText: this.intl.t('fleetops-ui.js.controllers.connectivity.fuel-providers.index.run-test'),
            acceptButtonIcon: 'plug',
            declineButtonText: this.intl.t('fleetops-ui.js.controllers.connectivity.fuel-providers.index.close'),
            connection,
            onTested: () => connection.reload(),
        });
    }

    @action async syncConnection(connection) {
        try {
            await this.fetch.post(`fuel-provider-connections/${connection.id}/sync`, { async: true });
            this.notifications.success(this.intl.t('fleetops-ui.js.controllers.connectivity.fuel-providers.index.fuel-transaction-sync-queued'));
            await connection.reload();
        } catch (error) {
            this.notifications.serverError(error);
        }
    }
}
