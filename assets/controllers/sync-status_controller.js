import { Controller } from "@hotwired/stimulus";
import dayjs from "dayjs";

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
        "button",
    ];

    updateIntervalSeconds = 20;

    updateIntervalRunningSeconds = 5;

    nextRefresh = 20;

    timeout;

    run = () => {
        this.buttonTarget.innerHTML = "Kører...";
        this.buttonTarget.disabled = true;
        this.doneTarget.classList.add("hidden");
        this.activeTarget.classList.add("hidden");

        fetch("/admin/synchronization/start", {
            method: "POST",
            mode: "same-origin",
            cache: "no-cache",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/json",
            },
            redirect: "follow",
            referrerPolicy: "no-referrer",
        }).finally(() => setTimeout(this.refresh, 1500));
    };

    refresh = () => {
        this.nextRefresh = this.updateIntervalSeconds;

        fetch("/admin/synchronization/status")
            .then((response) => {
                if (response.status === 404) {
                    this.hideAllElements();
                    this.endedTarget.innerHTML = "Not found";
                    this.buttonTarget.classList.remove("hidden");
                    this.doneTarget.classList.remove("hidden");
                    this.errorTarget.classList.remove("hidden");
                }
                return response.json();
            })
            .then((data) => {
                // Hide elements after fetch, to stop content from dissapearing before reappearing when running.
                this.hideAllElements();

                switch (data.status) {
                    case "DONE":
                        this.doneTarget.classList.remove("hidden");
                        this.okTarget.classList.remove("hidden");
                        this.endedTarget.innerHTML = dayjs(data.ended).format(
                            "DD/MM-YYYY HH:mm:ss",
                        );
                        this.buttonTarget.classList.remove("hidden");
                        break;
                    case "NOT_STARTED":
                        this.activeTarget.classList.remove("hidden");
                        this.notStartedTarget.classList.remove("hidden");
                        this.notStartedTarget.innerText = `Afventer.. ${data.queueLength} jobs i kø`;
                        break;
                    case "ERROR":
                        this.errorTarget.classList.remove("hidden");
                        this.buttonTarget.classList.remove("hidden");
                        break;
                    case "RUNNING":
                        if (data.queueLength === 0) {
                            this.progressTarget.classList.remove("hidden");
                            this.progressTarget.innerText = `Sidste job afvikles.`;
                            this.runningTarget.classList.remove("hidden");
                        } else {
                            this.progressTarget.classList.remove("hidden");
                            this.progressTarget.innerText = data.elapsed
                                ? `${data.queueLength} jobs i kø \n Job tid: ${data.elapsed}`
                                : `${data.queueLength} jobs i kø`;
                            this.activeTarget.classList.remove("hidden");
                            this.runningTarget.classList.remove("hidden");
                        }
                        this.nextRefresh = this.updateIntervalRunningSeconds;
                        break;
                    default:
                        this.buttonTarget.classList.remove("hidden");
                        break;
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
        this.buttonTarget.classList.add("hidden");
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
