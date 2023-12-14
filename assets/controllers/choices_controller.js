import {Controller} from '@hotwired/stimulus';
import Choices from "choices.js";
import 'choices.js/src/styles/choices.scss';

/**
 * Activates choices.js for each element with choices target.
 */
export default class extends Controller {
    static targets = ['choices'];

    connect() {
        this.choicesTargets.forEach((target) => {
            new Choices(target, {allowHTML: true, itemSelectText: ''});
        })
    }
}
