-- Multi-tenant CRM with Project Management Schema

-- Tenants (Top level)
CREATE TABLE IF NOT EXISTS tenants (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL,
    domain VARCHAR(255) UNIQUE,
    secret_key VARCHAR(255) NOT NULL,
    settings JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Companies (Under tenants)
CREATE TABLE IF NOT EXISTS companies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id INTEGER NOT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    secret_key VARCHAR(255) NOT NULL,
    logo VARCHAR(255),
    website VARCHAR(255),
    phone VARCHAR(50),
    email VARCHAR(255),
    address TEXT,
    industry VARCHAR(100),
    size VARCHAR(50),
    founded_year INTEGER,
    description TEXT,
    ai_description TEXT,
    social_media JSON,
    seo_settings JSON,
    branding JSON,
    settings JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    UNIQUE(tenant_id, slug)
);

-- Employees
CREATE TABLE IF NOT EXISTS employees (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id INTEGER NOT NULL,
    user_id INTEGER,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(50),
    position VARCHAR(100),
    department VARCHAR(100),
    salary DECIMAL(10,2),
    hire_date DATE,
    birth_date DATE,
    address TEXT,
    emergency_contact JSON,
    skills JSON,
    permissions JSON,
    avatar VARCHAR(255),
    status ENUM('active', 'inactive', 'terminated') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Projects
CREATE TABLE IF NOT EXISTS projects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id INTEGER NOT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    description TEXT,
    ai_description TEXT,
    status ENUM('planning', 'active', 'on_hold', 'completed', 'cancelled') DEFAULT 'planning',
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    start_date DATE,
    end_date DATE,
    budget DECIMAL(12,2),
    spent DECIMAL(12,2) DEFAULT 0,
    progress INTEGER DEFAULT 0,
    manager_id INTEGER,
    client_id INTEGER,
    secret_key VARCHAR(255) NOT NULL,
    repository_url VARCHAR(255),
    documentation_url VARCHAR(255),
    settings JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id),
    FOREIGN KEY (manager_id) REFERENCES employees(id),
    UNIQUE(company_id, slug)
);

-- Project Team Members
CREATE TABLE IF NOT EXISTS project_members (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    employee_id INTEGER NOT NULL,
    role VARCHAR(100),
    hourly_rate DECIMAL(8,2),
    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id),
    FOREIGN KEY (employee_id) REFERENCES employees(id),
    UNIQUE(project_id, employee_id)
);

-- Milestones
CREATE TABLE IF NOT EXISTS milestones (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    ai_description TEXT,
    due_date DATE,
    status ENUM('pending', 'in_progress', 'completed', 'overdue') DEFAULT 'pending',
    progress INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id)
);

-- Feature Sets
CREATE TABLE IF NOT EXISTS feature_sets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    milestone_id INTEGER,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    ai_description TEXT,
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    status ENUM('planned', 'in_progress', 'testing', 'completed', 'cancelled') DEFAULT 'planned',
    estimated_hours INTEGER,
    actual_hours INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id),
    FOREIGN KEY (milestone_id) REFERENCES milestones(id)
);

-- Tasks
CREATE TABLE IF NOT EXISTS tasks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    feature_set_id INTEGER,
    milestone_id INTEGER,
    assigned_to INTEGER,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    ai_description TEXT,
    status ENUM('todo', 'in_progress', 'review', 'testing', 'done', 'blocked') DEFAULT 'todo',
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    estimated_hours INTEGER,
    actual_hours INTEGER DEFAULT 0,
    due_date DATE,
    tags JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id),
    FOREIGN KEY (feature_set_id) REFERENCES feature_sets(id),
    FOREIGN KEY (milestone_id) REFERENCES milestones(id),
    FOREIGN KEY (assigned_to) REFERENCES employees(id)
);

