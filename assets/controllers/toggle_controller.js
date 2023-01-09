import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['parent', 'child', 'button'];

    displayParent = false;
    displayChildrenForParentIds = [];

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
