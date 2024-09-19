import { Controller } from "@hotwired/stimulus";
import { postRequestHandler } from "../helpers/postRequestHandler";

const states = {
    unsubscribed: "unsubscribed",
    subscribed: "subscribed",
};

export default class extends Controller {

    static targets = ["actionButton", "actionFetchingSubscription", "actionUnsubscribed", "actionSubscribed"];

    connect() {
        const targets = {
            parent: this.actionButtonTarget,
            fetching: this.actionFetchingSubscriptionTarget,
            unsubscribed: this.actionUnsubscribedTarget,
            subscribed: this.actionSubscribedTarget,
        };
        const data = targets.parent.dataset;
        const url = data.url;
        const encodedParams = data.params;
        const params = JSON.parse(encodedParams);

        postRequestHandler(url, params)
            .then(result => {
                if (result.data.success) {
                    if (result.data.frequencies) {
                        data.frequencies = result.data.frequencies;
                    }
                    triggerState(states.subscribed, targets);
                } else {
                    triggerState(states.unsubscribed, targets);
                }
            });
    }
    action(e) {
        const targets = {
            parent: e.target,
            fetching: this.actionFetchingSubscriptionTarget,
            unsubscribed: this.actionUnsubscribedTarget,
            subscribed: this.actionSubscribedTarget,
        };
        const data = e.target.dataset;
        const url = data.url;
        const encodedParams = data.params;

        let params = JSON.parse(encodedParams);
        let reportType = Object.keys(params)[0];
        //params[reportType].subscriptionType = "monthly";

        postRequestHandler(url, params)
            .then(result => {
                if (result.success) {
                    triggerState(states.subscribed, targets);
                    console.log('jaaa');
                } else {
                    triggerState(states.unsubscribed, targets);
                    console.log('neiii');
                }
            });
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
            targets.unsubscribed.classList.remove('hidden');
            break;
        case states.subscribed:
            targets.subscribed.classList.remove("hidden");
            break;
        default:
            console.log("State not recognized");
    }
}

/**
 * Resets the state of the targets.
 *
 * @param {object} targets - The targets to reset.
 * @return {void}
 */
function resetState(targets)
{
    Object.entries(targets).forEach(([type, target]) => {
        if (type === "parent") {
            return;
        }
        target.classList.add('hidden');
    });
}
