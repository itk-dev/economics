import { Controller } from "@hotwired/stimulus";
import { postRequestHandler } from "../helpers/postRequestHandler";

const states = {
    default: "default",
    loading: "loading",
    success: "success",
    error: "error"
};

export default class extends Controller {

    static targets = ["actionDefault", "actionLoading", "actionSuccess", "actionError"];

    action(e) {
        const targets = {
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
        case states.default:
            targets.default.classList.remove('hidden');
            break;
        case states.loading:
            targets.loading.classList.remove("hidden");
            break;
        case states.success:
            targets.success.classList.remove("hidden");
            targets.parent.classList.add('btn-success');
            break;
        case states.error:
            targets.error.classList.remove("hidden");
            targets.parent.classList.add('btn-error');
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
        target.classList.remove('btn-error', 'btn-success');
        if (type === "parent") {
            return;
        }
       target.classList.add('hidden');
    });
}