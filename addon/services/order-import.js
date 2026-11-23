import Service, { inject as service } from '@ember/service';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { isArray } from '@ember/array';
import { debug } from '@ember/debug';
import { task } from 'ember-concurrency';

export default class OrderImportService extends Service {
    @service intl;
    @service store;
    @service fetch;
    @service modalsManager;
    @service notifications;
    @service currentUser;
    @tracked queuedFiles = [];
    @tracked uploadedFiles = [];

    @action promptImport(order, options = {}) {
        this.#reset();

        return this.modalsManager.show('modals/order-import', {
            title: this.intl.t('fleetops-ui.js.services.order-import.import-order-s-with-spreadsheets'),
            acceptButtonText: this.intl.t('fleetops-ui.js.services.order-import.start-upload'),
            acceptButtonScheme: 'magic',
            acceptButtonIcon: 'upload',
            acceptButtonDisabled: true,
            isProcessing: false,
            fileQueueColumns: [
                { name: this.text('crud.column-type', 'Type'), valuePath: 'extension', key: 'type' },
                { name: this.text('crud.column-file-name', 'File Name'), valuePath: 'name', key: 'fileName' },
                { name: this.text('crud.column-file-size', 'File Size'), valuePath: 'size', key: 'fileSize' },
                { name: this.text('crud.column-upload-date', 'Upload Date'), valuePath: 'file.lastModifiedDate', key: 'uploadDate' },
                { name: '', valuePath: '', key: 'delete' },
            ],
            acceptedFileTypes: ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/csv'],
            uploadQueue: this.queuedFiles,
            queueFile: (file) => this.#addFileToQueue(file),
            removeFile: (file) => this.#removeFileFromQueue(file),
            confirm: async (modal) => {
                if (!this.#importValid()) return this.notifications.warning(this.intl.t('fleet-ops.operations.orders.index.new.warning-message'));

                modal.startLoading();
                modal.setOption('acceptButtonText', this.text('crud.uploading', 'Uploading...'));

                const uploadedFiles = (await this.uploadQueue.perform()) ?? [];

                this.modalsManager.setOption('acceptButtonText', this.text('crud.processing', 'Processing...'));
                this.modalsManager.setOption('isProcessing', true);

                const results = await this.importFiles.perform(uploadedFiles.filter(Boolean));
                if (!results) {
                    this.modalsManager.setOption('isProcessing', false);
                    return modal.stopLoading();
                }

                const { places, entities } = results;

                if (isArray(places)) {
                    // imported stops replace the single pickup/dropoff
                    order.payload.setProperties({ pickup: null, dropoff: null });
                    order.payload.set(
                        'waypoints',
                        places.map((p) => {
                            const place = this.store.createRecord('place', p);
                            return this.store.createRecord('waypoint', { place });
                        })
                    );
                }

                if (isArray(entities)) {
                    order.payload.set(
                        'entities',
                        entities.map((entity) => this.store.createRecord('entity', entity))
                    );
                }

                this.notifications.success(this.intl.t('fleet-ops.operations.orders.index.new.import-success'));

                this.#reset();
                modal.done();
            },
            decline: (modal) => {
                this.#reset();
                modal.done();
            },
            ...options,
        });
    }

    @task *importFiles(files = []) {
        const ids = files.map((file) => file.id);

        try {
            const results = yield this.fetch.post('orders/process-imports', { files: ids });
            return results;
        } catch (err) {
            debug('Error processing file imports: ' + err.message);
            this.notifications.serverError(err);
        }
    }

    @task *uploadQueue() {
        const uploaded = [];

        try {
            for (let i = 0; i < this.queuedFiles.length; i++) {
                const file = this.queuedFiles[i];
                if (!file) continue;

                const uploadedFile = yield this.uploadFile.perform(file);
                uploaded.push(uploadedFile);
            }

            return uploaded;
        } catch (err) {
            debug('Error uploading import queue: ' + err.message);
        }
    }

    @task *uploadFile(file) {
        try {
            const uploadedFile = yield this.fetch.uploadFile.perform(file, {
                path: `uploads/fleet-ops/order-imports/${this.currentUser.companyId}`,
                type: `order_import`,
            });

            this.uploadedFiles.pushObject(uploadedFile);
            return uploadedFile;
        } catch (err) {
            debug('Error uploading order import source: ' + err.message);
        }
    }

    /** A translation with an English fallback for keys that live outside this engine. */
    text(key, fallback) {
        return this.intl.exists(key) ? this.intl.t(key) : fallback;
    }

    #reset() {
        this.queuedFiles = [];
        this.uploadedFiles = [];
    }

    #importValid() {
        return this.queuedFiles.length > 0;
    }

    #checkqueuedFiles() {
        if (this.queuedFiles.length) {
            this.modalsManager.setOption('acceptButtonDisabled', false);
        } else {
            this.modalsManager.setOption('acceptButtonDisabled', true);
        }
    }

    #addFileToQueue(file) {
        this.queuedFiles.pushObject(file);
        this.#checkqueuedFiles();
    }

    #removeFileFromQueue(file) {
        file.queue?.remove(file);
        this.queuedFiles.removeObject(file);
        this.#checkqueuedFiles();
    }
}
