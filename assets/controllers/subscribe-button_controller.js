import { Controller } from "@hotwired/stimulus";
import { postRequestHandler } from "../helpers/postRequestHandler";

const states = {
    unsubscribed: "unsubscribed",
    subscribed: "subscribed",
};

export default class extends Controller {

    static targets = ["actionUnsubscribed", "actionSubscribed"];

    connect() {
        console.log('hallo');
    }
    action(e) {
        const targets = {
            unsubscribed: this.actionUnsubscribedTarget,
            subscribed: this.actionSubscribedTarget,
            parent: e.target,
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
/*        const targets = {
            default: this.actionDefaultTarget,
            loading: this.actionLoadingTarget,
            success: this.actionSuccessTarget,
            error: this.actionErrorTarget,
            parent: e.target,
        };
        const data = e.target.dataset;
        const url = data.url;
        const reload = data.reload;

        triggerState(states.loading, targets);
        postRequestHandler(url)
            .then(result => {
                if (result.success) {
                    triggerState(states.success, targets);
                    setTimeout(()=>{
                        reload && window.location.reload();
                        triggerState(states.default, targets);
                    }, 2000)
                } else {
                    triggerState(states.error, targets);
                    alert(result.error);
                }
            });*/
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
