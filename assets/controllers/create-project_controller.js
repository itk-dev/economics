import {Controller} from '@hotwired/stimulus';
import Choices from "choices.js";
import 'choices.js/src/styles/choices.scss';

/**
 * Account selector controller.
 *
 * Loads choices from path.
 */
export default class extends Controller {
    static targets = ['field'];

    connect() {
        this.fieldTargets.forEach((target) => {
            const notDisabled = !target.disabled;

            if (notDisabled) {
                new Choices(target, {maxItemCount: 1, allowHTML: true, itemSelectText: ''});
            }
        });

        // TODO: Change to stimulus.
        let checkboxCustomer = document.getElementById('create_project_form_new_customer');
        let checkboxAccount = document.getElementById('create_project_form_new_account');

        let accountElements = document.getElementsByClassName('form-group-account');
        let customerElements = document.getElementsByClassName('form-group-customer');

        // Listen to account checkbox.
        checkboxAccount.addEventListener('change', function() {
            for (let element of accountElements) {
                element.classList.toggle('hidden');
            }

            // Also toggle the customer checkbox.
            checkboxCustomer.parentElement.classList.toggle('hidden');
        });

        // Listen to customer checkbox.
        checkboxCustomer.addEventListener('change', function() {
            for (let element of customerElements) {
                element.classList.toggle('hidden');
            }
        });
    }
}
