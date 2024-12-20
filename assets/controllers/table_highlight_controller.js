import { Controller } from "@hotwired/stimulus";

/**
 * Table highlight controller.
 *
 * Highlights the `<thead>` and first cell (`<th>` or `<td>`) of the row when
 * hovering over a cell.
 */
export default class extends Controller {
    connect() {
        this.element.addEventListener(
            "mouseenter",
            (event) => this.highlight(event),
            true,
        );
        this.element.addEventListener(
            "mouseleave",
            (event) => this.clearHighlights(event),
            true,
        );
    }

    /**
     * Handles the highlighting of the appropriate `<thead>` column and row
     * header.
     *
     * @param {MouseEvent} event
     */
    highlight(event) {
        // Only run if hovering a `<td>` (and not child elements like <span> or <a>)
        if (event.target.tagName !== "TD") return;

        const cell = event.target; // The actual hovered cell

        // Find the index of the hovered cell (column index)
        const cellIndex = Array.from(cell.parentNode.children).indexOf(cell);

        // Highlight the corresponding column header (<th>) in the `<thead>`
        const columnHeader = this.element.querySelector(
            `thead th:nth-child(${cellIndex + 1})`,
        );
        if (columnHeader) columnHeader.classList.add("highlight-column");

        // Highlight the first cell in the row (supports both <td> and <th>)
        const rowStartCell = cell.parentNode.querySelector(
            "th:first-child, td:first-child",
        );
        if (rowStartCell) rowStartCell.classList.add("highlight-row");
    }

    /**
     * Clears all highlights when leaving a cell.
     *
     * @param {MouseEvent} event
     */
    clearHighlights(event) {
        // Only run if leaving a `<td>` (and not child elements)
        if (event.target.tagName !== "TD") return;

        // Remove the highlight class from all highlighted elements
        this.element.querySelectorAll(".highlight-column").forEach((header) => {
            header.classList.remove("highlight-column");
        });

        this.element
            .querySelectorAll(".highlight-row")
            .forEach((rowStartCell) => {
                rowStartCell.classList.remove("highlight-row");
            });
    }
}
