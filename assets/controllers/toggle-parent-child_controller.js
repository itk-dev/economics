import { Controller } from "@hotwired/stimulus";

/**
 * Toggle child parent controller.
 *
 * Toggles 'hidden' class for parent and child targets.
 */
export default class extends Controller {
    static targets = ["parent", "child", "button"];

    displayParent = false;

    displayChildrenForParentIds = [];


    svgExpand = `<svg class="svg-inline--fa fa-caret-right" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="caret-right" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 512" data-fa-i2svg=""><path fill="currentColor" d="M246.6 278.6c12.5-12.5 12.5-32.8 0-45.3l-128-128c-9.2-9.2-22.9-11.9-34.9-6.9s-19.8 16.6-19.8 29.6l0 256c0 12.9 7.8 24.6 19.8 29.6s25.7 2.2 34.9-6.9l128-128z"></path></svg>`;

    svgCollapse = `<svg class="svg-inline--fa fa-caret-down" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="caret-down" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512" data-fa-i2svg=""><path fill="currentColor" d="M137.4 374.6c12.5 12.5 32.8 12.5 45.3 0l128-128c9.2-9.2 11.9-22.9 6.9-34.9s-16.6-19.8-29.6-19.8L32 192c-12.9 0-24.6 7.8-29.6 19.8s-2.2 25.7 6.9 34.9l128 128z"></path></svg>`;

    connect() {
        this.parentTargets.forEach((target) => {
            target.classList.add("hidden");
        });

        this.childTargets.forEach((target) => {
            target.classList.add("hidden");
        });
    }

    toggleParent(event) {
        this.displayParent = !this.displayParent;

        const { currentTarget } = event;

        currentTarget.innerHTML = this.displayParent
            ? this.svgCollapse
            : this.svgExpand;

        if (this.displayParent) {
            this.parentTargets.forEach((target) => {
                target.classList.remove("hidden");
            });
        } else {
            this.parentTargets.forEach((target) => {
                target.classList.add("hidden");
            });

            this.displayChildrenForParentIds = [];

            this.childTargets.forEach((target) => {
                target.classList.add("hidden");
            });

            this.buttonTargets.forEach((target) => {
                const theTarget = target;
                theTarget.innerHTML = this.svgExpand;
            });
        }
    }

    toggleChild(event) {
        const { currentTarget } = event;
        const { parentId } = currentTarget.dataset;

        if (this.displayChildrenForParentIds.includes(parentId)) {
            this.displayChildrenForParentIds =
                this.displayChildrenForParentIds.filter(
                    (el) => el !== parentId,
                );

            currentTarget.innerHTML = this.svgExpand;

            this.childTargets.forEach((target) => {
                if (target.dataset.parentId === parentId) {
                    target.classList.add("hidden");
                }
            });
        } else {
            this.displayChildrenForParentIds.push(parentId);
            currentTarget.innerHTML = this.svgCollapse;

            this.childTargets.forEach((target) => {
                if (target.dataset.parentId === parentId) {
                    target.classList.remove("hidden");
                }
            });
        }
    }
}
