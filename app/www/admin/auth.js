// Authentication helper for ZeroAI admin pages
function getAuthToken() {
    return localStorage.getItem('zeroai_token');
}

function setAuthHeaders() {
    const token = getAuthToken();
    return token ? { 'Authorization': `Bearer ${token}` } : {};
}

function checkAuth() {
    const token = getAuthToken();
    if (!token) {
        window.location.href = '/login';
        return false;
    }
    return true;
}

function logout() {
    localStorage.removeItem('zeroai_token');
    window.location.href = '/login';
}

// Add auth headers to all fetch requests
const originalFetch = window.fetch;
window.fetch = function(url, options = {}) {
    options.headers = {
        ...options.headers,
        ...setAuthHeaders()
    };
    
    return originalFetch(url, options).then(response => {
        if (response.status === 401) {
            logout();
            throw new Error('Authentication required');
        }
        return response;
    });
};

// Check auth on page load
document.addEventListener('DOMContentLoaded', function() {
    if (window.location.pathname.startsWith('/admin')) {
        checkAuth();
    }
});