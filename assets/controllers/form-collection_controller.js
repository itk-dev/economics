import { Controller } from "@hotwired/stimulus";

/**
 * Adds and removes rows in a Symfony form collection.
 *
 * New rows are stamped out of the collection's `data-prototype`, so anything the
 * form type puts on a field — a `data-controller`, a choice list — comes along
 * and initialises itself once the row is in the DOM.
 */
export default class extends Controller {
    static targets = ["container"];

    static values = { removeLabel: String };

    connect() {
        // Never decremented: a removed row must not free its index for reuse
        // within the same submit.
        this.index = this.rows().length;

        this.rows().forEach((row) => this.addRemoveButton(row));
    }

    add() {
        const { prototype } = this.containerTarget.dataset;

        if (!prototype) {
            return;
        }

        const wrapper = document.createElement("div");
        wrapper.innerHTML = prototype.replace(/__name__/g, String(this.index));
        this.index += 1;

        const row = wrapper.firstElementChild;

        if (!row) {
            return;
        }

        row.dataset.formCollectionRow = "true";
        this.containerTarget.appendChild(row);
        this.addRemoveButton(row);
    }

    remove(event) {
        const row = event.currentTarget.closest("[data-form-collection-row]");

        // Scoped to our own container, so a button can never take out a row
        // belonging to another collection on the page.
        if (row && this.containerTarget.contains(row)) {
            row.remove();
        }
    }

    rows() {
        return Array.from(this.containerTarget.children).map((row) => {
            const theRow = row;
            theRow.dataset.formCollectionRow = "true";

            return theRow;
        });
    }

    addRemoveButton(row) {
        if (row.querySelector("[data-form-collection-remove]")) {
            return;
        }

        const button = document.createElement("button");
        button.type = "button";
        button.className = "btn";
        button.textContent = this.removeLabelValue;
        button.dataset.formCollectionRemove = "true";
        button.dataset.action = "form-collection#remove";
        row.appendChild(button);
    }
}
