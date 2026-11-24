import ResourceActionService from '@fleetbase/ember-core/services/resource-action';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';
import { format } from 'date-fns';
import { issueStatuses, localizeOptions } from '../utils/fleet-ops-options';
import { PANEL_DEFAULTS } from '../utils/context-panel';

export default class IssueActionsService extends ResourceActionService {
    @service driverActions;
    @service vehicleActions;

    /**
     * New issues get a "reported on <now>" title worked out when the issue is created: as a
     * default attribute it was fixed when the service started, in that moment's language,
     * so every issue carried the same stale English time stamp.
     */
    createNewInstance(attributes = {}) {
        const title = this.intl.t('fleetops-ui.js.services.issue-actions.issue-reported-on-value', { value: format(new Date(), 'dd MMM yy, HH:mm') });
        return super.createNewInstance({ title, ...attributes });
    }

    constructor() {
        super(...arguments);
        this.initialize('issue', {
            defaultAttributes: {
                status: 'pending',
                priority: 'low',
                type: 'operational',
            },
        });
    }

    transition = {
        view: (issue) => this.transitionTo('management.issues.index.details', issue),
        edit: (issue) => this.transitionTo('management.issues.index.edit', issue),
        create: () => this.transitionTo('management.issues.index.new'),
    };

    panel = {
        create: (attributes = {}) => {
            const issue = this.createNewInstance(attributes);
            return this.resourceContextPanel.open({
                content: 'issue/form',
                title: this.intl.t('common.create-a-new-resource', { resource: this.intl.t('resource.issue')?.toLowerCase() }),
                useDefaultSaveTask: true,
                saveOptions: {
                    callback: this.refresh,
                },
                issue,
            });
        },
        edit: (issue) => {
            return this.resourceContextPanel.open({
                content: 'issue/form',
                title: this.intl.t('common.edit-resource-name', { resourceName: issue.name }),
                useDefaultSaveTask: true,
                issue,
            });
        },
        view: (issue, options = {}) => {
            return this.resourceContextPanel.open({
                registry: this.detailsRegistry,
                issue,
                header: 'issue/panel-header',
                width: '800px',
                actionButtons: this.panelActionButtons(issue),
                tabs: [{ key: 'overview', label: this.intl.t('common.overview'), component: 'issue/details' }],
                ...PANEL_DEFAULTS,
                ...options,
            });
        },
    };

    modal = {
        create: (attributes = {}, options = {}, saveOptions = {}) => {
            const issue = this.createNewInstance(attributes);
            return this.modalsManager.show('modals/resource', {
                resource: issue,
                title: this.intl.t('common.create-a-new-resource', { resource: this.intl.t('resource.issue')?.toLowerCase() }),
                acceptButtonText: this.intl.t('common.create-resource', { resource: this.intl.t('resource.issue') }),
                component: 'issue/form',
                confirm: (modal) => this.modalTask.perform(modal, 'saveTask', issue, { refresh: true, ...saveOptions }),
                ...options,
            });
        },
        edit: (issue, options = {}, saveOptions = {}) => {
            return this.modalsManager.show('modals/resource', {
                resource: issue,
                title: this.intl.t('common.edit-resource-name', { resourceName: issue.name }),
                acceptButtonText: this.intl.t('common.save-changes'),
                saveButtonIcon: 'save',
                component: 'issue/form',
                confirm: (modal) => this.modalTask.perform(modal, 'saveTask', issue, { refresh: true, ...saveOptions }),
                ...options,
            });
        },
        view: (issue, options = {}) => {
            return this.modalsManager.show('modals/resource', {
                resource: issue,
                title: issue.name,
                component: 'issue/details',
                ...options,
            });
        },
    };

    /**
     * The header buttons of an issue panel, the same as the issue details
     * route: edit, then the workflow menu. The menu is read when it renders,
     * so closing or re-opening the issue swaps the last item.
     */
    panelActionButtons(issue) {
        const service = this;
        const refresh = () => issue?.reload?.();

        return [
            {
                icon: 'pencil',
                permission: 'fleet-ops update issue',
                fn: async () => {
                    await this.resourceContextPanel.closeAll();
                    this.panel.edit(issue);
                },
            },
            {
                icon: 'ellipsis',
                type: 'default',
                renderInPlace: true,
                get items() {
                    return service.workflowItems(issue, refresh);
                },
            },
        ];
    }

