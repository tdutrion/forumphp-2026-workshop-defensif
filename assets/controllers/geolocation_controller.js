import { Controller } from '@hotwired/stimulus';

/*
 * Fills the hidden "position" field of the form with the browser position, as JSON.
 */
export default class extends Controller {
    static targets = ['position', 'status'];
    static values = { unavailable: String, locating: String, saved: String, denied: String };

    locate() {
        if (!navigator.geolocation) {
            this.statusTarget.textContent = this.unavailableValue;
            return;
        }

        this.statusTarget.textContent = this.locatingValue;
        navigator.geolocation.getCurrentPosition(
            (position) => {
                this.positionTarget.value = JSON.stringify({ lat: position.coords.latitude, lng: position.coords.longitude });
                this.statusTarget.textContent = this.savedValue;
            },
            () => {
                this.statusTarget.textContent = this.deniedValue;
            },
        );
    }
}
