<?php
// CRM Database Setup
require_once __DIR__ . '/../config/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Companies table
    $pdo->exec("CREATE TABLE IF NOT EXISTS companies (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT,
        phone TEXT,
        address TEXT,
        website TEXT,
        industry TEXT,
        status TEXT DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Contacts table
    $pdo->exec("CREATE TABLE IF NOT EXISTS contacts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        company_id INTEGER,
        first_name TEXT NOT NULL,
        last_name TEXT NOT NULL,
        email TEXT,
        phone TEXT,
        position TEXT,
        department TEXT,
        status TEXT DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (company_id) REFERENCES companies(id)
    )");
    
    // Projects table
    $pdo->exec("CREATE TABLE IF NOT EXISTS projects (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        company_id INTEGER,
        name TEXT NOT NULL,
        description TEXT,
        status TEXT DEFAULT 'active',
        priority TEXT DEFAULT 'medium',
        start_date DATE,
        end_date DATE,
        budget DECIMAL(10,2),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (company_id) REFERENCES companies(id)
    )");
    
    // Tasks table
    $pdo->exec("CREATE TABLE IF NOT EXISTS tasks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER,
        contact_id INTEGER,
        title TEXT NOT NULL,
        description TEXT,
        status TEXT DEFAULT 'pending',
        priority TEXT DEFAULT 'medium',
        due_date DATE,
        assigned_to TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES projects(id),
        FOREIGN KEY (contact_id) REFERENCES contacts(id)
    )");
    
    // Activities/Notes table
    $pdo->exec("CREATE TABLE IF NOT EXISTS activities (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        type TEXT NOT NULL,
        related_type TEXT,
        related_id INTEGER,
        title TEXT NOT NULL,
        description TEXT,
        created_by TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Insert sample data
    $pdo->exec("INSERT OR IGNORE INTO companies (id, name, email, phone, industry) VALUES 
        (1, 'Acme Corporation', 'contact@acme.com', '555-0123', 'Technology'),
        (2, 'Global Solutions Inc', 'info@globalsolutions.com', '555-0456', 'Consulting'),
        (3, 'Tech Innovators LLC', 'hello@techinnovators.com', '555-0789', 'Technology')
    ");
    
    $pdo->exec("INSERT OR IGNORE INTO contacts (id, company_id, first_name, last_name, email, position) VALUES 
        (1, 1, 'John', 'Smith', 'john.smith@acme.com', 'CEO'),
        (2, 1, 'Sarah', 'Johnson', 'sarah.johnson@acme.com', 'CTO'),
        (3, 2, 'Mike', 'Davis', 'mike.davis@globalsolutions.com', 'Project Manager'),
        (4, 3, 'Lisa', 'Wilson', 'lisa.wilson@techinnovators.com', 'Lead Developer')
    ");
    
    $pdo->exec("INSERT OR IGNORE INTO projects (id, company_id, name, description, status, priority) VALUES 
        (1, 1, 'Website Redesign', 'Complete overhaul of company website', 'active', 'high'),
        (2, 2, 'CRM Implementation', 'Deploy new CRM system', 'active', 'medium'),
        (3, 3, 'Mobile App Development', 'Create mobile application', 'planning', 'high')
    ");
    
    $pdo->exec("INSERT OR IGNORE INTO tasks (id, project_id, contact_id, title, status, priority) VALUES 
        (1, 1, 1, 'Review wireframes', 'pending', 'high'),
        (2, 1, 2, 'Setup development environment', 'in_progress', 'medium'),
        (3, 2, 3, 'Data migration planning', 'pending', 'high'),
        (4, 3, 4, 'Technical requirements gathering', 'completed', 'medium')
    ");
    
    echo "CRM database setup completed successfully!";
    
} catch (Exception $e) {
    echo "Error setting up CRM database: " . $e->getMessage();
}
?>