import {Controller} from '@hotwired/stimulus';

/**
 * Entry select controller.
 */
export default class extends Controller {
    static targets = ['checkbox', 'toggleAll', 'spinner', 'result', 'submitButton'];
    submitEndpoint = null;
    selectAll = true;
    submitting = false;
    dirtyEntrys = new Set();

    connect() {
        this.submitEndpoint = this.element.dataset.submitEndpoint;
    }

    toggleAll() {
        this.checkboxTargets.forEach((target) => {
            target.checked = this.selectAll;
            this.dirtyEntrys.add(target.dataset.id);
        });

        this.selectAll = !this.selectAll;
    }

    checkboxClick(event) {
        const entryId = event.params.id;
        this.dirtyEntrys.add(entryId.toString());
    }

    async submitFormRedirectWithIds(event) {
        event.preventDefault();
        event.stopPropagation();

        const values = this.checkboxTargets.reduce((accumulator, target) => {
            const id = target.dataset.id;
            const checked = target.checked;

            if (this.dirtyEntrys.has(id) && checked) {
                accumulator.push(id);
            }

            return accumulator;
        }, []);

        let params = new URLSearchParams({
            ids: values
        })

        window.location.href = (this.submitEndpoint + "?" + params);
    }

    async submitForm(event) {
        event.preventDefault();
        event.stopPropagation();

        if (this.submitting) {
            return;
        }

        this.submitButtonTarget.classList.add('hidden');
        this.submitting = true;

        const values = this.checkboxTargets.reduce((accumulator, target) => {
            const id = target.dataset.id;
            const checked = target.checked;

            if (this.dirtyEntrys.has(id)) {
                accumulator.push({id, checked});
            }

            return accumulator;
        }, []);

        this.spinnerTarget.classList.remove('hidden');
        this.resultTarget.classList = ['hidden'];

        fetch(this.submitEndpoint, {
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
