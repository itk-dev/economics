import { Controller } from '@hotwired/stimulus';
export default class extends Controller {

    static targets = ['select'];
    connect() {
      this.defaultViewEndpoint = this.element.dataset.defaultViewUpdateEndpoint;
    }
    select(event) {
      const newDefaultView = event.target.options[event.target.selectedIndex].value;

      fetch(this.defaultViewEndpoint, {
        method: 'POST',
        mode: 'same-origin',
        cache: 'no-cache',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          newDefaultView
        })
      })
      .catch((err) => {
        console.log('Error', err);
      })
    }


}