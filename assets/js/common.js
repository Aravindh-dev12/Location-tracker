// Common utility functions

function getUser() {
    return JSON.parse(localStorage.getItem('user') || '{}');
}

function setUser(user) {
    localStorage.setItem('user', JSON.stringify(user));
}

function clearUser() {
    localStorage.removeItem('user');
}

async function saveLocation(lat, lng, acc, method) {
    try {
        const response = await fetch('../api/save_location.php', {
            method: 'POST',
            body: JSON.stringify({ latitude: lat, longitude: lng, accuracy: acc, method: method })
        });
        return await response.json();
    } catch (e) { console.error('saveLocation failed', e); }
}

async function submitWorkReport(description, hours, location) {
    const response = await fetch('../api/submit_work_report.php', {
        method: 'POST',
        body: JSON.stringify({
            description: description,
            hours: hours,
            latitude: location.latitude,
            longitude: location.longitude,
            method: location.method
        })
    });
    return await response.json();
}

async function getWorkReports() {
    const response = await fetch('../api/get_work_reports.php');
    return await response.json();
}

async function logout(location) {
    try {
        await fetch('../api/auth_logout.php', {
            method: 'POST',
            body: location ? JSON.stringify({ latitude: location.latitude, longitude: location.longitude }) : null
        });
    } catch (e) { console.error('Logout sync failed'); }
}

class LocationDetector {
    constructor() {
        this.watchId = null;
    }

    async detectLocation(forceGPS = true) {
        return new Promise((resolve, reject) => {
            if (!navigator.geolocation) {
                reject('Geolocation not supported');
                return;
            }
            navigator.geolocation.getCurrentPosition(
                pos => resolve({
                    latitude: pos.coords.latitude,
                    longitude: pos.coords.longitude,
                    accuracy: pos.coords.accuracy,
                    method: 'gps'
                }),
                err => {
                    if (forceGPS) {
                        reject('GPS Hardware unavailable or permission denied');
                        return;
                    }
                    // Fallback to IP only if not forcing GPS
                    this.getIPLocation().then(resolve).catch(reject);
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
            );
        });
    }

    async getIPLocation() {
        try {
            const res = await fetch('https://ipapi.co/json/');
            const data = await res.json();
            return {
                latitude: data.latitude,
                longitude: data.longitude,
                accuracy: 1500, // Approximate IP accuracy
                method: 'ip'
            };
        } catch (e) {
            throw new Error('IP Geolocation failed');
        }
    }

    startWatching(onUpdate, onError) {
        if (!navigator.geolocation) return;
        if (this.watchId) navigator.geolocation.clearWatch(this.watchId);

        this.watchId = navigator.geolocation.watchPosition(
            pos => {
                // Device GPS is always preferred
                onUpdate({
                    latitude: pos.coords.latitude,
                    longitude: pos.coords.longitude,
                    accuracy: pos.coords.accuracy,
                    method: 'gps'
                });
            },
            err => {
                if (onError) onError(err);
            },
            { 
                enableHighAccuracy: true, // Forces Device GPS hardware use
                timeout: 15000, 
                maximumAge: 0 
            }
        );
    }

    stopWatching() {
        if (this.watchId) {
            navigator.geolocation.clearWatch(this.watchId);
            this.watchId = null;
        }
    }
}
