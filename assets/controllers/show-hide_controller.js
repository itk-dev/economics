import {Controller} from '@hotwired/stimulus';

/**
 * Toggles a display of an entry.
 */
export default class extends Controller {
    static targets = ['entry', 'hiddenEntries'];
    storageKey = 'hiddenEntries';

    connect() {
        if (this.element.dataset.storageKey) {
            this.storageKey = this.element.dataset.storageKey;
        }

        this.hideEntries();
    }

    hideEntries() {
        const localStorageItem = localStorage.getItem(this.storageKey);
        const hiddenAssignees = JSON.parse(localStorageItem) ?? [];

        this.entryTargets.forEach((target) => {
            if (hiddenAssignees.includes(target.dataset.toggleId)) {
                target.classList.add('hidden');
            } else {
                target.classList.remove('hidden');
            }
        })

        this.hiddenEntriesTarget.innerHTML = hiddenAssignees.map(
            (value) => `<button data-action="click->show-hide#toggleEntry" data-toggle-id="${value}">
                    <svg class="planning-hidden-svg" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"></path>
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg> ${value}
                </button>`
        ).join();
    }

    toggleEntry(event) {
        const localStorageItem = localStorage.getItem(this.storageKey);
        const hiddenEntries = JSON.parse(localStorageItem) ?? [];

        const key = event.currentTarget.dataset.toggleId;

        if (key === null) {
            return;
        }

        const newHiddenEntries = new Set(hiddenEntries);
        if (newHiddenEntries.has(key)) {
            newHiddenEntries.delete(key);
        } else {
            newHiddenEntries.add(key)
        }

        localStorage.setItem(this.storageKey, JSON.stringify([...newHiddenEntries]));

        this.hideEntries();
    }
}
