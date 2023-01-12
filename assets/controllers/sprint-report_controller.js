import { Controller } from '@hotwired/stimulus';
import Choices from "choices.js";
import 'choices.js/src/styles/choices.scss';

/**
 * Sprint report controller.
 *
 * Handles selections in dropdowns.
 */
export default class extends Controller {
    static targets = ['project', 'version', 'form', 'loading', 'content', 'select'];

    connect() {
        // Initialize choices.js
        new Choices(this.projectTarget, {allowHTML: true});
        new Choices(this.versionTarget, {allowHTML: true});

        this.loadingTarget.classList.add('hidden');
        this.contentTarget.classList.remove('hidden');
        this.selectTarget.classList.remove('hidden');
    }

    submitForm() {
        this.contentTarget.classList.add('hidden');
        this.selectTarget.classList.add('hidden');
        this.loadingTarget.classList.remove('hidden');

        this.formTarget.submit();
    }
}
