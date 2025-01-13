import {Controller} from "@hotwired/stimulus";
import Choices from "choices.js";

/**
 * Project sync controller.
 *
 * Sync a project
 */
export default class extends Controller {
    static targets = ["select"];

    connect() {
        fetch("/admin/project/options", {
            method: "GET"
        }).then((resp) => resp.json()).then(
            (result) => {
                this.selectTarget.innerHTML = result.map(
                    ({id, title}) =>
                        `<option value="${id}">${title}</option>`).join("\n");
            }
        ).finally(() => {
                new Choices(this.selectTarget, {
                    allowHTML: true,
                    itemSelectText: "",
                })
            }
        );
    }

    submit() {
        console.log(this.selectTarget.value);
        if (this.selectTarget.value) {
            fetch(`/admin/project/${this.selectTarget.value}/sync`, {
                method: "POST",
                mode: "same-origin",
                cache: "no-cache",
                credentials: "same-origin",
                headers: {
                    "Content-Type": "application/json",
                },
                redirect: "follow",
                referrerPolicy: "no-referrer",
            }).then(r => r.json()).then(
                (response) => {
                    console.log("success", response);
                }
            ).catch((e) => console.log(e));
        }
    }
}
