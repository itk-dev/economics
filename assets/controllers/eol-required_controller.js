import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["checkbox", "dateField"];

    connect() {
        this.toggle();
    }

    toggle() {
        this.dateFieldTarget.required = this.checkboxTarget.checked;
    }
}
