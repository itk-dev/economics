import {Controller} from '@hotwired/stimulus';
import Choices from "choices.js";
import 'choices.js/src/styles/choices.scss';

/**
 * Account selector controller.
 *
 * Loads choices from path.
 */
export default class extends Controller {
    static targets = ['choice', 'account', 'customer', 'customerCheckbox'];

    connect() {
        this.choiceTargets.forEach((target) => {
            const notDisabled = !target.disabled;

            if (notDisabled) {
                new Choices(target, {maxItemCount: 1, allowHTML: true, itemSelectText: ''});
            }
        });
    }

    toggleNewAccount(event) {
        const value = event.target.checked;

        for (let element of this.accountTargets) {
            element.classList.toggle('hidden');
        }

        if (!value) {
            this.customerCheckboxTarget.checked = false;

            for (let element of this.customerTargets) {
                element.classList.add('hidden');
            }
        }
    }

    toggleNewCustomer(event) {
        for (let element of this.customerTargets) {
            element.classList.toggle('hidden');
        }
    }
}
