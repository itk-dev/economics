import { Controller } from '@hotwired/stimulus';
export default class extends Controller {

    static targets = ['select'];
    connect() {
      this.defaultViewEndpoint = this.element.dataset.defaultViewUpdateEndpoint;
    }
    select(event) {
      const newDefaultView = event.target.options[event.target.selectedIndex].value;
      const pathArray = window.location.pathname.split('/');
      const params = window.location.search;

      let i = 1;
      if ('admin' === pathArray[1]) {
        let newPath = "";
        for (i = 1; i < pathArray.length; i++) {
          newPath += "/";
          if (i === 2) {
            newPath += newDefaultView;
          }
          else {
            newPath += pathArray[i];
          }
        }

        window.location.replace(newPath + params);
      }
    }
}