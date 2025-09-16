import { Controller } from "@hotwired/stimulus";

/** Synchronization status. */
export default class extends Controller {
    static targets = [
        "ended",
        "ok",
        "error",
        "running",
        "notStarted",
        "active",
        "done",
        "progress",
    ];

    updateIntervalSeconds = 20;

    updateIntervalRunningSeconds = 5;

    nextRefresh = 20;

    timeout;

    refresh = () => {
        this.nextRefresh = this.updateIntervalSeconds;

        fetch("/admin/synchronization/status")
            .then((response) => {
                if (response.status === 404) {
                    this.hideAllElements();
                    this.endedTarget.innerHTML = "Not found";
                    this.doneTarget.classList.remove("hidden");
                    this.errorTarget.classList.remove("hidden");
                }
                return response.json();
            })
            .then((data) => {
                // Hide elements after fetch, to stop content from dissapearing before reappearing when running.
                this.hideAllElements();

                if (data.queueLength === 0) {
                    this.doneTarget.classList.remove("hidden");
                    this.nextRefresh = this.updateIntervalSeconds;
                } else if (data.queueLength > 0) {
                    if (data.queueLength === 1) {
                        this.progressTarget.classList.remove("hidden");
                        this.progressTarget.innerText = `Sidste job afvikles.`;
                        this.runningTarget.classList.remove("hidden");
                    } else {
                        this.progressTarget.classList.remove("hidden");
                        this.progressTarget.innerText = `${data.queueLength} jobs i kø`;
                        this.activeTarget.classList.remove("hidden");
                        this.runningTarget.classList.remove("hidden");
                    }
                    this.nextRefresh = this.updateIntervalRunningSeconds;
                }
            })
            .finally(() => {
                this.timeout = setTimeout(
                    this.refresh,
                    this.nextRefresh * 1000,
                );
            });
    };

    hideAllElements() {
        this.okTarget.classList.add("hidden");
        this.errorTarget.classList.add("hidden");
        this.runningTarget.classList.add("hidden");
        this.notStartedTarget.classList.add("hidden");
        this.doneTarget.classList.add("hidden");
        this.activeTarget.classList.add("hidden");
        this.progressTarget.classList.add("hidden");
    }

    connect() {
        if (this.element.dataset.interval) {
            this.updateIntervalSeconds = this.element.dataset.interval;
        }

        this.refresh();
    }
}
