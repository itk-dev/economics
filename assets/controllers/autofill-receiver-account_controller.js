import { Controller } from "@hotwired/stimulus";

/** Autoselects the invoice receiver account from the selected client. */
export default class extends Controller {
    static targets = ["client", "receiver"];

    static values = { map: Object };

    update() {
        // The map sends external clients to the configured external receiver
        // account and everyone else to the default account. Clients with no
        // mapping (the empty "no client" option) reset the field to "".
        const account = this.mapValue[this.clientTarget.value] ?? "";

        // The receiver select is enhanced by choices.js, so go through its API
        // (exposed on the element by the choices controller) to keep the widget
        // and the underlying <select> in sync. Fall back to the native value
        // when choices.js is not active, e.g. on a disabled recorded invoice.
        const { choices } = this.receiverTarget;
        if (choices) {
            choices.setChoiceByValue(account);
        } else {
            this.receiverTarget.value = account;
        }
    }
}
