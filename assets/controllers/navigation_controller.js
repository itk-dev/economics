import { Controller } from "@hotwired/stimulus";

/*
 * The following line makes this controller "lazy": it won't be downloaded until needed
 * See https://github.com/symfony/stimulus-bridge#lazy-controllers
 */
/* stimulusFetch: 'lazy' */
export default class extends Controller {
    
  connect() {
    // Find active menupoint
    const currentPath = window.location.pathname;
    const pathPattern = /\/admin\/(?:[^/]+\/)?([^/]+)/;

    const pathMatch = currentPath.match(pathPattern);
    let activeNavigationElement;

    if (pathMatch) {
      activeNavigationElement = document.querySelector('a.navigation-item[href="' + pathMatch[0] + '/"]');
      console.log(activeNavigationElement);
    } else {
      activeNavigationElement = document.querySelector('a[href*="/admin/"]');
    }
    activeNavigationElement.classList.add("current");

    const activeElementParent = activeNavigationElement.closest(".navigation-item-submenu");

    // Expand collapsible if active menu point is child
    if (activeElementParent) {
      activeElementParent.classList.add("shown");
    }
  }

  toggle(target) {
    target.currentTarget.nextElementSibling.classList.toggle("shown");
  }
}