    workflowItems(issue, onSaved) {
        const items = [
            {
                label: this.intl.t('fleetops-ui.js.services.issue-actions.change-status'),
                text: this.intl.t('fleetops-ui.js.services.issue-actions.change-status'),
                icon: 'arrows-rotate',
                fn: () => this.openStatusModal(issue, { onSaved }),
            },
            {
                label: this.intl.t('fleetops-ui.js.services.issue-actions.assign-issue'),
                text: this.intl.t('fleetops-ui.js.services.issue-actions.assign-issue'),
                icon: 'user-check',
                fn: () => this.openAssignModal(issue, { onSaved }),
            },
        ];

        if (this.closedStatuses.includes(issue?.status)) {
            items.push({
                label: this.intl.t('fleetops-ui.js.services.issue-actions.re-open-issue'),
                text: this.intl.t('fleetops-ui.js.services.issue-actions.re-open-issue'),
                icon: 'rotate-left',
                fn: () => this.confirmReopenIssue(issue, { onSaved }),
            });
        } else {
            items.push({
                label: this.intl.t('fleetops-ui.js.services.issue-actions.close-issue'),
                text: this.intl.t('fleetops-ui.js.services.issue-actions.close-issue'),
                icon: 'circle-check',
                class: 'text-green-600 dark:text-green-400',
                fn: () => this.openCloseIssueModal(issue, { onSaved }),
            });
        }

        return items;
    }

    get closedStatuses() {
        return ['closed', 'resolved', 'completed'];
    }

    openStatusModal(issue, options = {}) {
        return this.modalsManager.show('modals/issue-status', {
            issue,
            statusOptions: localizeOptions(this.intl, 'issueStatuses', issueStatuses),
            onSaved: options.onSaved,
        });
    }

    openAssignModal(issue, options = {}) {
        return this.modalsManager.show('modals/issue-assign', {
            issue,
            onSaved: options.onSaved,
        });
    }

    openCloseIssueModal(issue, options = {}) {
        return this.modalsManager.show('modals/issue-close', {
            issue,
            onSaved: options.onSaved,
        });
    }

    confirmReopenIssue(issue, options = {}) {
        this.modalsManager.confirm({
            title: this.intl.t('fleetops-ui.js.services.issue-actions.re-open-value', { value: issue.public_id || 'issue' }),
            body: this.intl.t('fleetops-ui.js.services.issue-actions.this-will-move-the-issue-back'),
            acceptButtonText: this.intl.t('fleetops-ui.js.services.issue-actions.re-open-issue'),
            acceptButtonIcon: 'rotate-left',
            confirm: async (modal) => {
                modal.startLoading();

                try {
                    const meta = issue.meta ?? {};
                    const reopenHistory = Array.isArray(meta.reopen_history) ? meta.reopen_history : [];
                    const user = this.currentUser.user;

                    issue.status = 're_opened';
                    issue.resolved_at = null;
                    issue.meta = {
                        ...meta,
                        reopen_history: [
                            ...reopenHistory,
                            {
                                reopened_at: new Date().toISOString(),
                                reopened_by_uuid: user?.id,
                                reopened_by_name: user?.name,
                            },
                        ],
                    };

                    await issue.save();
                    this.notifications.success(this.intl.t('fleetops-ui.js.services.issue-actions.issue-re-opened'));

                    if (typeof options.onSaved === 'function') {
                        await options.onSaved(issue);
                    }

                    modal.done();
                } catch (error) {
                    this.notifications.serverError(error);
                    modal.stopLoading();
                }
            },
        });
    }

    @action async viewDriver(issue) {
        const driver = await issue.loadDriver();
        if (driver) {
            this.driverActions.panel.view(driver);
        }
    }

    @action async viewVehicle(issue) {
        const vehicle = await issue.loadVehicle();
        if (vehicle) {
            this.vehicleActions.panel.view(vehicle);
        }
    }
}
