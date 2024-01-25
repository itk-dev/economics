import { Controller } from "@hotwired/stimulus";

/*
 * The following line makes this controller "lazy": it won't be downloaded until needed
 * See https://github.com/symfony/stimulus-bridge#lazy-controllers
 */
/* stimulusFetch: 'lazy' */
export default class extends Controller {
    
  connect() {
    // Find active menupoint
    var currentPath = window.location.pathname;
    var regexPattern = /\/admin\/([^\/]+)/;
    var match = currentPath.match(regexPattern);
    if (match) {
      var currentPage = document.querySelector(
        'a[href*="/admin/' + match[1] + '/"]'
      );
    } else {
      var currentPage = document.querySelector('a[href*="/admin/"]');
    }
    currentPage.classList.add("current");

    var activeElementParent = findClosestParentWithClass(
      currentPage,
      "submenu-content"
    );

    // Expand collapsible if active menu point is child
    if (activeElementParent) {
      activeElementParent.classList.add("shown");
    }
  }

  toggle(target) {
    target.currentTarget.nextElementSibling.classList.toggle("shown");
  }
}

function findClosestParentWithClass(element, className) {
  while (
    element &&
    (element.nodeType !== 1 || !element.classList.contains(className))
  ) {
    element = element.parentNode;
  }
  return element;
}
