import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["description"];

    endpoint;

    connect() {
        this.endpoint = this.element.dataset.endpoint;
    }

    generate() {
        fetch(this.endpoint, {
            method: "GET",
            mode: "same-origin",
            cache: "no-cache",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/json",
            },
            redirect: "follow",
            referrerPolicy: "no-referrer",
        }).then(async (resp) => {
            if (resp.ok) {
                const target = this.descriptionTarget;

                resp.json().then((data) => {
                    if (data.description !== null) {
                        target.value = data.description;
                    }
                });
            }
        });
    }
}
