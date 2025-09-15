function toggleSidebar() {
    const layoutContainer = document.getElementById('layoutContainer');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    
    if (window.innerWidth <= 768) {
        // Mobile behavior
        sidebar.classList.toggle('mobile-open');
        if (sidebar.classList.contains('mobile-open')) {
            overlay.style.display = 'block';
        } else {
            overlay.style.display = 'none';
        }
    } else {
        // Desktop behavior
        layoutContainer.classList.toggle('sidebar-closed');
    }
}

function updateSidebarContent(content) {
    const sidebarContent = document.getElementById('sidebar-content');
    if (sidebarContent) {
        sidebarContent.innerHTML = content;
    }
}

function updateSidebarForProfile() {
    const profileContent = `
        <div style="margin-bottom: 20px;">
            <h6 style="color: #94a3b8; margin-bottom: 10px;">Profile Settings</h6>
            <a href="/web/profile.php" style="color: white; text-decoration: none; display: block; padding: 8px 0;">👤 Edit Profile</a>
            <a href="/web/settings.php" style="color: white; text-decoration: none; display: block; padding: 8px 0;">⚙️ Settings</a>
            <a href="/web/logout.php" style="color: #ef4444; text-decoration: none; display: block; padding: 8px 0;">🚪 Logout</a>
        </div>
    `;
    updateSidebarContent(profileContent);
}

// Close sidebar when clicking overlay
document.addEventListener('DOMContentLoaded', function() {
    const overlay = document.getElementById('sidebar-overlay');
    if (overlay) {
        overlay.addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            if (sidebar && sidebar.classList.contains('mobile-open')) {
                toggleSidebar();
            }
        });
    }
});