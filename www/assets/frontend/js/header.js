function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    const mainContent = document.querySelector('.container-fluid, .container');
    const header = document.getElementById('main-header');
    const body = document.body;

    if (!sidebar || !overlay) return;

    const isOpen = sidebar.style.left === '0px';

    if (isOpen) {
        sidebar.style.left = '-250px';
        overlay.style.display = 'none';
        body.classList.remove('sidebar-open');
        if (mainContent) {
            mainContent.style.marginLeft = '0';
            mainContent.style.transition = 'margin-left 0.3s ease';
        }
        if (header) {
            header.style.marginLeft = '0';
        }
    } else {
        sidebar.style.left = '0px';
        overlay.style.display = 'block';
        body.classList.add('sidebar-open');
        if (window.innerWidth > 768) {
            if (mainContent) {
                mainContent.style.marginLeft = '250px';
                mainContent.style.transition = 'margin-left 0.3s ease';
            }
            if (header) {
                header.style.marginLeft = '250px';
            }
        }
        updateSidebarContent();
    }
}

function updateSidebarContent() {
    const currentPage = window.location.pathname;
    const content = document.getElementById('sidebar-content');
    if (!content) return;
    
    let links = '';
    
    if (currentPage.includes('/companies') || currentPage.includes('/contacts') || currentPage.includes('/employees') || currentPage.includes('/locations')) {
        links = `
            <a href="/web/contacts.php" style="display: block; color: #cbd5e1; text-decoration: none; padding: 12px 0; border-bottom: 1px solid #334155;">👥 Contacts</a>
            <a href="/web/employees.php" style="display: block; color: #cbd5e1; text-decoration: none; padding: 12px 0; border-bottom: 1px solid #334155;">👨💼 Employees</a>
            <a href="/web/locations.php" style="display: block; color: #cbd5e1; text-decoration: none; padding: 12px 0; border-bottom: 1px solid #334155;">📍 Locations</a>
        `;
    } else if (currentPage.includes('/projects') || currentPage.includes('/tasks') || currentPage.includes('/features') || currentPage.includes('/bugs')) {
        links = `
            <a href="/web/tasks.php" style="display: block; color: #cbd5e1; text-decoration: none; padding: 12px 0; border-bottom: 1px solid #334155;">✅ Tasks</a>
            <a href="/web/features.php" style="display: block; color: #cbd5e1; text-decoration: none; padding: 12px 0; border-bottom: 1px solid #334155;">✨ Features</a>
            <a href="/web/bugs.php" style="display: block; color: #cbd5e1; text-decoration: none; padding: 12px 0; border-bottom: 1px solid #334155;">🐛 Bugs</a>
        `;
    } else if (currentPage.includes('/sales') || currentPage.includes('/leads') || currentPage.includes('/opportunities') || currentPage.includes('/quotes')) {
        links = `
            <a href="/web/leads.php" style="display: block; color: #cbd5e1; text-decoration: none; padding: 12px 0; border-bottom: 1px solid #334155;">📋 Leads</a>
            <a href="/web/opportunities.php" style="display: block; color: #cbd5e1; text-decoration: none; padding: 12px 0; border-bottom: 1px solid #334155;">💰 Opportunities</a>
            <a href="/web/quotes.php" style="display: block; color: #cbd5e1; text-decoration: none; padding: 12px 0; border-bottom: 1px solid #334155;">📄 Quotes</a>
        `;
    } else {
        links = '<p style="color: #94a3b8; font-size: 0.9rem;">No sub-menu available</p>';
    }
    
    content.innerHTML = links;
}

function updateSidebarForProfile() {
    const content = document.getElementById('sidebar-content');
    if (!content) return;
    
    const links = `
        <div style="padding: 12px 0; border-bottom: 1px solid #334155; color: #e2e8f0;">
            <strong>👤 User</strong>
        </div>
        <a href="/web/profile.php" style="display: block; color: #cbd5e1; text-decoration: none; padding: 12px 0; border-bottom: 1px solid #334155;">⚙️ Settings</a>
        <a href="/web/logout.php" style="display: block; color: #f87171; text-decoration: none; padding: 12px 0; border-bottom: 1px solid #334155;">🚪 Logout</a>
    `;
    
    content.innerHTML = links;
}

document.addEventListener('DOMContentLoaded', function () {
    const overlay = document.getElementById('sidebar-overlay');
    if (overlay) {
        overlay.addEventListener('click', toggleSidebar);
    }
});