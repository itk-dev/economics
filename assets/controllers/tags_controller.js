import { Controller } from "@hotwired/stimulus";
import Choices from "choices.js";
import "choices.js/src/styles/choices.scss";

/**
 * A tag widget over a <select multiple>: pick an existing option, or type a new
 * one and press Enter.
 *
 * Choices.js will not create a value of its own on a select — `_onEnterKey`
 * adds an item only for text elements — and it binds its own keydown on the
 * outer container in the capture phase. So the Enter key is intercepted one
 * level higher, on this controller's element, which captures first.
 *
 * Mounted on the form row rather than the select, because Choices.js wraps the
 * select and a controller on a moving element is disconnected and reconnected
 * indefinitely. The row is inside the collection entry, so a row stamped from
 * the prototype still initialises itself.
 */
export default class extends Controller {
    static targets = ["select"];

    static values = { placeholder: String };

    connect() {
        if (
            !this.hasSelectTarget ||
            this.selectTarget.disabled ||
            this.selectTarget.choices
        ) {
            return;
        }

        this.choices = new Choices(this.selectTarget, {
            // Role names are free text a user typed, and Choices writes labels
            // with innerHTML when this is on. Nothing here wants HTML in a label.
            allowHTML: false,
            itemSelectText: "",
            removeItems: true,
            removeItemButton: true,
            duplicateItemsAllowed: false,
            // Without it an empty field is a blank box with no hint that it
            // takes typing.
            placeholder: true,
            placeholderValue: this.placeholderValue,
        });

        // Kept on the element for parity with choices_controller.
        this.selectTarget.choices = this.choices;

        // Set once the user walks the dropdown, cleared as soon as they type
        // again: it is the only reliable signal that they mean the highlighted
        // suggestion rather than what they typed.
        this.navigated = false;

        this.onKeyDown = (event) => this.trackNavigation(event);
        this.onInput = (event) => {
            if (event.target === this.choices.input.element) {
                this.navigated = false;
            }
        };

        this.element.addEventListener("keydown", this.onKeyDown, true);
        this.element.addEventListener("input", this.onInput, true);
    }

    disconnect() {
        this.element.removeEventListener("keydown", this.onKeyDown, true);
        this.element.removeEventListener("input", this.onInput, true);

        if (this.choices) {
            this.choices.destroy();
            this.choices = null;
        }

        // Cleared as well, or a reconnect finds the destroyed instance still on
        // the element, bails out of connect(), and leaves a bare <select> with
        // no way to type a new role.
        if (this.hasSelectTarget) {
            this.selectTarget.choices = null;
        }
    }

    trackNavigation(event) {
        if (
            ["ArrowUp", "ArrowDown", "PageUp", "PageDown"].includes(event.key)
        ) {
            this.navigated = true;

            return;
        }

        this.createOnEnter(event);
    }

    createOnEnter(event) {
        if (event.key !== "Enter" || !this.choices) {
            return;
        }

        const term = this.choices.input.value.trim();

        if (term === "") {
            return;
        }

        // Choices searches fuzzily through Fuse.js, so "Fisk" highlights
        // "Fakturering". A highlight alone is therefore no reason to hand Enter
        // over — only an exact match, or the user having walked the list.
        if (this.navigated || this.exactMatchHighlighted(term)) {
            return;
        }

        // Also stops the browser submitting the whole agreement form.
        event.preventDefault();
        event.stopPropagation();

        if (!this.isSelected(term)) {
            this.choices.setChoices(
                [{ value: term, label: term }],
                "value",
                "label",
                false,
            );
            this.choices.setChoiceByValue(term);
        }

        this.choices.clearInput();
        this.choices.hideDropdown();
        this.navigated = false;
    }

    exactMatchHighlighted(term) {
        if (!this.choices.dropdown.isActive) {
            return false;
        }

        const highlighted =
            this.choices.dropdown.element.querySelector(".is-highlighted");

        return (
            !!highlighted &&
            (highlighted.dataset.value ?? "").toLowerCase() ===
                term.toLowerCase()
        );
    }

    isSelected(term) {
        const wanted = term.toLowerCase();

        return (this.choices.getValue(true) || []).some(
            (value) => String(value).toLowerCase() === wanted,
        );
    }
}
