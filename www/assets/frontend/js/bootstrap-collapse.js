// Simple collapse functionality without full Bootstrap JS
function toggleCollapse(targetId) {
    const target = document.getElementById(targetId);
    if (target) {
        if (target.style.display === 'none' || target.style.display === '') {
            target.style.display = 'block';
        } else {
            target.style.display = 'none';
        }
    }
}

// Initialize collapse functionality
document.addEventListener('DOMContentLoaded', function() {
    // Handle all collapse buttons
    const collapseButtons = document.querySelectorAll('[data-bs-toggle="collapse"]');
    collapseButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('data-bs-target').replace('#', '');
            toggleCollapse(targetId);
        });
    });
    
    // Hide all collapse elements by default
    const collapseElements = document.querySelectorAll('.collapse');
    collapseElements.forEach(element => {
        element.style.display = 'none';
    });
});