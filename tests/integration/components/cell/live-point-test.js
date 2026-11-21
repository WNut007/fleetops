import { module, test } from 'qunit';
import { setupRenderingTest } from 'dummy/tests/helpers';
import { render, click, settled } from '@ember/test-helpers';
import { tracked } from '@glimmer/tracking';
import { hbs } from 'ember-cli-htmlbars';

class Driver {
    @tracked location = { type: 'Point', coordinates: [100.736, 13.724] };
}

module('Integration | Component | cell/live-point', function (hooks) {
    setupRenderingTest(hooks);

    test('the cell follows the location when it changes', async function (assert) {
        this.driver = new Driver();
        this.column = { valuePath: 'location' };

        await render(hbs`<Cell::LivePoint @row={{this.driver}} @column={{this.column}} />`);
        assert.dom('span').hasText('13.724 100.736');

        this.driver.location = { type: 'Point', coordinates: [100.7381, 13.7262] };
        await settled();
        assert.dom('span').hasText('13.7262 100.7381');
    });

    test('no point, nothing shown; a column with onClick makes it a link', async function (assert) {
        const clicked = [];
        this.driver = { location: null };
        this.column = { valuePath: 'location', onClick: (row) => clicked.push(row) };

        await render(hbs`<Cell::LivePoint @row={{this.driver}} @column={{this.column}} />`);
        assert.dom('a').hasText('');

        await click('a');
        assert.deepEqual(clicked, [this.driver]);
    });
});
