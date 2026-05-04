import { Controller } from "@hotwired/stimulus";

/*
 * The following line makes this controller "lazy": it won't be downloaded until needed.
 * See https://github.com/symfony/stimulus-bridge#lazy-controllers
 */
/* stimulusFetch: 'lazy' */
/**
 * Per-row hide for report tables. Persists hidden worker emails to the server
 * (User.preferences) and re-fetches the report fragment so the PHP-computed
 * averages stay correct.
 *
 * Wiring on the fragment container element: data-controller="report-hide"
 * data-report-hide-url-value="{{ path(...?_fragment=1) }}"
 * data-report-hide-prefs-url-value="{{
 * path('app_user_preferences_hidden_workers') }}"
 *
 * Per-row hide button: data-action="report-hide#hide" data-worker-email="..."
 * Per-row unhide chip: data-action="report-hide#show" data-worker-email="..."
 */
export default class extends Controller {
    static values = {
        url: String,
        prefsUrl: String,
    };

    hide(event) {
        const email = event.currentTarget.dataset.workerEmail;
        if (email) {
            this.applyChange((current) =>
                Array.from(new Set([...current, email])),
            );
        }
    }

    show(event) {
        const email = event.currentTarget.dataset.workerEmail;
        if (email) {
            this.applyChange((current) => current.filter((e) => e !== email));
        }
    }

    async applyChange(transform) {
        const current = this.readCurrentHidden();
        const next = transform(current);

        this.element.classList.add("loading");

        try {
            const prefsResp = await fetch(this.prefsUrlValue, {
                method: "PATCH",
                mode: "same-origin",
                cache: "no-cache",
                credentials: "same-origin",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ hiddenWorkers: next }),
            });
            if (!prefsResp.ok) {
                throw new Error(
                    `Saving preferences failed (${prefsResp.status})`,
                );
            }

            const fragmentResp = await fetch(this.urlValue, {
                method: "GET",
                mode: "same-origin",
                cache: "no-cache",
                credentials: "same-origin",
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                    "X-Report-Fragment": "1",
                },
            });
            if (!fragmentResp.ok) {
                throw new Error(
                    `Fetching report fragment failed (${fragmentResp.status})`,
                );
            }

            const html = await fragmentResp.text();
            this.swap(html);
        } catch (err) {
            // eslint-disable-next-line no-console
            console.error("[report-hide]", err);
            this.element.classList.remove("loading");
        }
    }

    readCurrentHidden() {
        const chips = this.element.querySelectorAll(
            ".hidden-entry-chip[data-worker-email]",
        );
        return Array.from(chips).map((c) => c.dataset.workerEmail);
    }

    swap(html) {
        const next = document.createElement("div");
        next.innerHTML = html.trim();
        const replacement = next.querySelector("#report-fragment");
        if (!replacement) {
            // eslint-disable-next-line no-console
            console.error(
                "[report-hide] no #report-fragment in response — refusing to swap",
            );
            this.element.classList.remove("loading");
            return;
        }
        this.element.replaceWith(replacement);
    }
}
