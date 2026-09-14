import { Controller } from "@hotwired/stimulus";

/**
 * Worklog details controller.
 *
 * Fetches the worklogs behind a workload report cell and shows them in a modal,
 * so the report table keeps its horizontal scroll position.
 */
export default class extends Controller {
    static targets = ["dialog", "content"];

    static values = { loadingText: String, errorText: String };

    // Identifies the cell whose response we are still interested in. A slow
    // response for a previously clicked cell must not overwrite a newer one.
    requestId = 0;

    open(event) {
        const { url } = event.currentTarget.dataset;

        if (!url) {
            return;
        }

        this.requestId += 1;
        const { requestId } = this;

        this.contentTarget.innerHTML = `<p>${this.loadingTextValue}</p>`;

        if (!this.dialogTarget.open) {
            this.dialogTarget.showModal();
        }

        fetch(url, { headers: { "X-Requested-With": "XMLHttpRequest" } })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`Unexpected status ${response.status}`);
                }

                return response.text();
            })
            .then((html) => {
                if (this.isStale(requestId)) {
                    return;
                }

                this.contentTarget.innerHTML = html;
            })
            .catch(() => {
                if (this.isStale(requestId)) {
                    return;
                }

                this.contentTarget.innerHTML = `<p class="alert-danger">${this.errorTextValue}</p>`;
            });
    }

    close() {
        // Abandons any in-flight response for the cell that was open.
        this.requestId += 1;
        this.dialogTarget.close();
        this.contentTarget.innerHTML = "";
    }

    /** A <dialog> click lands on the element itself only when the backdrop was hit. */
    clickOutside(event) {
        if (event.target === this.dialogTarget) {
            this.close();
        }
    }

    isStale(requestId) {
        return requestId !== this.requestId || !this.dialogTarget.open;
    }
}
