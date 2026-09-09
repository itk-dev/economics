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
        this.index = this.nextIndex();

        this.rows().forEach((row) => this.addRemoveButton(row));
    }

    /**
     * One past the highest index rendered, read off the entry ids Symfony emits.
     *
     * Not the row count: `delete_empty` drops an untouched row server-side, so a
     * form coming back from a failed validation can be keyed 0 and 2, and
     * counting rows would hand the next row an index that is already taken —
     * two rows would then submit under the same name and PHP would keep only
     * the last, silently replacing a contact the user had filled in.
     *
     * The row count stays the floor, so a row without a numbered id cannot drag
     * the index below what counting would have given.
     */
    nextIndex() {
        const rows = this.rows();

        return rows.reduce((next, row) => {
            const index = /_(\d+)$/.exec(row.id);

            return index ? Math.max(next, Number(index[1]) + 1) : next;
        }, rows.length);
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
        button.className = "button button-danger";
        button.textContent = this.removeLabelValue;
        button.dataset.formCollectionRemove = "true";
        button.dataset.action = "form-collection#remove";
        row.appendChild(button);
    }
}
