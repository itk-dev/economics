import { Controller } from '@hotwired/stimulus';
import Choices from "choices.js";
import 'choices.js/src/styles/choices.scss';

/**
 * Sprint report controller.
 *
 * Handles selections in dropdowns.
 */
export default class extends Controller {
    static targets = ['project', 'version', 'form', 'loading', 'content', 'select', 'budget', 'finishedPercentage', 'spentHours', 'projectTotalForecast', 'overUnderIndex'];
    spentHours = 0;

    connect() {
        this.calculateForecasts = this.calculateForecasts.bind(this);

        // Initialize choices.js
        new Choices(this.projectTarget, {allowHTML: true});
        new Choices(this.versionTarget, {allowHTML: true});

        this.loadingTarget.classList.add('hidden');
        this.contentTarget.classList.remove('hidden');
        this.selectTarget.classList.remove('hidden');

        this.spentHours = parseFloat(this.spentHoursTarget.innerHTML);

        this.budgetTarget.addEventListener('change', this.calculateForecasts);
        this.finishedPercentageTarget.addEventListener('change', this.calculateForecasts);
    }

    submitForm() {
        this.contentTarget.classList.add('hidden');
        this.selectTarget.classList.add('hidden');
        this.loadingTarget.classList.remove('hidden');

        this.formTarget.submit();
    }

    async submitBudget() {
        const budget = parseFloat(this.budgetTarget.value);
        const projectId = this.budgetTarget.dataset.projectId;
        const versionId = this.budgetTarget.dataset.versionId;

        const response = await fetch('/sprint-report/budget', {
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
        });

        return response.json();
    }

    calculateForecasts() {
        const finishedDegree = parseFloat(this.finishedPercentageTarget.value);
        const sales_budget = parseFloat(this.budgetTarget.value);

        let forecast = null;
        let over_under = null;

        if (finishedDegree > 0) {
            forecast = this.spentHours / finishedDegree * 100;
            this.projectTotalForecastTarget.innerHTML = '' + forecast.toFixed(2);
        }
        if (sales_budget > 0 && forecast != null) {
            over_under = forecast / sales_budget;
            this.overUnderIndexTarget.innerHTML = '' + over_under.toFixed(2);
        }
    }
}
