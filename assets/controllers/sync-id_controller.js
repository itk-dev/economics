import { Controller } from "@hotwired/stimulus";
import Choices from "choices.js";
import "choices.js/src/styles/choices.scss";

/**
 * Sync by id controller.
 */
export default class extends Controller {
    static targets = ["text", "button", "select"];

    updateEndpoint;
    optionsEndpoint;

    connect() {
        this.updateEndpoint = this.element.dataset.updateEndpoint;
        this.optionsEndpoint = this.element.dataset.optionsEndpoint;

        fetch(this.optionsEndpoint).then(res => res.json()).then(data => {
            data.forEach((element) => {
                const node = document.createElement('option');
                node.value = element.value;
                node.innerText = element.label;
                this.selectTarget.appendChild(node);
            });

            new Choices(this.selectTarget, {
                allowHTML: true,
                itemSelectText: "",
            })
        });
    }

    sync() {
        this.textTarget.innerText = "...";
        this.buttonTarget.disabled = true;

        this.buttonTarget.classList.remove('btn-danger');
        this.buttonTarget.classList.remove('btn-success');

        fetch(this.updateEndpoint + "?" + new URLSearchParams({
            id: this.selectTarget.value,
        }), {
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
                        this.textTarget.innerText = `⚠`;
                        this.buttonTarget.classList.add('btn-danger');
                    });
                } else {
                    this.textTarget.innerText = "✓";
                    this.buttonTarget.classList.add('btn-success');
                }
            })
            .catch((err) => {
                this.textTarget.innerText = `⚠`;
                this.buttonTarget.classList.add('btn-danger');
            })
            .finally(() => {
                this.buttonTarget.disabled = false;
            });
    }
}
