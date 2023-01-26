import {Controller} from '@hotwired/stimulus';
import Choices from "choices.js";
import 'choices.js/src/styles/choices.scss';

/**
 * Worklog select controller.
 */
export default class extends Controller {
    static targets = ['checkbox', 'toggleAll', 'spinner', 'result', 'submitButton'];
    invoiceEntryId = null;
    invoiceId = null;
    selectAll = true;
    submitting = false;

    connect() {
        this.invoiceEntryId = this.element.dataset.invoiceEntryId;
        this.invoiceId = this.element.dataset.invoiceId;
    }

    toggleAll() {
        console.log("selectAll", this.selectAll);

        this.checkboxTargets.forEach((target) => {
            target.checked = this.selectAll;
        });

        this.selectAll = !this.selectAll;
    }

    async submitForm(event) {
        event.preventDefault();
        event.stopPropagation();

        if (this.submitting) {
            return;
        }

        this.submitButtonTarget.classList.add('hidden');
        this.submitting = true;

        const values = [];

        this.checkboxTargets.forEach((target) => {
            const id = target.dataset.id;
            const checked = target.checked;

            values.push({id, checked});
        });

        const url = `/invoices/${this.invoiceId}/entries/${this.invoiceEntryId}/select_worklogs`;

        this.spinnerTarget.classList.remove('hidden');
        this.resultTarget.classList = ['hidden'];

        fetch(url, {
            method: 'POST',
            mode: 'same-origin',
            cache: 'no-cache',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json'
            },
            redirect: 'follow',
            referrerPolicy: 'no-referrer',
            body: JSON.stringify(values)
        }).then(async (resp) => {
            if (!resp.ok) {
                resp.json().then((err) => {
                    this.resultTarget.innerHTML = err.message;
                    this.resultTarget.classList.remove('hidden');
                    this.resultTarget.classList.add('text-red-500');
                });
            } else {
                this.resultTarget.innerHTML = 'Ok.';
                this.resultTarget.classList.remove('hidden');
                this.resultTarget.classList.add('text-green-500');
            }
        }).catch((err) => {
            this.resultTarget.innerHTML = err.message;
            this.resultTarget.classList.remove('hidden');
            this.resultTarget.classList.add('text-red-500');
        }).finally(() => {
            this.spinnerTarget.classList.add('hidden');
            this.submitting = false;
            this.submitButtonTarget.classList.remove('hidden');
        });
    }
}
