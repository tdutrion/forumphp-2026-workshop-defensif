import { Controller } from '@hotwired/stimulus';

/*
 * Closes a <details> menu on a click outside of it or on Escape (opening stays native).
 */
export default class extends Controller {
    connect() {
        this.close = this.close.bind(this);
        document.addEventListener('click', this.close);
        document.addEventListener('keydown', this.close);
    }

    disconnect() {
        document.removeEventListener('click', this.close);
        document.removeEventListener('keydown', this.close);
    }

    close(event) {
        if (!this.element.open) {
            return;
        }
        if ('keydown' === event.type && 'Escape' !== event.key) {
            return;
        }
        if ('click' === event.type && this.element.contains(event.target)) {
            return;
        }
        this.element.open = false;
        if ('keydown' === event.type) {
            this.element.querySelector('summary').focus();
        }
    }
}
