import { Controller } from "@hotwired/stimulus";

/** Toggle value for key. */
export default class extends Controller {
    static targets = ["checkbox"];

    updateUrl;

    value;

    key;

    connect() {
        this.updateUrl = this.element.dataset.updateUrl;
        this.value = this.checkboxTarget.checked;
        this.key = this.element.dataset.key;
    }

    toggle() {
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
                key: this.key,
                value,
            }),
        })
            .then(async (resp) => {
                if (!resp.ok) {
                    this.checkboxTarget.checked = this.value;
                } else {
                    this.value = this.checkboxTarget.checked;
                }
            })
            .catch(() => {
                this.checkboxTarget.checked = this.value;
            });
    }
}
