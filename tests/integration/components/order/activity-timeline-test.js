import { module, test } from 'qunit';
import { setupRenderingTest } from 'dummy/tests/helpers';
import { render, settled } from '@ember/test-helpers';
import { hbs } from 'ember-cli-htmlbars';

const status = (code, label) => ({ code, status: label, details: label, created_at: new Date('2026-11-20T00:00:00Z') });

module('Integration | Component | order/activity-timeline', function (hooks) {
    setupRenderingTest(hooks);

    test('a status recorded after the panel opened shows without a reload', async function (assert) {
        this.set('resource', { status: 'created' });
        this.set('activity', [status('CREATED', 'Order Created')]);

        await render(hbs`<Order::ActivityTimeline @resource={{this.resource}} @activity={{this.activity}} />`);
        assert.dom('.timeline-items-container').includesText('Order Created');
        assert.dom('.timeline-items-container').doesNotIncludeText('Order Dispatched');

        this.set('activity', [status('CREATED', 'Order Created'), status('DISPATCHED', 'Order Dispatched')]);
        await settled();

        assert.dom('.timeline-items-container').includesText('Order Dispatched');
    });
});
