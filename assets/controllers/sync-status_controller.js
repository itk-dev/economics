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

    updateIntervalSeconds = 60;

    updateIntervalRunningSeconds = 5;

    nextRefresh = 60;

    timeout;

    run = () => {
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
        }).finally(() => setTimeout(this.refresh, 3000));
    };

    refresh = () => {
        this.buttonTarget.classList.add("hidden");
        this.okTarget.classList.add("hidden");
        this.errorTarget.classList.add("hidden");
        this.runningTarget.classList.add("hidden");
        this.notStartedTarget.classList.add("hidden");
        this.doneTarget.classList.add("hidden");
        this.activeTarget.classList.add("hidden");
        this.progressTarget.classList.add("hidden");

        this.nextRefresh = this.updateIntervalSeconds;

        fetch("/admin/synchronization/status")
            .then((response) => {
                if (response.status === 404) {
                    this.endedTarget.innerHTML = "Not found";
                    this.buttonTarget.classList.remove("hidden");
                    this.doneTarget.classList.remove("hidden");
                    this.errorTarget.classList.remove("hidden");
                }
                return response.json();
            })
            .then((data) => {
                if (data) {
                    this.endedTarget.innerHTML = dayjs(data.ended).format(
                        "DD/MM-YYYY HH:mm:ss",
                    );

                    switch (data.status) {
                        case "DONE":
                            this.buttonTarget.classList.remove("hidden");
                            this.doneTarget.classList.remove("hidden");
                            this.okTarget.classList.remove("hidden");
                            break;
                        case "ERROR":
                            this.buttonTarget.classList.remove("hidden");
                            this.doneTarget.classList.remove("hidden");
                            this.errorTarget.classList.remove("hidden");
                            break;
                        case "RUNNING":
                            this.progressTarget.classList.remove("hidden");
                            this.progressTarget.innerText = `(${
                                data.step ?? "-"
                            }: ${data.progress} %)`;
                            this.activeTarget.classList.remove("hidden");
                            this.runningTarget.classList.remove("hidden");

                            this.nextRefresh =
                                this.updateIntervalRunningSeconds;
                            break;
                        case "NOT_STARTED":
                            this.activeTarget.classList.remove("hidden");
                            this.notStartedTarget.classList.remove("hidden");
                            break;
                        default:
                            break;
                    }
                }
            })
            .finally(() => {
                this.timeout = setTimeout(
                    this.refresh,
                    this.nextRefresh * 1000,
                );
            });
    };

    connect() {
        if (this.element.dataset.interval) {
            this.updateIntervalSeconds = this.element.dataset.interval;
        }

        this.refresh();
    }
}
