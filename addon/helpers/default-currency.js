import Helper from '@ember/component/helper';
import { inject as service } from '@ember/service';

/**
 * The organization's currency (else the user's, else USD) for money inputs whose record has
 * no currency yet — they used to fall back to USD outright.
 *
 *   <MoneyInput @currency={{or @resource.currency (default-currency)}} />
 */
export default class DefaultCurrencyHelper extends Helper {
    @service currentUser;

    compute() {
        return this.currentUser.company?.currency || this.currentUser.currency || 'USD';
    }
}
