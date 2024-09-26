import { Controller } from "@hotwired/stimulus";

/** Toggle value for key. */
export default class extends Controller {
    connect() {
        this.element.classList.remove("loading");
    }
}
