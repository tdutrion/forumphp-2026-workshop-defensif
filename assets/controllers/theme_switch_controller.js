import { Controller } from '@hotwired/stimulus';

/*
 * The light/dark switch of the top menu. With the automatic theme the page follows the system:
 * the switch then offers the opposite of what the system shows.
 */
export default class extends Controller {
    static values = { theme: String };
    static targets = ['input', 'toDark', 'toLight'];

    connect() {
        if ('auto' !== this.themeValue) {
            return;
        }
        const systemIsDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        this.inputTarget.value = systemIsDark ? 'light' : 'dark';
        this.toDarkTarget.classList.toggle('hidden', systemIsDark);
        this.toLightTarget.classList.toggle('hidden', !systemIsDark);
    }
}
