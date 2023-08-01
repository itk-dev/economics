import { Controller } from '@hotwired/stimulus';

/**
 * Toggle child parent controller.
 *
 * Toggles 'hidden' class for parent and child targets.
 */
export default class extends Controller {
    static targets = ['parent', 'child', 'button', 'base'];

    displayParent = false;
    displayChildrenForParentIds = [];

    connect() {
        this.hideHiddenAssignees();

        this.parentTargets.forEach((target) => {
            target.classList.add('hidden');
        });

        this.childTargets.forEach((target) => {
            target.classList.add('hidden');
        });
    }

    hideHiddenAssignees() {
        const localStorageItem = localStorage.getItem('planningHiddenAssignees');
        const hiddenAssignees = localStorageItem ? JSON.parse(localStorageItem) : [];

        this.baseTargets.forEach((target) => {
            if (hiddenAssignees.includes(target.dataset.assignee)) {
                target.classList.add('hidden');
            } else {
                target.classList.remove('hidden');
            }
        })
    }

    toggleAssignee(event) {
        const localStorageItem = localStorage.getItem('planningHiddenAssignees');
        const hiddenAssignees = localStorageItem ? JSON.parse(localStorageItem) : [];

        const assigneeKey = this.element.dataset.assignee;

        if (assigneeKey === null) {
            return;
        }

        let newHiddenAssignees = null;

        if (hiddenAssignees.includes(assigneeKey)) {
            newHiddenAssignees = hiddenAssignees.filter((value) => value !== assigneeKey);
        } else {
            newHiddenAssignees = [...hiddenAssignees, assigneeKey];
        }

        localStorage.setItem('planningHiddenAssignees', JSON.stringify(newHiddenAssignees));

        this.hideHiddenAssignees();
    }

    toggleParent(event) {
        this.displayParent = !this.displayParent;

        event.currentTarget.innerText = this.displayParent ? '-' : '+';

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
                target.innerText = '+';
            })
        }
    }

    toggleChild(event) {
        const parentId = event.currentTarget.dataset.parentId;

        if (this.displayChildrenForParentIds.includes(parentId)) {
            this.displayChildrenForParentIds = this.displayChildrenForParentIds.filter((el) => el !== parentId);
            event.currentTarget.innerText = '+';

            this.childTargets.forEach((target) => {
                if (target.dataset.parentId === parentId) {
                    target.classList.add('hidden');
                }
            });
        } else {
            this.displayChildrenForParentIds.push(parentId);
            event.currentTarget.innerText = '-';

            this.childTargets.forEach((target) => {
                if (target.dataset.parentId === parentId) {
                    target.classList.remove('hidden');
                }
            });
        }
    }
}
