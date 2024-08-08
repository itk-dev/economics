import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    updateUrl;
    connect() {
        console.log('Hello from connect');
        this.updateUrl = this.element.dataset.updateUrl;
    }

    syncWorklogs(e) {
        let syncButton = e.target;
        syncButton.disabled = true;
        syncButton.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" className="size-6">\n' +
            '  <path strokeLinecap="round" strokeLinejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />\n' +
            '</svg>'

        fetch(this.updateUrl, {
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
            .then(async (resp) => {
                if (!resp.ok) {
                    resp.json().then((err) => {
                        syncButton.innerText = `failed: ${err.message}`;
                    });
                } else {
                    syncButton.innerText = "ok";
                }
            })
            .catch((err) => {
                syncButton.innerText = `failed${err.message}`;
                syncButton.disabled = false;
            })
            .finally(() => {
               syncButton.disabled = false;
            });
    }
}
