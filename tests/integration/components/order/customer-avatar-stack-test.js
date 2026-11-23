import { module, test } from 'qunit';
import { setupRenderingTest } from 'dummy/tests/helpers';
import { render } from '@ember/test-helpers';
import { hbs } from 'ember-cli-htmlbars';

module('Integration | Component | order/customer-avatar-stack', function (hooks) {
    setupRenderingTest(hooks);

    test('stops without a customer show the no-customer text, not blank avatars', async function (assert) {
        this.set('waypoints', [{ address: 'A' }, { address: 'B' }]);

        await render(hbs`<Order::CustomerAvatarStack @waypoints={{this.waypoints}} />`);

        assert.dom('[role="img"]').doesNotExist();
        assert.dom('[data-test-customer-avatar-stack-empty]').exists();
    });

    test('only stops with a customer get an avatar', async function (assert) {
        this.set('waypoints', [{ address: 'A', customer: { public_id: 'customer_1', name: 'Somchai' } }, { address: 'B' }]);

        await render(hbs`<Order::CustomerAvatarStack @waypoints={{this.waypoints}} />`);

        assert.dom('[role="img"]').exists({ count: 1 });
        assert.dom('[data-test-customer-avatar-stack-empty]').doesNotExist();
    });

    test('falls back to the order customer when no stop has one', async function (assert) {
        this.set('waypoints', [{ address: 'A' }, { address: 'B' }]);
        this.set('customer', { public_id: 'contact_1', name: 'Kanchana' });

        await render(hbs`<Order::CustomerAvatarStack @waypoints={{this.waypoints}} @fallbackCustomer={{this.customer}} />`);

        assert.dom('[role="img"]').exists({ count: 1 });
        assert.dom('[data-test-customer-avatar-stack-empty]').doesNotExist();
    });
});
