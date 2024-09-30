import { Controller } from "@hotwired/stimulus";
import { postRequestHandler } from "../helpers/postRequestHandler";

const states = {
    unsubscribed: "unsubscribed",
    subscribed: "subscribed",
};

export default class extends Controller {
    static targets = [
        "actionButton",
        "actionFetchingSubscription",
        "actionUnsubscribed",
        "actionSubscribed",
        "menu",
    ];

    connect() {
        const targets = {
            parent: this.actionButtonTarget,
            fetching: this.actionFetchingSubscriptionTarget,
            unsubscribed: this.actionUnsubscribedTarget,
            subscribed: this.actionSubscribedTarget,
        };
        const data = targets.parent.dataset;
        const { url } = data;
        const encodedParams = data.params;
        const params = JSON.parse(encodedParams);

        postRequestHandler(url, params).then((result) => {
            console.log(result.data);
            if (result.data.success) {
                if (result.data.frequencies) {
                    triggerState(states.subscribed, targets);

                    data.frequencies = result.data.frequencies;
                } else {
                    triggerState(states.unsubscribed, targets);
                }
            } else {
                document.getElementById("subscribe-module").style.display =
                    "none";
            }
        });
    }

    action = (e) => {
        const targets = {
            menuTarget: this.menuTarget,
            parent: this.actionButtonTarget,
            fetching: this.actionFetchingSubscriptionTarget,
            unsubscribed: this.actionUnsubscribedTarget,
            subscribed: this.actionSubscribedTarget,
        };
        const data = targets.parent.dataset;
        const { url } = data;
        const encodedParams = data.params;
        const params = JSON.parse(encodedParams);

        const type = e.target.dataset.subscriptiontype;

        targets.menuTarget.classList.toggle("hidden");

        const reportType = Object.keys(params)[0];
        params[reportType].subscriptionType = type;

        triggerState(states.fetching, targets);
        postRequestHandler(url, params).then((result) => {
            if (result.success) {
                if (result.data.action) {
                    if (result.data.frequencies) {
                        data.frequencies = result.data.frequencies;
                        triggerState(states.subscribed, targets);
                    } else {
                        data.frequencies = "";
                        triggerState(states.unsubscribed, targets);
                    }
                }
            } else {
                document.getElementById("subscribe-module").style.display =
                    "none";
            }
        });
    };

    toggle() {
        const targets = {
            menuTarget: this.menuTarget,
        };
        targets.menuTarget.classList.toggle("hidden");
    }
}

/**
 * Applies a state to the provided targets based on the given state value.
 *
 * @param {string} state - The state value to apply.
 * @param {object} targets - The targets to apply the state to.
 */
function triggerState(state, targets) {
    resetState(targets);

    switch (state) {
        case states.unsubscribed:
            targets.unsubscribed.classList.remove("hidden");
            break;
        case states.subscribed:
            targets.subscribed.classList.remove("hidden");
            break;
        case states.fetching:
            targets.fetching.classList.remove("hidden");
            break;
        default:
            console.log("State not recognized");
    }
}

/**
 * Resets the state of the targets.
 * @param {object} targets - The targets to reset.
 * @returns {void}
 */
function resetState(targets) {
    Object.entries(targets).forEach(([type, target]) => {
        if (type === "parent") {
            return;
        }
        target.classList.add("hidden");
    });
}
