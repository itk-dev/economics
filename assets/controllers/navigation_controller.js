import { Controller } from "@hotwired/stimulus";

/*
 * The following line makes this controller "lazy": it won't be downloaded until needed
 * See https://github.com/symfony/stimulus-bridge#lazy-controllers
 */
/* stimulusFetch: 'lazy' */
export default class extends Controller {
    // Set active class in main menu on load.

    /* eslint-disable-next-line class-methods-use-this */
    connect() {
        // Find active menu item.
        const currentPath = window.location.pathname;
        const menuItems = document.querySelectorAll(
            "#main-menu .navigation-item",
        );

        menuItems.forEach((menuItem) => {
            if (menuItem.pathname === currentPath) {
                menuItem.classList.add("current");

                const activeElementParent = menuItem.closest(
                    ".navigation-item-submenu",
                );
                const nextElement = menuItem.nextElementSibling;

                if (activeElementParent) {
                    activeElementParent.classList.add("shown");
                }
                if (
                    nextElement &&
                    nextElement.classList.contains("navigation-item-submenu")
                ) {
                    nextElement.classList.add("shown");
                }
            }
        });
    }

    // Set active menu item if menu item has no pathname.
    /* eslint-disable-next-line class-methods-use-this */
    toggle(target) {
        target.currentTarget.nextElementSibling.classList.toggle("shown");
    }
}
