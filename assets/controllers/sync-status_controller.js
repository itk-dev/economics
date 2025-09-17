import { Controller } from "@hotwired/stimulus";

/** Synchronization status. */
export default class extends Controller {
    static targets = ["status"];

    statusMessageTemplate = "";

    refresh = () => {
        fetch("/admin/synchronization/status")
            .then((response) => {
                return response.json();
            })
            .then((data) => {
                this.statusTarget.innerText =
                    this.statusMessageTemplate.replace(
                        "<numberOfJobs>",
                        data.queueLength ?? 0,
                    );
            })
            .finally(() => {
                setTimeout(this.refresh, 20000);
            });
    };

    connect() {
        this.statusMessageTemplate = this.element.dataset.status ?? "";

        this.refresh();
    }
}
