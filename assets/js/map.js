/**
 * MapManager using MapLibre GL JS (Free/OpenSource)
 */
class MapManager {
    constructor(containerId) {
        this.containerId = containerId;
        this.map = null;
        this.markers = {};
        this.circles = {};
        this.isReady = false;
    }
    
    async init(center = { lat: 11.3411, lng: 77.7172 }, zoom = 15) {
        if (typeof maplibregl === 'undefined') {
            console.error('MapLibre GL JS not loaded');
            return;
        }

        console.log('Initializing MapLibre at:', center);
        this.map = new maplibregl.Map({
            container: this.containerId,
            style: 'https://tiles.openfreemap.org/styles/liberty',
            center: [center.lng, center.lat],
            zoom: zoom
        });

        this.map.addControl(new maplibregl.NavigationControl());
        this.map.addControl(new maplibregl.FullscreenControl());

        return new Promise((resolve) => {
            this.map.on('load', () => {
                console.log('Map style loaded successfully');
                this.isReady = true;
                resolve(this);
            });
            this.map.on('error', (err) => {
                console.error('MapLibre error:', err);
            });
        });
    }
    
    addMarker(id, position, title = "") {
        if (!this.map || !this.isReady) {
            // Queue for initialization if not ready
            console.warn(`Map not ready for marker ${id}, will try in next sync`);
            return;
        }

        const pinElement = document.createElement("div");
        pinElement.className = "custom-pin";
        pinElement.innerHTML = `
            <div class="pin-anchor"></div>
            <div class="pin-pulse"></div>
            <div class="pin-icon">👤</div>
            <div class="pin-label">${title.split(' ')[0]}</div>
        `;

        console.log(`Adding marker for ${id} at:`, position);
        const marker = new maplibregl.Marker({
            element: pinElement,
            anchor: 'bottom'
        })
        .setLngLat([position.lng, position.lat])
        .addTo(this.map);

        this.markers[id] = marker;

        // Add Accuracy Circle
        const sourceId = `accuracy-${id}`;
        this.map.addSource(sourceId, {
            type: 'geojson',
            data: this.createGeoJSONCircle([position.lng, position.lat], 0)
        });

        this.map.addLayer({
            id: `accuracy-layer-${id}`,
            type: 'fill',
            source: sourceId,
            paint: {
                'fill-color': '#3b82f6',
                'fill-opacity': 0.1,
                'fill-outline-color': '#3b82f6'
            }
        });

        this.circles[id] = { sourceId, layerId: `accuracy-layer-${id}` };
        return marker;
    }
    
    updateMarker(id, newPos, accuracy = 10) {
        if (!this.map || !this.isReady) return;
        
        const marker = this.markers[id];
        const circle = this.circles[id];
        if (!marker) return;

        if (circle && this.map.getSource(circle.sourceId)) {
            const accuracyKm = (accuracy || 10) / 1000;
            this.map.getSource(circle.sourceId).setData(
                this.createGeoJSONCircle([newPos.lng, newPos.lat], accuracyKm)
            );
        }

        const currentLngLat = marker.getLngLat();
        const oldPos = { lat: currentLngLat.lat, lng: currentLngLat.lng };
        if (oldPos.lat === newPos.lat && oldPos.lng === newPos.lng) return;

        const startTime = Date.now();
        const duration = 1000;
        const animate = () => {
            const elapsed = Date.now() - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const eased = progress < 0.5 ? 2 * progress * progress : -1 + (4 - 2 * progress) * progress;

            const lat = oldPos.lat + (newPos.lat - oldPos.lat) * eased;
            const lng = oldPos.lng + (newPos.lng - oldPos.lng) * eased;
            
            marker.setLngLat([lng, lat]);
            if (progress < 1) requestAnimationFrame(animate);
        };
        requestAnimationFrame(animate);
    }

    removeMarker(id) {
        if (this.markers[id]) {
            this.markers[id].remove();
            delete this.markers[id];
        }
        if (this.circles[id] && this.map) {
            if (this.map.getLayer(this.circles[id].layerId)) this.map.removeLayer(this.circles[id].layerId);
            if (this.map.getSource(this.circles[id].sourceId)) this.map.removeSource(this.circles[id].sourceId);
            delete this.circles[id];
        }
    }
    
    focusMarker(userId) {
        const marker = this.markers[userId];
        if (marker) {
            marker.getElement().classList.add('highlight');
            setTimeout(() => marker.getElement().classList.remove('highlight'), 3000);
            
            const lngLat = marker.getLngLat();
            this.map.flyTo({
                center: lngLat,
                zoom: 17,
                essential: true
            });
        }
    }

    createGeoJSONCircle(center, radiusInKm, points = 64) {
        const coords = { latitude: center[1], longitude: center[0] };
        const ret = [];
        const distanceX = radiusInKm / (111.32 * Math.cos((coords.latitude * Math.PI) / 180));
        const distanceY = radiusInKm / 110.574;

        for (let i = 0; i < points; i++) {
            const theta = (i / points) * (2 * Math.PI);
            ret.push([coords.longitude + distanceX * Math.cos(theta), coords.latitude + distanceY * Math.sin(theta)]);
        }
        ret.push(ret[0]);

        return {
            type: 'FeatureCollection',
            features: [{
                type: 'Feature',
                geometry: { type: 'Polygon', coordinates: [ret] }
            }]
        };
    }
}
