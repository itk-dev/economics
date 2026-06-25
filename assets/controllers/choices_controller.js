import { Controller } from "@hotwired/stimulus";
import Choices from "choices.js";
import "choices.js/src/styles/choices.scss";

/** Activates choices.js for each element with choices target. */
export default class extends Controller {
    static targets = ["choices"];

    connect() {
        this.choicesTargets.forEach((target) => {
            const theTarget = target;
            const notDisabled = !theTarget.disabled;

            if (notDisabled) {
                // Keep the instance on the element so other controllers can drive
                // the widget (e.g. autofill-receiver-account#update).
                theTarget.choices = new Choices(theTarget, {
                    allowHTML: true,
                    itemSelectText: "",
                    removeItems: true,
                    removeItemButton: true,
                });
            }
        });
    }
}
