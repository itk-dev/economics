import { Controller } from "@hotwired/stimulus";

/**
 * Project include controller.
 *
 * Creates a toggle
 */
export default class extends Controller {
    static targets = ["checkbox", "text"];

    updateUrl;

    value;

    connect() {
        this.updateUrl = this.element.dataset.updateUrl;
        this.value = this.checkboxTarget.checked;
    }

    toggle() {
        this.textTarget.innerText = "";

        const value = this.checkboxTarget.checked;

        fetch(this.updateUrl, {
            method: "POST",
            mode: "same-origin",
            cache: "no-cache",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/json",
            },
            redirect: "follow",
            referrerPolicy: "no-referrer",
            body: JSON.stringify({
                value,
            }),
        })
            .then(async (resp) => {
                if (!resp.ok) {
                    resp.json().then((err) => {
                        this.checkboxTarget.checked = this.value;
                        this.textTarget.innerText = `failed: ${err.message}`;
                    });
                } else {
                    this.value = this.checkboxTarget.checked;
                }
            })
            .catch((err) => {
                this.checkboxTarget.checked = this.value;
                this.textTarget.innerText = `failed${err.message}`;
            })
            .finally(() => {});
    }
}
