import { Controller } from "@hotwired/stimulus";

/** Autofills the invoice material number from the selected client's type. */
export default class extends Controller {
    static targets = ["client", "material"];

    static values = { map: Object };

    update() {
        const materialNumber = this.mapValue[this.clientTarget.value];

        if (!materialNumber) {
            return;
        }

        this.materialTarget.value = materialNumber;
    }
}
