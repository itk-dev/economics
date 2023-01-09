import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['project', 'issue', 'button'];

    displayProject = false;
    displayIssuesForProjectIds = [];

    connect() {
        this.projectTargets.forEach((target) => {
            target.classList.add('hidden');
        });
        this.issueTargets.forEach((target) => {
            target.classList.add('hidden');
        });
    }

    toggleAssignee(event) {
        this.displayProject = !this.displayProject;

        event.currentTarget.innerText = this.displayProject ? '-' : '+';

        if (this.displayProject) {
            this.projectTargets.forEach((target) => {
                target.classList.remove('hidden');
            });
        } else {
            this.projectTargets.forEach((target) => {
                target.classList.add('hidden');
            });

            this.displayIssuesForProjectIds = [];

            this.issueTargets.forEach((target) => {
                target.classList.add('hidden');
            });

            this.buttonTargets.forEach((target) => {
                target.innerText = '+';
            })
        }
    }

    toggleProject(event) {
        const projectId = event.currentTarget.dataset.projectId;

        if (this.displayIssuesForProjectIds.includes(projectId)) {
            this.displayIssuesForProjectIds = this.displayIssuesForProjectIds.filter((el) => el !== projectId);
            event.currentTarget.innerText = '+';

            this.issueTargets.forEach((target) => {
                if (target.dataset.projectId === projectId) {
                    target.classList.add('hidden');
                }
            });
        } else {
            this.displayIssuesForProjectIds.push(projectId);
            event.currentTarget.innerText = '-';

            this.issueTargets.forEach((target) => {
                if (target.dataset.projectId === projectId) {
                    target.classList.remove('hidden');
                }
            });
        }
    }
}
