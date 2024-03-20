import { Controller } from "@hotwired/stimulus";
import Choices from "choices.js";
import "choices.js/src/styles/choices.scss";

export default class extends Controller {
    static targets = ["select"];

    updateUrl;

    selected;

    connect() {
        this.updateUrl = this.element.dataset.updateUrl;

        this.selected = Array.from(
            this.selectTarget.querySelectorAll("option:checked"),
            (e) => e.value,
        );

        /* eslint-disable-next-line no-new */
        new Choices(this.selectTarget, {
            allowHTML: true,
            itemSelectText: "",
            removeItems: true,
            removeItemButton: true,
        });
    }

    onChange() {
        const newSelected = Array.from(
            this.selectTarget.querySelectorAll("option:checked"),
            (e) => e.value,
        );

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
                selected: newSelected,
            }),
        })
            .then(async (resp) => {
                if (!resp.ok) {
                    // TODO: Handle error.
                } else {
                    // TODO: Handle success.
                }
            })
            .catch(() => {
                // TODO: Handle error.
            });
    }
}
