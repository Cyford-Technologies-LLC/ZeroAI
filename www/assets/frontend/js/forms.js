// Add Bootstrap JS for collapse functionality
document.addEventListener('DOMContentLoaded', function() {
    // Auto-expand form if there was a form submission error or success
    const alerts = document.querySelectorAll('.alert');
    if (alerts.length > 0) {
        const addForms = document.querySelectorAll('[id*="addForm"], [id*="AddForm"]');
        addForms.forEach(form => {
            if (form.classList.contains('collapse')) {
                form.classList.add('show');
            }
        });
    }
});