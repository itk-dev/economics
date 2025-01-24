import { Controller } from "@hotwired/stimulus";
import postRequestHandler from "../helpers/postRequestHandler";

const states = {
    default: "default",
    loading: "loading",
    success: "success",
    error: "error",
};

/**
 * Resets the state of the targets.
 *
 * @param {object} targets - The targets to reset.
 * @returns {void}
 */
function resetState(targets) {
    Object.entries(targets).forEach(([type, target]) => {
        target.classList.remove("btn-error", "btn-success");
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
        case states.default:
            targets.default.classList.remove("hidden");
            break;
        case states.loading:
            targets.loading.classList.remove("hidden");
            break;
        case states.success:
            targets.success.classList.remove("hidden");
            targets.parent.classList.add("btn-success");
            break;
        case states.error:
            targets.error.classList.remove("hidden");
            targets.parent.classList.add("btn-error");
            break;
        default:
            // eslint-disable-next-line no-console
            console.error("State not recognized");
    }
}

export default class extends Controller {
    static targets = [
        "actionDefault",
        "actionLoading",
        "actionSuccess",
        "actionError",
    ];

    action(e) {
        const targets = {
            default: this.actionDefaultTarget,
            loading: this.actionLoadingTarget,
            success: this.actionSuccessTarget,
            error: this.actionErrorTarget,
            parent: e.target,
        };
        const data = e.target.dataset;
        const { url } = data;
        const { reload } = data;

        triggerState(states.loading, targets);
        postRequestHandler(url).then((result) => {
            if (result.success) {
                triggerState(states.success, targets);
                setTimeout(() => {
                    if (reload) {
                        window.location.reload();
                    }
                    triggerState(states.default, targets);
                }, 2000);
            } else {
                triggerState(states.error, targets);
                // eslint-disable-next-line no-alert
                alert(result.error);
            }
        });
    }
}
