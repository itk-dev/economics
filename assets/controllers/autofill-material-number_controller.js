import { Controller } from "@hotwired/stimulus";

/** Autofills the invoice material number from the selected client's type. */
export default class extends Controller {
    static targets = ["client", "material"];

    static values = { map: Object };

    update() {
        // Reflect the selected client's implied material number. Clients with no
        // type (and the empty "no client" option) map to "", which resets the
        // field to the empty NONE option rather than leaving a stale value.
        this.materialTarget.value =
            this.mapValue[this.clientTarget.value] ?? "";
    }
}
