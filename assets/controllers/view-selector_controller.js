import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["select"];

    /* eslint-disable-next-line class-methods-use-this */
    select(event) {
        event.preventDefault();
        event.stopPropagation();

        const newDefaultView =
            event.target.options[event.target.selectedIndex].value;
        const { search } = window.location;

        const params = new URLSearchParams(search);
        params.set("view", newDefaultView);

        window.location.replace(`${window.location.pathname}?${params}`);
    }
}
