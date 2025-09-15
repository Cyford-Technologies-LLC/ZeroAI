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

// Close sidebar when clicking overlay and initialize sidebar content
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
    
    // Initialize sidebar content based on current page
    const currentPath = window.location.pathname;
    if (currentPath.includes('companies')) {
        updateSidebarForCompanies();
    } else if (currentPath.includes('projects')) {
        updateSidebarForProjects();
    } else if (currentPath.includes('contacts')) {
        updateSidebarForContacts();
    } else if (currentPath.includes('tasks')) {
        updateSidebarForTasks();
    }
});

function updateSidebarForCompanies() {
    const content = `
        <div style="margin-bottom: 20px;">
            <h6 style="color: #94a3b8; margin-bottom: 10px;">Company Actions</h6>
            <a href="#add-company" style="color: #0dcaf0; text-decoration: none; display: block; padding: 8px 0;">+ Add Company</a>
            <a href="/web/locations.php" style="color: white; text-decoration: none; display: block; padding: 8px 0;">📍 Locations</a>
            <a href="/web/employees.php" style="color: white; text-decoration: none; display: block; padding: 8px 0;">👥 Employees</a>
            <a href="/web/contacts.php" style="color: white; text-decoration: none; display: block; padding: 8px 0;">☎️ Contacts</a>
            <a href="/web/marketing.php" style="color: white; text-decoration: none; display: block; padding: 8px 0;">📊 Marketing</a>
        </div>
    `;
    updateSidebarContent(content);
}

function updateSidebarForProjects() {
    const content = `
        <div style="margin-bottom: 20px;">
            <h6 style="color: #94a3b8; margin-bottom: 10px;">Project Actions</h6>
            <a href="#add-project" style="color: #0dcaf0; text-decoration: none; display: block; padding: 8px 0;">+ Add Project</a>
            <a href="/web/tasks.php" style="color: white; text-decoration: none; display: block; padding: 8px 0;">✅ Tasks</a>
            <a href="/web/bugs.php" style="color: white; text-decoration: none; display: block; padding: 8px 0;">🐛 Bugs</a>
            <a href="/web/features.php" style="color: white; text-decoration: none; display: block; padding: 8px 0;">✨ Features</a>
            <a href="/web/releases.php" style="color: white; text-decoration: none; display: block; padding: 8px 0;">🚀 Releases</a>
            <a href="/web/team.php" style="color: white; text-decoration: none; display: block; padding: 8px 0;">👥 Team</a>
        </div>
    `;
    updateSidebarContent(content);
}

function updateSidebarForContacts() {
    const content = `
        <div style="margin-bottom: 20px;">
            <h6 style="color: #94a3b8; margin-bottom: 10px;">Contact Actions</h6>
            <a href="#add-contact" style="color: #0dcaf0; text-decoration: none; display: block; padding: 8px 0;">+ Add Contact</a>
            <a href="/web/companies.php" style="color: white; text-decoration: none; display: block; padding: 8px 0;">🏢 View Companies</a>
        </div>
    `;
    updateSidebarContent(content);
}

function updateSidebarForTasks() {
    const content = `
        <div style="margin-bottom: 20px;">
            <h6 style="color: #94a3b8; margin-bottom: 10px;">Task Actions</h6>
            <a href="#add-task" style="color: #0dcaf0; text-decoration: none; display: block; padding: 8px 0;">+ Add Task</a>
            <a href="/web/projects.php" style="color: white; text-decoration: none; display: block; padding: 8px 0;">📋 View Projects</a>
        </div>
    `;
    updateSidebarContent(content);
}