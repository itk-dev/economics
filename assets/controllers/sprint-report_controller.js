import { Controller } from "@hotwired/stimulus";

/**
 * Sprint report controller.
 *
 * Handles selections in dropdowns.
 */
export default class extends Controller {
    static targets = [
        "project",
        "version",
        "form",
        "loading",
        "content",
        "select",
        "budget",
        "finishedPercentage",
        "spentHours",
        "projectTotalForecast",
        "overUnderIndex",
        "budgetSubmit",
    ];

    budgetEndpoint = null;

    connect() {
        this.budgetEndpoint = this.element.dataset.budgetUpdateEndpoint;

        this.calculateForecasts = this.calculateForecasts.bind(this);

        if (this.projectTarget.value && this.versionTarget.value) {
            this.budgetTarget.addEventListener(
                "change",
                this.calculateForecasts,
            );
            this.finishedPercentageTarget.addEventListener(
                "change",
                this.calculateForecasts,
            );
        }
    }

    submitBudget() {
        const budget = parseFloat(this.budgetTarget.value);
        const { projectId } = this.budgetTarget.dataset;
        const { versionId } = this.budgetTarget.dataset;

        const text = this.budgetSubmitTarget.innerHTML;
        this.budgetSubmitTarget.innerHTML = "...";
        this.budgetSubmitTarget.setAttribute("disabled", "disabled");
        this.budgetSubmitTarget.setAttribute("class", "btn-disabled");

        fetch(this.budgetEndpoint, {
            method: "POST",
            mode: "same-origin",
            cache: "no-cache",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify({
                projectId,
                versionId,
                budget,
            }),
        })
            .then(() => {
                this.budgetSubmitTarget.setAttribute("class", "btn-success");
            })
            .catch(() => {
                this.budgetSubmitTarget.setAttribute("class", "btn-error");
            })
            .finally(() => {
                this.budgetSubmitTarget.removeAttribute("disabled");
                this.budgetSubmitTarget.innerHTML = text;
            });
    }

    calculateForecasts() {
        const spentHours = parseFloat(this.spentHoursTarget.innerHTML);
        const finishedDegree = parseFloat(this.finishedPercentageTarget.value);
        const salesBudget = parseFloat(this.budgetTarget.value);

        let forecast = null;
        let overUnder = null;

        if (finishedDegree > 0) {
            forecast = (spentHours / finishedDegree) * 100;
            this.projectTotalForecastTarget.innerHTML = `${forecast.toFixed(2)}`;
        }

        if (salesBudget > 0 && forecast != null) {
            overUnder = forecast / salesBudget;
            this.overUnderIndexTarget.innerHTML = `${overUnder.toFixed(2)}`;
        }
    }
}
