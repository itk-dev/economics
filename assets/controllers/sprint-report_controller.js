import {Controller} from '@hotwired/stimulus';
import Choices from "choices.js";
import 'choices.js/src/styles/choices.scss';

/**
 * Sprint report controller.
 *
 * Handles selections in dropdowns.
 */
export default class extends Controller {
    static targets = ['project', 'version', 'form', 'loading', 'content', 'select', 'budget', 'finishedPercentage', 'spentHours', 'projectTotalForecast', 'overUnderIndex', 'budgetSubmit'];

    connect() {
        this.calculateForecasts = this.calculateForecasts.bind(this);

        // Initialize choices.js
        new Choices(this.projectTarget, {allowHTML: true, itemSelectText: ''});
        new Choices(this.versionTarget, {allowHTML: true, itemSelectText: ''});

        this.loadingTarget.classList.add('hidden');
        this.contentTarget.classList.remove('hidden');
        this.selectTarget.classList.remove('hidden');

        if (this.projectTarget.value && this.versionTarget.value) {
            this.budgetTarget.addEventListener('change', this.calculateForecasts);
            this.finishedPercentageTarget.addEventListener('change', this.calculateForecasts);
        }
    }

    submitFormProjectId() {
        this.versionTarget.value = null;
        this.submitForm();
    }

    submitForm() {
        this.contentTarget.classList.add('hidden');
        this.selectTarget.classList.add('hidden');
        this.loadingTarget.classList.remove('hidden');

        this.formTarget.submit();
    }

    submitBudget() {
        const budget = parseFloat(this.budgetTarget.value);
        const projectId = this.budgetTarget.dataset.projectId;
        const versionId = this.budgetTarget.dataset.versionId;

        const text = this.budgetSubmitTarget.innerHTML;
        this.budgetSubmitTarget.innerHTML = '...';
        this.budgetSubmitTarget.setAttribute('disabled', 'disabled');
        this.budgetSubmitTarget.setAttribute('class', 'btn-disabled');

        fetch('/sprint-report/budget', {
            method: 'POST',
            mode: 'same-origin',
            cache: 'no-cache',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                projectId,
                versionId,
                budget
            })
        })
            .then(() => {
                this.budgetSubmitTarget.setAttribute('class', 'btn-success')
            })
            .catch((err) => {
                this.budgetSubmitTarget.setAttribute('class', 'btn-error');
            })
            .finally(() => {
                this.budgetSubmitTarget.removeAttribute('disabled');
                this.budgetSubmitTarget.innerHTML = text;
            });
    }

    calculateForecasts() {
        const spentHours = parseFloat(this.spentHoursTarget.innerHTML);
        const finishedDegree = parseFloat(this.finishedPercentageTarget.value);
        const sales_budget = parseFloat(this.budgetTarget.value);

        let forecast = null;
        let over_under = null;

        if (finishedDegree > 0) {
            forecast = spentHours / finishedDegree * 100;
            this.projectTotalForecastTarget.innerHTML = '' + forecast.toFixed(2);
        }
        if (sales_budget > 0 && forecast != null) {
            over_under = forecast / sales_budget;
            this.overUnderIndexTarget.innerHTML = '' + over_under.toFixed(2);
        }
    }
}
