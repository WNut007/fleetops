import { join } from '@ember/runloop';
import { debug } from '@ember/debug';
import { computeBearing, createTrackingMarker } from '../components/leaflet-tracking-marker';

const DEFAULT_PLACE_ICON = '/engines-dist/images/building-marker.png';

/** GeoJSON point -> [lat, lng], [0, 0] when missing (same as the point-to-coordinates helper). */
export function pointToLatLng(point) {
    let [longitude, latitude] = [0, 0];
    if (point && Array.isArray(point.coordinates)) {
        [longitude, latitude] = point.coordinates;
    }
    return [latitude, longitude];
}

/** "(lat, lng)" as the format-point helper prints it. */
function formatPoint(point) {
    const [latitude, longitude] = pointToLatLng(point);
    return `(${latitude}, ${longitude})`;
}

function textElement(tag, text) {
    const el = document.createElement(tag);
    el.textContent = text ?? '';
    return el;
}

/**
 * The live map's driver, vehicle and place markers, created and updated directly in Leaflet.
 *
 * They used to be ember-leaflet components, one per marker, each with its own child
 * components and modifiers: rendering ~300 markers took 3-4 s of main-thread time on the
 * first load. Here a marker costs a few Leaflet calls. Behaviour is kept: the same marker
 * classes and options, `add`/`click` reported through the live map's `trigger` (so its
 * handlers and universe events still run), tooltips rendered only when opened, and marker
 * updates for location, heading and icon when the record changes.
 *
 * Driver and vehicle tooltips show the Ember marker cards: the live map renders the card for
 * the hovered record into `tooltipElement` (see `onTooltipOpen`).
 */
export default class LiveMapMarkers {
    #markers = { driver: new Map(), vehicle: new Map(), place: new Map() };

    /**
     * @param {Object} options
     * @param {L.Map} options.map
     * @param {Function} options.trigger           live map trigger(name, record, event)
     * @param {Function} options.onTooltipOpen     (type, record) -> HTMLElement to show
     */
    constructor({ map, trigger, onTooltipOpen }) {
        this.map = map;
        this.trigger = trigger;
        this.onTooltipOpen = onTooltipOpen;
        this.L = window.leaflet || window.L;
    }

    sync({ drivers = [], vehicles = [], places = [] }) {
        if (!this.map) return;
        this.#syncType('driver', drivers);
        this.#syncType('vehicle', vehicles);
        this.#syncType('place', places);
    }

    destroy() {
        for (const markers of Object.values(this.#markers)) {
            for (const entry of markers.values()) {
                entry.layer.remove();
            }
            markers.clear();
        }
        this.map = null;
    }

    #syncType(type, records) {
        const markers = this.#markers[type];
        const seen = new Set();

        for (const record of Array.from(records ?? [])) {
            if (!record) continue;
            const key = record.id ?? record.public_id;
            if (key == null || seen.has(key)) continue;
            seen.add(key);

            // One bad record must not stop the rest of the markers from being drawn.
            try {
                const entry = markers.get(key);
                if (entry && entry.record === record) {
                    this.#update(type, entry);
                } else {
                    if (entry) entry.layer.remove();
                    markers.set(key, this.#create(type, record));
                }
            } catch (error) {
                debug(`[LiveMapMarkers] ${type} ${key}: ${error.message}`);
            }
        }

        for (const [key, entry] of markers) {
            if (!seen.has(key)) {
                entry.layer.remove();
                markers.delete(key);
            }
        }
    }

    #iconUrl(type, record) {
        if (type === 'driver') return record.vehicle_avatar;
        if (type === 'vehicle') return record.avatar_url;
        return record.avatar_url || DEFAULT_PLACE_ICON;
    }

    #icon(type, iconUrl) {
        const size = type === 'place' ? 16 : 24;
        return this.L.icon({ iconUrl, iconSize: [size, size] });
    }

    #create(type, record) {
        const L = this.L;
        const location = pointToLatLng(record.location);
        const iconUrl = this.#iconUrl(type, record);
        const icon = this.#icon(type, iconUrl);
        const event = type === 'driver' ? 'Driver' : type === 'vehicle' ? 'Vehicle' : 'Place';
        let layer;

        if (type === 'place') {
            layer = L.marker(location, { icon, riseOnHover: true, title: record.address, alt: record.address, draggable: false });
            layer.bindPopup(() => {
                const el = document.createElement('div');
                el.appendChild(textElement('div', record.address));
                el.appendChild(textElement('div', formatPoint(record.location)));
                return el;
            });
            layer.bindTooltip(() => textElement('span', record.name || record.address), { permanent: false, sticky: true, direction: 'bottom' });
        } else {
            const rotationAngle = record.heading;
            const bearingAngle = rotationAngle ?? computeBearing([0, 0], location);
            layer = createTrackingMarker(location, { icon, draggable: false, rotationAngle, publicId: record.public_id, bearingAngle });
            layer.on('movestart', (e) => join(() => this.#movestart(e)));
            layer.bindTooltip(() => this.onTooltipOpen(type, record, layer), { permanent: false, sticky: true });
        }

        layer.on('add', (e) => join(() => this.trigger(`on${event}Added`, record, e)));
        layer.on('click', (e) => join(() => this.trigger(`on${event}Clicked`, record, e)));
        layer.addTo(this.map);

        return { record, layer, iconUrl, location, heading: record.heading };
    }

    #update(type, entry) {
        const { record, layer } = entry;

        const location = pointToLatLng(record.location);
        if (location[0] !== entry.location[0] || location[1] !== entry.location[1]) {
            entry.location = location;
            layer.setLatLng(location);
        }

        const iconUrl = this.#iconUrl(type, record);
        if (iconUrl !== entry.iconUrl) {
            entry.iconUrl = iconUrl;
            layer.setIcon(this.#icon(type, iconUrl));
        }

        if (type !== 'place' && record.heading !== entry.heading) {
            entry.heading = record.heading;
            if (record.heading != null && typeof layer.setRotationAngle === 'function') {
                layer.setRotationAngle(record.heading);
            }
        }
    }

    /** Turn a tracking marker toward where it is sliding (as the tracking-marker component did). */
    #movestart(event) {
        const marker = event.target;
        const previousPosition = marker._previousPosition;
        const position = marker._nextPosition;
        if (!position) return;
        const moving = previousPosition?.[0] !== position[0] && previousPosition?.[1] !== position[1];

        marker.setRotationOrigin('center');
        if (moving) {
            marker.setRotationAngle(computeBearing(previousPosition, position));
        }
    }
}
