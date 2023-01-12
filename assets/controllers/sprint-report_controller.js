import { Controller } from '@hotwired/stimulus';
import Choices from "choices.js";
import 'choices.js/src/styles/choices.scss';

/**
 * Sprint report controller.
 *
 * Handles selections in dropdowns.
 */
export default class extends Controller {
    static targets = ['project', 'version'];

    connect() {
        console.log('connect');

        const choices = new Choices(this.projectTarget, {allowHTML: true});

        const choicesVersion = new Choices(this.versionTarget, {allowHTML: true});
    }

    submitForm() {

    }
}
