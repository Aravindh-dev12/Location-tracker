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

    async detectLocation() {
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
                    fetch('https://ipapi.co/json/')
                        .then(res => res.json())
                        .then(data => resolve({
                            latitude: data.latitude,
                            longitude: data.longitude,
                            accuracy: 1000,
                            method: 'ip'
                        }))
                        .catch(() => reject('All location methods failed'));
                },
                { enableHighAccuracy: true, timeout: 5000 }
            );
        });
    }

    startWatching(onUpdate, onError) {
        if (!navigator.geolocation) return;
        if (this.watchId) navigator.geolocation.clearWatch(this.watchId);

        this.watchId = navigator.geolocation.watchPosition(
            pos => {
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
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
    }

    stopWatching() {
        if (this.watchId) {
            navigator.geolocation.clearWatch(this.watchId);
            this.watchId = null;
        }
    }
}
