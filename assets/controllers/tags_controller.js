import { Controller } from "@hotwired/stimulus";
import Choices from "choices.js";
import "choices.js/src/styles/choices.scss";

/**
 * A tag widget over a <select multiple>: pick an existing option, or type a new
 * one and press Enter.
 *
 * Choices.js has no create-new of its own, so the typed term is added as a
 * choice and selected — the select then submits it like any other value, and the
 * server side creates the missing role.
 *
 * Attached per element rather than through a container, so a row added from a
 * collection prototype initialises itself.
 */
export default class extends Controller {
    connect() {
        if (this.element.disabled || this.element.choices) {
            return;
        }

        this.searchTerm = "";

        this.choices = new Choices(this.element, {
            allowHTML: true,
            itemSelectText: "",
            removeItems: true,
            removeItemButton: true,
            duplicateItemsAllowed: false,
        });

        // Kept on the element for parity with choices_controller.
        this.element.choices = this.choices;

        this.onSearch = (event) => {
            this.searchTerm = event.detail.value ?? "";
        };
        this.onKeyDown = (event) => this.createOnEnter(event);

        this.element.addEventListener("search", this.onSearch);
        this.element.addEventListener("change", () => {
            this.searchTerm = "";
        });
        this.choices.input.element.addEventListener("keydown", this.onKeyDown);
    }

    disconnect() {
        this.element.removeEventListener("search", this.onSearch);

        if (this.choices) {
            this.choices.destroy();
            this.element.choices = null;
        }
    }

    createOnEnter(event) {
        if (event.key !== "Enter") {
            return;
        }

        const term = this.searchTerm.trim();

        if (term === "" || this.hasChoice(term)) {
            return;
        }

        // Stops Choices.js from selecting whatever the dropdown had highlighted,
        // and stops the browser from submitting the form.
        event.preventDefault();
        event.stopPropagation();

        this.choices.setChoices(
            [{ value: term, label: term, selected: true }],
            "value",
            "label",
            false,
        );
        this.choices.clearInput();
        this.searchTerm = "";
    }

    hasChoice(term) {
        const wanted = term.toLowerCase();

        return Array.from(this.element.options).some(
            (option) => option.value.toLowerCase() === wanted,
        );
    }
}
