import { Controller } from '@hotwired/stimulus';

/*
 * Fills the hidden "position" field of the form with the browser position, as JSON.
 *
 * The city field follows the position: it selects the nearest city (within PREFILL_RADIUS_KM of the
 * centre of its cinemas), on load when the visitor has already allowed geolocation (an empty field
 * only), and after "use my position". The browser is never asked for the permission on its own:
 * only "use my position" does that.
 */
const PREFILL_RADIUS_KM = 30;

export default class extends Controller {
    static targets = ['position', 'status', 'city'];
    static values = { unavailable: String, locating: String, saved: String, denied: String, prefilled: String, cities: Array };

    connect() {
        if (!this.hasCityTarget || '' !== this.cityTarget.value || !navigator.geolocation || !navigator.permissions) {
            return;
        }
        navigator.permissions.query({ name: 'geolocation' })
            .then((permission) => {
                if ('granted' === permission.state) {
                    navigator.geolocation.getCurrentPosition((position) => this.prefillCity(position.coords), () => {});
                }
            })
            .catch(() => {});
    }

    locate() {
        if (!navigator.geolocation) {
            this.statusTarget.textContent = this.unavailableValue;
            return;
        }

        this.statusTarget.textContent = this.locatingValue;
        navigator.geolocation.getCurrentPosition(
            (position) => {
                this.savePosition(position.coords);
                // Asked for: the nearest city replaces the one chosen, if there is one near enough.
                this.statusTarget.textContent = this.selectCity(position.coords) ? this.prefilledValue : this.savedValue;
            },
            () => {
                this.statusTarget.textContent = this.deniedValue;
            },
        );
    }

    savePosition(coords) {
        this.positionTarget.value = JSON.stringify({ lat: coords.latitude, lng: coords.longitude });
    }

    prefillCity(coords) {
        // The visitor may have chosen a city in the meantime: never overwrite it.
        if ('' !== this.cityTarget.value || !this.selectCity(coords)) {
            return;
        }
        this.savePosition(coords);
        this.statusTarget.textContent = this.prefilledValue;
    }

    /** Selects the city nearest to the position; false if none is near enough. */
    selectCity(coords) {
        if (!this.hasCityTarget) {
            return false;
        }
        const nearest = this.nearestCity(coords.latitude, coords.longitude);
        if (null === nearest) {
            return false;
        }
        // The autocomplete (Tom Select) keeps its own state: go through it when it is there.
        if (this.cityTarget.tomselect) {
            this.cityTarget.tomselect.setValue(nearest.slug);
        } else {
            this.cityTarget.value = nearest.slug;
        }

        return true;
    }

    nearestCity(latitude, longitude) {
        let nearest = null;
        let nearestDistance = PREFILL_RADIUS_KM;
        for (const city of this.citiesValue) {
            const distance = distanceKm(latitude, longitude, city.latitude, city.longitude);
            if (distance <= nearestDistance) {
                nearest = city;
                nearestDistance = distance;
            }
        }

        return nearest;
    }
}

/* Haversine distance, like App\Planner\Geo::distanceKm(). */
function distanceKm(lat1, lng1, lat2, lng2) {
    const toRadians = (degrees) => degrees * Math.PI / 180;
    const a = Math.sin(toRadians(lat2 - lat1) / 2) ** 2
        + Math.cos(toRadians(lat1)) * Math.cos(toRadians(lat2)) * Math.sin(toRadians(lng2 - lng1) / 2) ** 2;

    return 6371 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}
