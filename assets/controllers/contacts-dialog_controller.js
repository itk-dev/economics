import { Controller } from "@hotwired/stimulus";

/**
 * Shows a row's contacts in a modal, so the agreement table keeps its width.
 *
 * The contacts are rendered with the row, so unlike worklog-details there is
 * nothing to fetch.
 */
export default class extends Controller {
    static targets = ["dialog"];

    open() {
        if (!this.dialogTarget.open) {
            this.dialogTarget.showModal();
        }
    }

    close() {
        this.dialogTarget.close();
    }

    /** A <dialog> click lands on the element itself only when the backdrop was hit. */
    clickOutside(event) {
        if (event.target === this.dialogTarget) {
            this.close();
        }
    }
}
