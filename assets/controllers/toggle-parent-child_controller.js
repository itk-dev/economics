import { Controller } from '@hotwired/stimulus';

/**
 * Toggle child parent controller.
 *
 * Toggles 'hidden' class for parent and child targets.
 */
export default class extends Controller {
    static targets = ['parent', 'child', 'button'];

    displayParent = false;
    displayChildrenForParentIds = [];

    svgExpand = `<svg class="toggle-parent-child-expand" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15"></path>
                     </svg>`;
    svgCollapse = `<svg class="toggle-parent-child-collapse" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                         <path stroke-linecap="round" stroke-linejoin="round" d="M9 9V4.5M9 9H4.5M9 9L3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5l5.25 5.25"></path>
                       </svg>`;

    connect() {
        this.parentTargets.forEach((target) => {
            target.classList.add('hidden');
        });

        this.childTargets.forEach((target) => {
            target.classList.add('hidden');
        });
    }

    toggleParent(event) {
        this.displayParent = !this.displayParent;

        event.currentTarget.innerHTML = this.displayParent ? this.svgCollapse : this.svgExpand;

        if (this.displayParent) {
            this.parentTargets.forEach((target) => {
                target.classList.remove('hidden');
            });
        } else {
            this.parentTargets.forEach((target) => {
                target.classList.add('hidden');
            });

            this.displayChildrenForParentIds = [];

            this.childTargets.forEach((target) => {
                target.classList.add('hidden');
            });

            this.buttonTargets.forEach((target) => {
                target.innerHTML = this.svgExpand;
            })
        }
    }

    toggleChild(event) {
        const parentId = event.currentTarget.dataset.parentId;

        if (this.displayChildrenForParentIds.includes(parentId)) {
            this.displayChildrenForParentIds = this.displayChildrenForParentIds.filter((el) => el !== parentId);
            event.currentTarget.innerHTML = this.svgExpand;

            this.childTargets.forEach((target) => {
                if (target.dataset.parentId === parentId) {
                    target.classList.add('hidden');
                }
            });
        } else {
            this.displayChildrenForParentIds.push(parentId);
            event.currentTarget.innerHTML = this.svgCollapse;

            this.childTargets.forEach((target) => {
                if (target.dataset.parentId === parentId) {
                    target.classList.remove('hidden');
                }
            });
        }
    }
}
