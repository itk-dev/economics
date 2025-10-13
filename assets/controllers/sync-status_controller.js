import { Controller } from "@hotwired/stimulus";

/** Synchronization status. */
export default class extends Controller {
    static targets = ["queue", "error"];

    statusMessageTemplate = "";

    nextTimeout = 20000;

    refresh = () => {
        fetch("/admin/synchronization/status")
            .then((response) => {
                return response.json();
            })
            .then((data) => {
                console.log(data);
                this.queueTarget.innerText =
                    this.queueMessageTemplate.replace(
                        "<numberOfJobs>",
                        data.async ?? 0,
                    );
                this.errorTarget.innerText =
                    this.errorMessageTemplate.replace(
                        "<numberOfJobs>",
                        data.error ?? 0,
                    );

                this.nextTimeout = data.async > 0 ? 5000 : 20000;
            })
            .finally(() => {
                setTimeout(this.refresh, this.nextTimeout);
            });
    };

    connect() {
        this.queueMessageTemplate = this.element.dataset.jobqueue ?? "";
        this.errorMessageTemplate = this.element.dataset.errorqueue ?? "";

        this.refresh();
    }
}
