import { Controller } from "@hotwired/stimulus";
import postRequestHandler from "../helpers/postRequestHandler";

const states = {
    unsubscribed: "unsubscribed",
    subscribed: "subscribed",
};

/**
 * Resets the state of the targets.
 *
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
            // eslint-disable-next-line no-console
            console.error("State not recognized");
    }
}

export default class extends Controller {
    static targets = [
        "actionButton",
        "actionFetchingSubscription",
        "actionUnsubscribed",
        "actionSubscribed",
        "menu",
    ];

    connect = () => {
        const { targets, url, params } = this.getData();

        postRequestHandler(url, params).then((result) => {
            this.handleFetchedData(result, targets);
        });
    };

    action = (e) => {
        const { targets, url, params } = this.getData();
        targets.menuTarget = this.menuTarget;

        targets.menuTarget.classList.toggle("hidden");

        const reportType = Object.keys(params)[0];
        params[reportType].subscriptionType = e.target.dataset.subscriptiontype;

        triggerState(states.fetching, targets);

        postRequestHandler(url, params).then((result) => {
            this.handleFetchedData(result, targets);
        });
    };

    getData = () => {
        const targets = {
            parent: this.actionButtonTarget,
            fetching: this.actionFetchingSubscriptionTarget,
            unsubscribed: this.actionUnsubscribedTarget,
            subscribed: this.actionSubscribedTarget,
        };

        const data = targets.parent.dataset;
        const { url } = data;
        const params = JSON.parse(data.params);

        return { targets, url, params };
    };

    // eslint-disable-next-line class-methods-use-this
    handleFetchedData = (result, targets) => {
        const newTargets = { ...targets };

        if (result.status === 200) {
            const { data } = result;
            if (data.frequencies) {
                triggerState(states.subscribed, targets);
                newTargets.parent.dataset.frequencies = data.frequencies;
            } else {
                delete newTargets.parent.dataset.frequencies;
                triggerState(states.unsubscribed, targets);
            }
        } else {
            document.getElementById("subscribe-module").style.display = "none";
        }
    };

    toggle() {
        const targets = {
            menuTarget: this.menuTarget,
        };
        targets.menuTarget.classList.toggle("hidden");
    }
}
