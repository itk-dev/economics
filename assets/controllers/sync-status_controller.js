import {Controller} from "@hotwired/stimulus";
import Choices from "choices.js";

/**
 * Project sync controller.
 *
 * Sync a project
 */
export default class extends Controller {
    static targets = ["select", "button", "spinner", "ok", "error"];

    connect() {
        fetch("/admin/project/options", {
            method: "GET"
        }).then((resp) => resp.json()).then(
            (result) => {
                this.selectTarget.innerHTML = result.map(
                    ({id, title}) =>
                        `<option value="${id}">${title}</option>`).join("\n");
            }
        ).finally(() => {
                new Choices(this.selectTarget, {
                    allowHTML: true,
                    itemSelectText: "",
                })
            }
        );
    }

    submit() {
        if (this.selectTarget.value) {
            this.spinnerTarget.classList.remove("hidden");
            this.buttonTarget.disabled = true;
            this.okTarget.classList.add("hidden");
            this.errorTarget.classList.add("hidden");

            fetch(`/admin/project/${this.selectTarget.value}/sync`, {
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
                .then((response) => {
                    if (response.ok) {
                        this.okTarget.classList.remove("hidden");
                    } else {
                        this.errorTarget.classList.remove("hidden");
                    }
                })
                .catch(() => this.errorTarget.classList.remove("hidden"))
                .finally(() => {
                    this.buttonTarget.disabled = false;
                    this.spinnerTarget.classList.add("hidden");
                    this.buttonTarget.classList.remove("hidden");
                });
        }
    }
}
