import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { inject as service } from '@ember/service';
import { task } from 'ember-concurrency-decorators';

export default class OrderDetailsProofComponent extends Component {
    @service fetch;
    @tracked proofs = [];

    constructor(owner, { resource }) {
        super(...arguments);
        this.loadOrderProofs.perform(resource);
    }

    @task *loadOrderProofs(order) {
        if (!order?.id) {
            this.proofs = [];
            return;
        }

        const proofs = yield this.fetch.get(`orders/${order.id}/proofs`);

        this.proofs = this.#normalizeProofs(proofs);
    }

    @action reloadProofs() {
        this.loadOrderProofs.perform(this.args.resource);
    }

    // The API's default remarks end with the proof kind ("Verified by Signature"). A driver app
    // may send its own remarks (e.g. in Thai), so fall back to the captured data itself: a
    // signature carries its PNG as a data URL, a photo has a file URL, a scan only raw text.
    #getType(proof = {}) {
        const remarks = proof.remarks ?? '';
        if (remarks.endsWith('Photo')) return 'photo';
        if (remarks.endsWith('Scan')) return 'scan';
        if (remarks.endsWith('Signature')) return 'signature';
        if (typeof proof.raw === 'string' && proof.raw.startsWith('data:image')) return 'signature';
        if (proof.url) return 'photo';
        if (proof.raw) return 'scan';
        return undefined;
    }

    #normalizeProofs(proofs = []) {
        const seen = new Set();

        return proofs
            .filter((proof) => {
                const key = proof.uuid ?? proof.public_id ?? proof.id;
                if (!key || seen.has(key)) {
                    return false;
                }

                seen.add(key);
                return true;
            })
            .map((proof) => ({
                ...proof,
                type: this.#getType(proof),
            }))
            .sort((a, b) => new Date(b.created_at ?? 0).getTime() - new Date(a.created_at ?? 0).getTime());
    }
}
