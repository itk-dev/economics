import { Controller } from "@hotwired/stimulus";

/** Scroll to active sprint in planning view */
export default class extends Controller {
    static targets = ["column"];

    connect() {
        const activeColumn = this.columnTargets.find((el) => el.dataset.active);

        if (!activeColumn) {
            return;
        }

        const activeIndex = activeColumn.dataset.index;
        const scrollToIndex = Math.max(0, activeIndex - 1);
        const scrollToColumn = this.columnTargets.find(
            (el) => el.dataset.index == scrollToIndex,
        );
        const firstColumn = this.columnTargets.find(
            (el) => el.dataset.index == 1,
        );

        if (!scrollToColumn || !firstColumn) {
            return;
        }

        const { x } = scrollToColumn.getBoundingClientRect();
        const { x: firstColumnX } = firstColumn.getBoundingClientRect();

        scrollContainer.scrollTo(x - firstColumnX, 0);
    }
}