-- Bugs
CREATE TABLE IF NOT EXISTS bugs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    feature_set_id INTEGER,
    task_id INTEGER,
    reported_by INTEGER,
    assigned_to INTEGER,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    ai_description TEXT,
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    status ENUM('open', 'in_progress', 'resolved', 'closed', 'wont_fix') DEFAULT 'open',
    steps_to_reproduce TEXT,
    expected_behavior TEXT,
    actual_behavior TEXT,
    environment TEXT,
    browser VARCHAR(100),
    os VARCHAR(100),
    resolution TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id),
    FOREIGN KEY (feature_set_id) REFERENCES feature_sets(id),
    FOREIGN KEY (task_id) REFERENCES tasks(id),
    FOREIGN KEY (reported_by) REFERENCES employees(id),
    FOREIGN KEY (assigned_to) REFERENCES employees(id)
);

-- Dependencies
CREATE TABLE IF NOT EXISTS dependencies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    dependent_type ENUM('task', 'feature_set', 'milestone') NOT NULL,
    dependent_id INTEGER NOT NULL,
    dependency_type ENUM('task', 'feature_set', 'milestone', 'external') NOT NULL,
    dependency_id INTEGER,
    external_name VARCHAR(255),
    relationship ENUM('blocks', 'depends_on', 'related') DEFAULT 'depends_on',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id)
);

-- Wishlist
CREATE TABLE IF NOT EXISTS wishlist (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    company_id INTEGER NOT NULL,
    submitted_by INTEGER,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    ai_description TEXT,
    category VARCHAR(100),
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'low',
    status ENUM('submitted', 'under_review', 'approved', 'rejected', 'implemented') DEFAULT 'submitted',
    votes INTEGER DEFAULT 0,
    estimated_effort VARCHAR(50),
    business_value VARCHAR(50),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id),
    FOREIGN KEY (company_id) REFERENCES companies(id),
    FOREIGN KEY (submitted_by) REFERENCES employees(id)
);

-- Clients/Customers
CREATE TABLE IF NOT EXISTS clients (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id INTEGER NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    phone VARCHAR(50),
    company_name VARCHAR(255),
    address TEXT,
    contact_person VARCHAR(255),
    industry VARCHAR(100),
    source VARCHAR(100),
    status ENUM('lead', 'prospect', 'active', 'inactive', 'lost') DEFAULT 'lead',
    lifetime_value DECIMAL(12,2) DEFAULT 0,
    notes TEXT,
    ai_notes TEXT,
    social_profiles JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id)
);

-- Time Tracking
CREATE TABLE IF NOT EXISTS time_entries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    employee_id INTEGER NOT NULL,
    task_id INTEGER,
    bug_id INTEGER,
    description TEXT,
    hours DECIMAL(4,2) NOT NULL,
    date DATE NOT NULL,
    billable BOOLEAN DEFAULT 1,
    rate DECIMAL(8,2),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id),
    FOREIGN KEY (employee_id) REFERENCES employees(id),
    FOREIGN KEY (task_id) REFERENCES tasks(id),
    FOREIGN KEY (bug_id) REFERENCES bugs(id)
);

-- Comments/Notes
CREATE TABLE IF NOT EXISTS comments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    entity_type ENUM('project', 'task', 'bug', 'milestone', 'feature_set', 'client') NOT NULL,
    entity_id INTEGER NOT NULL,
    employee_id INTEGER NOT NULL,
    content TEXT NOT NULL,
    ai_content TEXT,
    is_internal BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id)
);

-- File Attachments
CREATE TABLE IF NOT EXISTS attachments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    entity_type ENUM('project', 'task', 'bug', 'milestone', 'feature_set', 'client', 'company') NOT NULL,
    entity_id INTEGER NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_size INTEGER,
    mime_type VARCHAR(100),
    uploaded_by INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES employees(id)
);

-- SEO Tools
CREATE TABLE IF NOT EXISTS seo_keywords (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id INTEGER NOT NULL,
    keyword VARCHAR(255) NOT NULL,
    target_url VARCHAR(255),
    current_rank INTEGER,
    target_rank INTEGER,
    search_volume INTEGER,
    difficulty INTEGER,
    last_checked DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id)
);

-- API Tokens
CREATE TABLE IF NOT EXISTS api_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    entity_type ENUM('tenant', 'company', 'project') NOT NULL,
    entity_id INTEGER NOT NULL,
    token VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    permissions JSON,
    expires_at DATETIME,
    last_used DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);