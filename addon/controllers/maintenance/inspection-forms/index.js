import Controller from '@ember/controller';
import { inject as service } from '@ember/service';
import { tracked } from '@glimmer/tracking';

export default class MaintenanceInspectionFormsIndexController extends Controller {
    @service inspectionFormActions;
    @service intl;

    @tracked queryParams = this.inspectionFormActions.queryParamsFor(['status', 'type', 'page', 'limit', 'sort', 'query', 'public_id', 'created_at', 'updated_at']);
    @tracked page = 1;
    @tracked limit;
    @tracked sort = '-created_at';
    @tracked public_id;
    @tracked status;
    @tracked type;

    get actionButtons() {
        return [
            { id: 'refresh', icon: 'refresh', onClick: this.inspectionFormActions.refresh, helpText: this.intl.t('common.refresh') },
            {
                id: 'create',
                text: this.intl.t('common.new'),
                type: 'primary',
                icon: 'plus',
                onClick: this.inspectionFormActions.transition.create,
                permission: 'fleet-ops create inspection-form',
            },
        ];
    }

    get bulkActions() {
        return [
            {
                id: 'bulk-delete',
                label: this.intl.t('fleetops-ui.js.controllers.maintenance.inspection-forms.index.delete-selected'),
                class: 'text-red-500',
                fn: this.inspectionFormActions.bulkDelete,
                permission: 'fleet-ops delete inspection-form',
            },
        ];
    }

    get columns() {
        return [
            {
                id: 'name',
                label: this.intl.t('fleetops-ui.js.controllers.maintenance.inspection-forms.index.name'),
                valuePath: 'name',
                cellComponent: 'table/cell/anchor',
                action: this.inspectionFormActions.transition.view,
                permission: 'fleet-ops view inspection-form',
                resizable: true,
                sortable: true,
                filterable: true,
                filterParam: 'name',
                filterComponent: 'filter/string',
            },
            {
                id: 'type',
                label: this.intl.t('fleetops-ui.js.controllers.maintenance.inspection-forms.index.type'),
                valuePath: 'type',
                cellComponent: 'table/cell/fleet-ops-option',
                optionsKey: 'inspectionFormTypes',
                resizable: true,
                sortable: true,
                filterable: true,
                filterParam: 'type',
                filterComponent: 'filter/string',
            },
            {
                id: 'status',
                label: this.intl.t('fleetops-ui.js.controllers.maintenance.inspection-forms.index.status'),
                valuePath: 'status',
                cellComponent: 'table/cell/status',
                resizable: true,
                sortable: true,
                filterable: true,
                filterParam: 'status',
                filterComponent: 'filter/string',
            },
            { id: 'item-count', label: this.intl.t('fleetops-ui.js.controllers.maintenance.inspection-forms.index.items'), valuePath: 'item_count', resizable: true, sortable: false },
            {
                id: 'created-at',
                label: this.intl.t('column.created-at'),
                valuePath: 'createdAt',
                sortParam: 'created_at',
                resizable: true,
                sortable: true,
                filterable: true,
                filterComponent: 'filter/date',
            },
            {
                id: 'row-actions',
                label: '',
                cellComponent: 'table/cell/dropdown',
                ddButtonText: false,
                ddButtonIcon: 'ellipsis-h',
                ddButtonIconPrefix: 'fas',
                cellClassNames: 'overflow-visible',
                wrapperClass: 'flex items-center justify-end mx-2',
                actions: [
                    {
                        id: 'view',
                        label: this.intl.t('fleetops-ui.js.controllers.maintenance.inspection-forms.index.view-form'),
                        fn: this.inspectionFormActions.transition.view,
                        permission: 'fleet-ops view inspection-form',
                    },
                    {
                        id: 'edit',
                        label: this.intl.t('fleetops-ui.js.controllers.maintenance.inspection-forms.index.edit-form'),
                        fn: this.inspectionFormActions.transition.edit,
                        permission: 'fleet-ops update inspection-form',
                    },
                    { separator: true },
                    {
                        id: 'publish',
                        label: this.intl.t('fleetops-ui.js.controllers.maintenance.inspection-forms.index.publish'),
                        fn: this.inspectionFormActions.publish,
                        permission: 'fleet-ops publish inspection-form',
                    },
                    {
                        id: 'archive',
                        label: this.intl.t('fleetops-ui.js.controllers.maintenance.inspection-forms.index.archive'),
                        fn: this.inspectionFormActions.archive,
                        permission: 'fleet-ops archive inspection-form',
                    },
                    {
                        id: 'generate-link',
                        label: this.intl.t('fleetops-ui.js.controllers.maintenance.inspection-forms.index.generate-inspection-link'),
                        fn: this.inspectionFormActions.generateLink,
                        permission: 'fleet-ops view inspection-form',
                    },
                    { separator: true },
                    {
                        id: 'delete',
                        label: this.intl.t('fleetops-ui.js.controllers.maintenance.inspection-forms.index.delete-form'),
                        fn: this.inspectionFormActions.delete,
                        class: 'text-red-500',
                        permission: 'fleet-ops delete inspection-form',
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
