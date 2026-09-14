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

    static values = {
        placeholder: String,
        addLabel: String,
        noResults: String,
        noChoices: String,
    };

    connect() {
        if (
            !this.hasSelectTarget ||
            this.selectTarget.disabled ||
            this.selectTarget.choices
        ) {
            return;
        }

        // Read before Choices takes the options into its own store: this is the
        // only place the whole vocabulary is available as plain strings, and
        // the create affordance has to know what already exists.
        this.known = new Set(
            Array.from(this.selectTarget.options).map((option) =>
                option.value.trim().toLowerCase(),
            ),
        );

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
            // Choices.js renders its own English strings here otherwise.
            noResultsText: this.noResultsValue,
            noChoicesText: this.noChoicesValue,
        });

        // Kept on the element for parity with choices_controller.
        this.selectTarget.choices = this.choices;

        this.addHint();

        // Set once the user walks the dropdown, cleared as soon as they type
        // again: it is the only reliable signal that they mean the highlighted
        // suggestion rather than what they typed.
        this.navigated = false;

        this.onKeyDown = (event) => this.trackNavigation(event);
        this.onInput = (event) => {
            if (event.target === this.choices.input.element) {
                this.navigated = false;
                this.renderHint();
            }
        };

        this.element.addEventListener("keydown", this.onKeyDown, true);
        this.element.addEventListener("input", this.onInput, true);
    }

    /**
     * The "add this as a new role" row.
     *
     * A hint of our own rather than a choice fed in through `setChoices`: a
     * real choice is selectable as a literal value, and Choices highlights one
     * after every render — which is exactly what `exactMatchHighlighted` reads
     * to decide whether Enter belongs to the dropdown.
     *
     * Appended to the dropdown element, not to the list inside it: a re-render
     * clears only the list.
     */
    addHint() {
        this.hint = document.createElement("div");
        this.hint.className = "tags-add-hint";
        this.hint.hidden = true;

        // mousedown, not click: a click would land after the input had blurred
        // and Choices had already closed the dropdown underneath it.
        this.onHintMouseDown = (event) => {
            event.preventDefault();
            this.createRole(this.choices.input.value.trim());
        };
        this.hint.addEventListener("mousedown", this.onHintMouseDown);

        this.choices.dropdown.element.append(this.hint);
    }

    /**
     * Shown while the typed value is not one the vocabulary holds. A fuzzy hit
     * is no reason to hide it — Fuse matches "Fisk" against "Fakturering", and
     * the user still means a role that does not exist.
     */
    renderHint() {
        if (!this.choices || !this.hint) {
            return;
        }

        const term = this.choices.input.value.trim();
        const show = term !== "" && !this.known.has(term.toLowerCase());

        // textContent, never innerHTML: allowHTML is off for the same reason.
        this.hint.textContent = show
            ? this.addLabelValue.replace("%name%", term)
            : "";
        this.hint.hidden = !show;

        if (show && !this.choices.dropdown.isActive) {
            this.choices.showDropdown(true);
        }
    }

    disconnect() {
        this.element.removeEventListener("keydown", this.onKeyDown, true);
        this.element.removeEventListener("input", this.onInput, true);

        if (this.hint) {
            this.hint.removeEventListener("mousedown", this.onHintMouseDown);
            this.hint = null;
        }

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

        this.createRole(term);
    }

    createRole(term) {
        if (!this.choices || term === "") {
            return;
        }

        if (!this.isSelected(term)) {
            this.choices.setChoices(
                [{ value: term, label: term }],
                "value",
                "label",
                false,
            );
            this.choices.setChoiceByValue(term);
            // Part of the vocabulary now, so the hint stops offering it.
            this.known.add(term.toLowerCase());
        }

        this.choices.clearInput();
        // preventInputBlur: hideDropdown() otherwise blurs the input in a
        // requestAnimationFrame, which is what dropped focus out of the widget
        // after every role added.
        this.choices.hideDropdown(true);
        // .input.element, not .input: the latter is guarded by Choices' own
        // isFocussed flag, which is stale this early in the frame. Left closed
        // — Choices reopens the dropdown on the next printable character, and
        // reopening it here throws the whole vocabulary over a user who has
        // just committed a value.
        this.choices.input.element.focus();
        this.navigated = false;
        this.renderHint();
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
