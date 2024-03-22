/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// any CSS you import will output into a single css file (app.css in this case)
import "./styles/app.css";
import "./styles/tom-select.css";

// start the Stimulus application
import "./bootstrap";
import TomSelect from "tom-select";

// Apply Tom Select to select select (pun intended!) elements
// (cf. https://tom-select.js.org/examples/)
window.addEventListener("load", () => {
    document.querySelectorAll("select.tom-select").forEach(
        (el) =>
            new TomSelect(el, {
                sortField: {
                    field: "text",
                    direction: "asc",
                },
            }),
    );
});
