import { module, test } from 'qunit';
import { setupRenderingTest } from 'dummy/tests/helpers';
import { render, click, settled } from '@ember/test-helpers';
import { hbs } from 'ember-cli-htmlbars';

const statuses = (...codes) => codes.map((code) => ({ code, status: code }));
const shown = () => [...document.querySelectorAll('[data-test-activity-step]')].map((step) => step.textContent.trim());

module('Integration | Component | order/activity-steps', function (hooks) {
    setupRenderingTest(hooks);

    test('opens on the newest three and every status is reachable one press at a time', async function (assert) {
        this.set('activity', statuses('CREATED', 'DISPATCHED', 'STARTED', 'ARRIVED_PORT', 'CUSTOMS_CLEARED', 'DELIVERED', 'COMPLETED'));

        await render(hbs`<Order::ActivitySteps @activity={{this.activity}} @activeStatus="completed" as |s|>{{s.status}}</Order::ActivitySteps>`);
        assert.deepEqual(shown(), ['CUSTOMS_CLEARED', 'DELIVERED', 'COMPLETED']);
        assert.dom('[data-test-activity-next]').isDisabled('already at the newest');
        assert.dom('.timeline-item.active').hasText('COMPLETED', 'the current status is marked');

        for (let i = 0; i < 4; i++) {
            await click('[data-test-activity-previous]');
        }
        assert.deepEqual(shown(), ['CREATED', 'DISPATCHED', 'STARTED']);
        assert.dom('[data-test-activity-previous]').isDisabled('at the oldest');

        await click('[data-test-activity-next]');
        assert.deepEqual(shown(), ['DISPATCHED', 'STARTED', 'ARRIVED_PORT'], 'one status per press');
    });

    test('a new status shows at once while following the newest; paging back stays put', async function (assert) {
        this.set('activity', statuses('CREATED', 'DISPATCHED', 'STARTED'));
        await render(hbs`<Order::ActivitySteps @activity={{this.activity}} as |s|>{{s.status}}</Order::ActivitySteps>`);
        assert.dom('[data-test-activity-previous]').isDisabled();
        assert.dom('[data-test-activity-next]').isDisabled();

        this.set('activity', statuses('CREATED', 'DISPATCHED', 'STARTED', 'ARRIVED_PORT'));
        await settled();
        assert.deepEqual(shown(), ['DISPATCHED', 'STARTED', 'ARRIVED_PORT']);

        await click('[data-test-activity-previous]');
        this.set('activity', statuses('CREATED', 'DISPATCHED', 'STARTED', 'ARRIVED_PORT', 'CUSTOMS_CLEARED'));
        await settled();
        assert.deepEqual(shown(), ['CREATED', 'DISPATCHED', 'STARTED'], 'the page the user chose stays');

        await click('[data-test-activity-next]');
        await click('[data-test-activity-next]');
        this.set('activity', statuses('CREATED', 'DISPATCHED', 'STARTED', 'ARRIVED_PORT', 'CUSTOMS_CLEARED', 'DELIVERED'));
        await settled();
        assert.deepEqual(shown(), ['ARRIVED_PORT', 'CUSTOMS_CLEARED', 'DELIVERED'], 'back at the newest, it follows again');
    });

    test('fewer than three statuses, or none', async function (assert) {
        this.set('activity', statuses('CREATED'));
        await render(hbs`<Order::ActivitySteps @activity={{this.activity}} as |s|>{{s.status}}</Order::ActivitySteps>`);
        assert.deepEqual(shown(), ['CREATED']);

        this.set('activity', null);
        await settled();
        assert.deepEqual(shown(), []);
    });
});
