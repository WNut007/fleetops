import Controller from '@ember/controller';
import { inject as service } from '@ember/service';
import { tracked } from '@glimmer/tracking';

export default class AnalyticsReportsIndexController extends Controller {
    @service reportActions;
    @service notifications;
    @service intl;

    /** query params */
    @tracked queryParams = this.reportActions.queryParamsFor(['page', 'limit', 'sort', 'query', 'public_id', 'name', 'created_at']);
    @tracked page = 1;
    @tracked limit;
    @tracked sort = '-created_at';
    @tracked public_id;
    @tracked name;
    @tracked created_at;

    /** action buttons */
    get actionButtons() {
        return [
            {
                id: 'refresh',
                icon: 'refresh',
                onClick: this.reportActions.refresh,
                helpText: this.intl.t('common.refresh'),
            },
            {
                id: 'create',
                text: this.intl.t('common.new'),
                type: 'primary',
                icon: 'plus',
                onClick: this.reportActions.transition.create,
                permission: 'iam create report',
            },
        ];
    }

    get bulkActions() {
        return [
            {
                id: 'bulk-delete',
                label: this.intl.t('fleetops-ui.js.controllers.analytics.reports.index.delete-selected'),
                class: 'text-red-500',
                fn: this.reportActions.bulkDelete,
                permission: 'iam delete report',
            },
        ];
    }

    get columns() {
        return [
            {
                id: 'title',
                sticky: true,
                label: this.intl.t('fleetops-ui.js.controllers.analytics.reports.index.title'),
                valuePath: 'title',
                cellComponent: 'table/cell/anchor',
                action: this.reportActions.transition.view,
                resizable: true,
                sortable: true,
                filterable: true,
                filterComponent: 'filter/string',
            },
            {
                id: 'public-id',
                label: this.intl.t('fleetops-ui.js.controllers.analytics.reports.index.id'),
                valuePath: 'public_id',
                cellComponent: 'click-to-copy',
                resizable: true,
                sortable: true,
                filterable: true,
                hidden: false,
                filterComponent: 'filter/string',
            },
            {
                id: 'row-actions',
                label: '',
                cellComponent: 'table/cell/dropdown',
                ddButtonText: false,
                ddButtonIcon: 'ellipsis-h',
                ddButtonIconPrefix: 'fas',
                ddMenuLabel: this.intl.t('common.resource-actions', { resource: this.intl.t('resource.Driver') }),
                cellClassNames: 'overflow-visible',
                wrapperClass: 'flex items-center justify-end mx-2',
                sticky: 'right',
                width: 60,
                actions: [
                    {
                        id: 'view',
                        label: this.intl.t('fleetops-ui.js.controllers.analytics.reports.index.view-report'),
                        fn: this.reportActions.transition.view,
                        permission: 'iam view report',
                    },
                    {
                        id: 'edit',
                        label: this.intl.t('fleetops-ui.js.controllers.analytics.reports.index.edit-report'),
                        fn: this.reportActions.transition.edit,
                        permission: 'iam update report',
                    },
                    {
                        separator: true,
                    },
                    {
                        id: 'delete',
                        label: this.intl.t('fleetops-ui.js.controllers.analytics.reports.index.delete-report'),
                        fn: this.reportActions.delete,
                        permission: 'iam delete report',
                    },
                ],
                sortable: false,
                filterable: false,
                resizable: false,
                searchable: false,
            },
        ];
    }
}
