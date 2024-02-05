import {Controller} from '@hotwired/stimulus';
import Choices from "choices.js";
import 'choices.js/src/styles/choices.scss';

export default class extends Controller {
    static targets = ['select'];

    updateUrl;
    selected;

    connect() {
        this.updateUrl = this.element.dataset.updateUrl;
        new Choices(this.selectTarget, {allowHTML: true, itemSelectText: '', removeItems: true, removeItemButton: true});
        this.selected = Array.from(this.selectTarget.querySelectorAll("option:checked"),e=>e.value);
    }

    onChange() {
        const newSelected = Array.from(this.selectTarget.querySelectorAll("option:checked"),e=>e.value);

        fetch(this.updateUrl, {
            method: 'POST',
            mode: 'same-origin',
            cache: 'no-cache',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json'
            },
            redirect: 'follow',
            referrerPolicy: 'no-referrer',
            body: JSON.stringify({
                selected: newSelected
            })
        }).then(async (resp) => {
            if (!resp.ok) {
                console.log("TODO: handle error")
            } else {
                console.log("TODO: Handle success")
            }
        }).catch(() => {
            console.error("TODO: Handle error")
        });
    }
}
