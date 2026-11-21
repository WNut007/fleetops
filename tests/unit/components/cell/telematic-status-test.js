import { module, test } from 'qunit';
import CellTelematicStatusComponent from 'dummy/components/cell/telematic-status';

// The labels are translation keys whose last segment is the English label, e.g. ...telematic-status.not-tested.
const intl = {
    t(key) {
        const text = key.split('.').pop().replace(/-/g, ' ');
        return text.charAt(0).toUpperCase() + text.slice(1);
    },
};

function makeStatusCell(status) {
    const component = Object.create(CellTelematicStatusComponent.prototype);
    Object.defineProperty(component, 'intl', { value: intl });
    component.args = { row: { status } };

    return component;
}

module('Unit | Component | cell/telematic-status', function () {
    test('active and connected both display as connected', function (assert) {
        assert.strictEqual(makeStatusCell('active').label, 'Connected');
        assert.strictEqual(makeStatusCell('connected').label, 'Connected');
        assert.strictEqual(makeStatusCell('active').badgeStatus, 'success');
        assert.strictEqual(makeStatusCell('connected').badgeStatus, 'success');
    });

    test('remaining provider statuses map to their operator facing labels', function (assert) {
        assert.strictEqual(makeStatusCell('synchronizing').label, 'Syncing');
        assert.strictEqual(makeStatusCell('synchronizing').badgeStatus, 'info');
        assert.strictEqual(makeStatusCell('initialized').label, 'Not tested');
        assert.strictEqual(makeStatusCell('error').label, 'Needs attention');
        assert.strictEqual(makeStatusCell('error').badgeStatus, 'warning');
        assert.strictEqual(makeStatusCell('degraded').badgeStatus, 'warning');
        assert.strictEqual(makeStatusCell('disconnected').badgeStatus, 'warning');
    });

    test('a missing status falls back to unknown', function (assert) {
        assert.strictEqual(makeStatusCell(undefined).label, 'Unknown');
        assert.strictEqual(makeStatusCell(undefined).badgeStatus, 'default');
    });
});
