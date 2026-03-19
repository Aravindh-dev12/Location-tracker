const API_URL = '../api';

async function apiCall(endpoint, method = 'GET', data = null) {
    const options = {
        method: method,
        headers: { 'Content-Type': 'application/json' }
    };
    
    if (data) options.body = JSON.stringify(data);
    
    const response = await fetch(API_URL + '/' + endpoint, options);
    return await response.json();
}

async function submitWorkReport(description, hours, location) {
    return await apiCall('submit_work_report.php', 'POST', {
        description: description,
        hours: hours,
        latitude: location.latitude,
        longitude: location.longitude,
        method: location.method
    });
}

async function getWorkReports(filter = 'all') {
    return await apiCall('get_work_reports.php?filter=' + filter);
}

async function saveLocation(latitude, longitude, accuracy, method) {
    return await apiCall('save_location.php', 'POST', {
        latitude: latitude,
        longitude: longitude,
        accuracy: accuracy,
        method: method
    });
}

async function logout(location) {
    return await apiCall('auth_logout.php', 'POST', {
        user_id: getUser().id,
        latitude: location?.latitude,
        longitude: location?.longitude,
        method: location?.method
    });
}
