class LocationDetector {
    constructor() {
        this.watchId = null;
    }
    
    async detectLocation() {
        return new Promise((resolve, reject) => {
            if (!navigator.geolocation) {
                reject(new Error('Geolocation not supported'));
                return;
            }

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    resolve({
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                        accuracy: Math.round(position.coords.accuracy),
                        method: 'gps',
                        timestamp: new Date().toISOString()
                    });
                },
                (error) => {
                    console.error('Geolocation error:', error);
                    // Fallback to IP if GPS fails
                    this.detectIP().then(resolve).catch(reject);
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
            );
        });
    }
    
    async detectIP() {
        try {
            const response = await fetch('https://ipapi.co/json/');
            const data = await response.json();
            return {
                latitude: parseFloat(data.latitude),
                longitude: parseFloat(data.longitude),
                accuracy: 1000, // IP geolocation is less accurate
                method: 'ip',
                timestamp: new Date().toISOString()
            };
        } catch (error) {
            throw new Error('IP Geolocation failed');
        }
    }

    async getAddress(lat, lng) {
        // Placeholder for Google Reverse Geocoding
        // In a real app, you'd use: https://maps.googleapis.com/maps/api/geocode/json?latlng=${lat},${lng}&key=YOUR_API_KEY
        return "Detecting address...";
    }
}
