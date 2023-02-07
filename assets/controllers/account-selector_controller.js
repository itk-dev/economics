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
            new Choices(target, {maxItemCount: 1, allowHTML: true});
        });

        fetch('/accounts/choices').then(
            (resp) => resp.json()
        ).then((choices) => {
            console.log(choices);
        });
    }
}
