class MapManager {
    constructor(containerId) {
        this.containerId = containerId;
        this.map = null;
        this.markers = {};
        this.circles = {};
    }
    
    async init(center = { lat: 11.3411, lng: 77.7172 }, zoom = 15) {
        // Wait for Google Maps to load
        if (typeof google === 'undefined') {
            console.error('Google Maps API not loaded');
            return;
        }

        const { Map, InfoWindow } = await google.maps.importLibrary("maps");
        const { AdvancedMarkerElement } = await google.maps.importLibrary("marker");

        this.map = new Map(document.getElementById(this.containerId), {
            center: center,
            zoom: zoom,
            mapId: "SOLAR_TRACKER_MAP_ID",
            disableDefaultUI: false,
            zoomControl: true,
            mapTypeControl: false,
            scaleControl: true,
            streetViewControl: false,
            rotateControl: false,
            fullscreenControl: true
        });

        this.infoWindow = new InfoWindow();
        this.AdvancedMarkerElement = AdvancedMarkerElement;
        return this;
    }
    
    addMarker(id, position, title = "", iconUrl = null) {
        const pinElement = document.createElement("div");
        pinElement.className = "custom-pin";
        pinElement.innerHTML = `
            <div class="pin-anchor"></div>
            <div class="pin-pulse"></div>
            <div class="pin-icon">
                <svg viewBox="0 0 24 24" width="24" height="24">
                    <path fill="currentColor" d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                </svg>
            </div>
            <div class="pin-label">${title.split(' ')[0]}</div>
        `;

        const marker = new this.AdvancedMarkerElement({
            map: this.map,
            position: position,
            title: title,
            content: pinElement,
            gmpClickable: true
        });
        
        // InfoWindow Listener
        marker.addListener("click", () => {
            this.infoWindow.setContent(`
                <div style="padding: 12px; min-width: 150px;">
                    <div style="font-weight: 800; color: #1f2937; margin-bottom: 4px;">${title}</div>
                    <div style="font-size: 11px; color: #6b7280;">User currently active</div>
                </div>
            `);
            this.infoWindow.open(this.map, marker);
        });

        this.markers[id] = marker;

        // Add Accuracy Circle
        this.circles[id] = new google.maps.Circle({
            map: this.map,
            center: position,
            radius: 0, // Will be set in update
            fillColor: "#3b82f6",
            fillOpacity: 0.1,
            strokeColor: "#3b82f6",
            strokeOpacity: 0.3,
            strokeWeight: 1,
            clickable: false
        });

        return marker;
    }
    
    updateMarker(id, newPos, accuracy = 10) {
        if (!this.markers[id]) return;
        
        const marker = this.markers[id];
        const circle = this.circles[id];
        const oldPos = marker.position;
        
        // Update circle radius immediately
        if (circle) {
            circle.setRadius(accuracy);
            circle.setCenter(newPos);
        }

        if (!oldPos || (oldPos.lat === newPos.lat && oldPos.lng === newPos.lng)) return;

        // Smooth interpolation over 1 second
        const startTime = Date.now();
        const duration = 1000;
        
        const animate = () => {
            const elapsed = Date.now() - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            const easedProgress = progress < 0.5 ? 2 * progress * progress : -1 + (4 - 2 * progress) * progress;

            const lat = oldPos.lat + (newPos.lat - oldPos.lat) * easedProgress;
            const lng = oldPos.lng + (newPos.lng - oldPos.lng) * easedProgress;
            
            const currentPos = { lat, lng };
            marker.position = currentPos;
            if (circle) circle.setCenter(currentPos);

            if (progress < 1) {
                requestAnimationFrame(animate);
            }
        };
        
        requestAnimationFrame(animate);
    }

    removeMarker(id) {
        if (this.markers[id]) {
            this.markers[id].setMap(null);
            delete this.markers[id];
        }
        if (this.circles[id]) {
            this.circles[id].setMap(null);
            delete this.circles[id];
        }
    }
    
    panTo(position, zoom = null) {
        this.map.panTo(position);
        if (zoom) this.map.setZoom(zoom);
    }

    focusMarker(userId) {
        const marker = this.markers[userId];
        if (marker) {
            const el = marker.content;
            el.classList.add('highlight');
            setTimeout(() => el.classList.remove('highlight'), 3000);
            
            this.map.setCenter(marker.position);
            this.map.setZoom(19);
        }
    }
}
