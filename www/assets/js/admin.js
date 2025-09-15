// ZeroAI Admin Panel - Common JavaScript Functions

// Utility Functions
function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.textContent = message;
    
    const container = document.querySelector('.container');
    if (container) {
        container.insertBefore(alertDiv, container.firstChild);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.parentNode.removeChild(alertDiv);
            }
        }, 5000);
    }
}

function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// AJAX Helper
async function apiRequest(url, data = null, method = 'GET') {
    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/json',
        }
    };
    
    if (data && method !== 'GET') {
        options.body = JSON.stringify(data);
    }
    
    try {
        const response = await fetch(url, options);
        return await response.json();
    } catch (error) {
        console.error('API Request failed:', error);
        throw error;
    }
}

// Form Helpers
function serializeForm(form) {
    const formData = new FormData(form);
    const data = {};
    
    for (let [key, value] of formData.entries()) {
        data[key] = value;
    }
    
    return data;
}

function validateForm(form) {
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            isValid = false;
        } else {
            field.classList.remove('is-invalid');
        }
    });
    
    return isValid;
}

// Table Helpers
function sortTable(table, column, direction = 'asc') {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    rows.sort((a, b) => {
        const aVal = a.cells[column].textContent.trim();
        const bVal = b.cells[column].textContent.trim();
        
        if (direction === 'asc') {
            return aVal.localeCompare(bVal);
        } else {
            return bVal.localeCompare(aVal);
        }
    });
    
    rows.forEach(row => tbody.appendChild(row));
}

function filterTable(table, searchTerm) {
    const tbody = table.querySelector('tbody');
    const rows = tbody.querySelectorAll('tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (text.includes(searchTerm.toLowerCase())) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Loading States
function showLoading(element) {
    const originalText = element.textContent;
    element.textContent = 'Loading...';
    element.disabled = true;
    element.dataset.originalText = originalText;
}

function hideLoading(element) {
    element.textContent = element.dataset.originalText || 'Submit';
    element.disabled = false;
}

// Modal Functions
function createModal(title, content, actions = []) {
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
    `;
    
    const modalContent = document.createElement('div');
    modalContent.className = 'modal-content';
    modalContent.style.cssText = `
        background: white;
        border-radius: 12px;
        padding: 25px;
        max-width: 500px;
        width: 90%;
        max-height: 80vh;
        overflow-y: auto;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    `;
    
    const modalHeader = document.createElement('div');
    modalHeader.style.cssText = `
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid #dee2e6;
    `;
    
    const modalTitle = document.createElement('h3');
    modalTitle.textContent = title;
    modalTitle.style.margin = '0';
    
    const closeBtn = document.createElement('button');
    closeBtn.innerHTML = '&times;';
    closeBtn.style.cssText = `
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: #6c757d;
    `;
    closeBtn.onclick = () => document.body.removeChild(modal);
    
    modalHeader.appendChild(modalTitle);
    modalHeader.appendChild(closeBtn);
    
    const modalBody = document.createElement('div');
    modalBody.innerHTML = content;
    modalBody.style.marginBottom = '20px';
    
    modalContent.appendChild(modalHeader);
    modalContent.appendChild(modalBody);
    
    if (actions.length > 0) {
        const modalFooter = document.createElement('div');
        modalFooter.style.cssText = `
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            padding-top: 15px;
            border-top: 1px solid #dee2e6;
        `;
        
        actions.forEach(action => {
            const btn = document.createElement('button');
            btn.textContent = action.text;
            btn.className = `btn ${action.class || 'btn-secondary'}`;
            btn.onclick = () => {
                if (action.callback) action.callback();
                document.body.removeChild(modal);
            };
            modalFooter.appendChild(btn);
        });
        
        modalContent.appendChild(modalFooter);
    }
    
    modal.appendChild(modalContent);
    document.body.appendChild(modal);
    
    // Close on overlay click
    modal.onclick = (e) => {
        if (e.target === modal) {
            document.body.removeChild(modal);
        }
    };
    
    return modal;
}

// Auto-refresh functionality
function setupAutoRefresh(interval = 30000) {
    setInterval(() => {
        const refreshElements = document.querySelectorAll('[data-auto-refresh]');
        refreshElements.forEach(element => {
            const url = element.dataset.autoRefresh;
            if (url) {
                fetch(url)
                    .then(response => response.text())
                    .then(html => {
                        element.innerHTML = html;
                    })
                    .catch(error => {
                        console.error('Auto-refresh failed:', error);
                    });
            }
        });
    }, interval);
}

// Initialize common functionality
document.addEventListener('DOMContentLoaded', function() {
    // Add click handlers for sortable table headers
    document.querySelectorAll('.sortable').forEach(header => {
        header.style.cursor = 'pointer';
        header.onclick = function() {
            const table = this.closest('table');
            const column = Array.from(this.parentNode.children).indexOf(this);
            const currentDirection = this.dataset.direction || 'asc';
            const newDirection = currentDirection === 'asc' ? 'desc' : 'asc';
            
            sortTable(table, column, newDirection);
            this.dataset.direction = newDirection;
            
            // Update visual indicator
            document.querySelectorAll('.sortable').forEach(h => h.classList.remove('sorted-asc', 'sorted-desc'));
            this.classList.add(`sorted-${newDirection}`);
        };
    });
    
    // Add search functionality
    document.querySelectorAll('.table-search').forEach(searchInput => {
        searchInput.oninput = function() {
            const table = document.querySelector(this.dataset.target);
            if (table) {
                filterTable(table, this.value);
            }
        };
    });
    
    // Form validation
    document.querySelectorAll('form[data-validate]').forEach(form => {
        form.onsubmit = function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
                showAlert('Please fill in all required fields', 'danger');
            }
        };
    });
    
    // Setup auto-refresh if enabled
    if (document.querySelector('[data-auto-refresh]')) {
        setupAutoRefresh();
    }
});