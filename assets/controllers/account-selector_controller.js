import {Controller} from '@hotwired/stimulus';
import Choices from "choices.js";
import 'choices.js/src/styles/choices.scss';

/**
 * Account selector controller.
 *
 * Loads choices from path.
 */
export default class extends Controller {
    static targets = ['field'];

    connect() {
        this.fieldTargets.forEach((target) => {
            const notDisabled = !target.disabled;

            if (notDisabled) {
                new Choices(target, {maxItemCount: 1, allowHTML: true, itemSelectText: ''});
            }
        });
    }
}