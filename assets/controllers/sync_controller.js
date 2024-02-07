import { Controller } from "@hotwired/stimulus";

/**
 * Project sync controller.
 *
 * Sync a project
 */
export default class extends Controller {
    static targets = ["text", "button"];

    updateUrl;

    connect() {
        this.updateUrl = this.element.dataset.updateUrl;
    }

    sync() {
        this.textTarget.innerText = "Syncing...";
        this.buttonTarget.style.display = "none";

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
        })
            .then(async (resp) => {
                if (!resp.ok) {
                    resp.json().then((err) => {
                        this.textTarget.innerText = `failed: ${err.message}`;
                    });
                } else {
                    this.textTarget.innerText = "ok";
                }
            })
            .catch((err) => {
                this.textTarget.innerText = `failed${err.message}`;
            })
            .finally(() => {
                this.buttonTarget.style.display = "block";
            });
    }
}
