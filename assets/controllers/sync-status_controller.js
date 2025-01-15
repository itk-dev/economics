import {Controller} from "@hotwired/stimulus";
import dayjs from "dayjs";

/**
 * Synchronization status.
 */
export default class extends Controller {
    static targets = ['ended', 'ok', 'error', 'running', 'notStarted', 'spinner', 'status', 'active', 'done', 'progress'];

    updateIntervalSeconds = 60;

    timeout;

    run = () => {
        this.statusTarget.classList.add("hidden");
        this.spinnerTarget.classList.remove("hidden");

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
        })
            .finally(() => setTimeout(this.refresh, 3000));
    }

    refresh = () => {
        this.spinnerTarget.classList.remove("hidden");
        this.okTarget.classList.add("hidden");
        this.errorTarget.classList.add("hidden");
        this.runningTarget.classList.add("hidden");
        this.notStartedTarget.classList.add("hidden");
        this.doneTarget.classList.add("hidden");
        this.activeTarget.classList.add("hidden");
        this.progressTarget.classList.add("hidden");

        fetch("/admin/synchronization/status")
            .then(response => response.json())
            .then((data) => {
                this.endedTarget.innerHTML = dayjs(data.ended).format("DD/MM-YYYY HH:mm:ss");

                switch (data.status) {
                    case "DONE":
                        this.doneTarget.classList.remove("hidden");
                        this.okTarget.classList.remove("hidden");
                        break;
                    case "ERROR":
                        this.doneTarget.classList.remove("hidden");
                        this.errorTarget.classList.remove("hidden");
                        break;
                    case "RUNNING":
                        this.progressTarget.classList.remove("hidden");
                        this.progressTarget.innerText = "(" + (data.step ?? '-') + ": " + data.progress + " %)"
                        this.activeTarget.classList.remove("hidden");
                        this.runningTarget.classList.remove("hidden");
                        break;
                    case "NOT_STARTED":
                        this.activeTarget.classList.remove("hidden");
                        this.notStartedTarget.classList.remove("hidden");
                        break;
                }
            })
            .finally(() => {
                this.spinnerTarget.classList.add("hidden");
                this.statusTarget.classList.remove("hidden");

                this.timeout = setTimeout(
                    this.refresh, this.updateIntervalSeconds * 1000
                )
            });
    }

    connect() {
        this.refresh();
    }
}
