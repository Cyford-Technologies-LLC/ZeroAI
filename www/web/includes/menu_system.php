<?php
// Dynamic Menu System
class MenuSystem {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->initMenuTable();
    }
    
    private function initMenuTable() {
        $sql = "
        CREATE TABLE IF NOT EXISTS menus (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            type TEXT NOT NULL DEFAULT 'sidebar',
            parent_id INTEGER NULL,
            url TEXT,
            icon TEXT,
            sort_order INTEGER DEFAULT 0,
            is_active BOOLEAN DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (parent_id) REFERENCES menus(id)
        );
        
        INSERT OR IGNORE INTO menus (id, name, type, url, icon, sort_order, is_active) VALUES 
        (1, 'Dashboard', 'header', '/web/index.php', '📊', 1, 1),
        (2, 'Companies', 'header', '/web/companies.php', '🏢', 2, 1),
        (3, 'Sales', 'header', '/web/sales.php', '💰', 3, 1),
        (4, 'Projects', 'header', '/web/projects.php', '📋', 4, 1),
        (5, 'AI Workshop', 'header', '/web/ai_workshop.php', '🤖', 5, 1),
        
        (10, 'All Companies', 'sidebar', '/web/companies.php', '🏢', 1, 1),
        (11, 'Contacts', 'sidebar', '/web/contacts.php', '👥', 2, 1),
        (12, 'Locations', 'sidebar', '/web/locations.php', '📍', 3, 1),
        (13, 'Documents', 'sidebar', '/web/documents.php?context=companies', '📄', 4, 1),
        
        (20, 'All Projects', 'sidebar', '/web/projects.php', '📋', 1, 1),
        (21, 'Tasks', 'sidebar', '/web/tasks.php', '✅', 2, 1),
        (22, 'Bugs', 'sidebar', '/web/bugs.php', '🐛', 3, 1),
        (23, 'Features', 'sidebar', '/web/features.php', '✨', 4, 1),
        (24, 'Team', 'sidebar', '/web/team.php', '👥', 5, 1),
        (25, 'Releases', 'sidebar', '/web/releases.php', '🚀', 6, 1),
        (26, 'Project Documents', 'sidebar', '/web/documents.php?context=projects', '📄', 7, 1);
        ";
        
        $this->pdo->exec($sql);
    }
    
    public function getHeaderMenus() {
        $stmt = $this->pdo->prepare("SELECT * FROM menus WHERE type = 'header' AND is_active = 1 ORDER BY sort_order, name");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getSidebarMenus($context = 'main') {
        // Get sidebar menus based on context
        $stmt = $this->pdo->prepare("SELECT * FROM menus WHERE type = 'sidebar' AND is_active = 1 ORDER BY sort_order, name");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function renderHeaderMenu($currentPage = '') {
        $menus = $this->getHeaderMenus();
        $html = '';
        
        foreach ($menus as $menu) {
            $this->ensurePageExists($menu['url']);
            
            $isActive = ($currentPage === strtolower(str_replace(' ', '_', $menu['name'])));
            $activeClass = $isActive ? 'background: rgba(255,255,255,0.2);' : '';
            
            $html .= sprintf(
                '<a href="%s" style="color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px; %s">%s %s</a>',
                htmlspecialchars($menu['url']),
                $activeClass,
                htmlspecialchars($menu['icon']),
                htmlspecialchars($menu['name'])
            );
        }
        
        return $html;
    }
    
    public function renderSidebarMenu($context = 'main', $contextId = null) {
        $menus = $this->getSidebarMenus($context);
        $html = '';
        
        // Filter menus based on context
        $filteredMenus = $this->filterMenusByContext($menus, $context);
        
        if (!empty($filteredMenus)) {
            $html .= '<div style="margin-bottom: 20px;">';
            $html .= '<h6 style="color: #94a3b8; margin-bottom: 10px;">Navigation</h6>';
            
            foreach ($filteredMenus as $menu) {
                $url = $menu['url'];
                
                // Add context ID to URL if needed
                if ($contextId && strpos($url, '?') !== false) {
                    $url .= '&' . $this->getContextParam($context) . '=' . $contextId;
                } elseif ($contextId) {
                    $url .= '?' . $this->getContextParam($context) . '=' . $contextId;
                }
                
                $this->ensurePageExists($url);
                
                $html .= sprintf(
                    '<a href="%s" style="color: white; text-decoration: none; display: block; padding: 8px 0;">%s %s</a>',
                    htmlspecialchars($url),
                    htmlspecialchars($menu['icon']),
                    htmlspecialchars($menu['name'])
                );
            }
            
            $html .= '</div>';
        }
        
        return $html;
    }
    
    private function filterMenusByContext($menus, $context) {
        switch ($context) {
            case 'companies':
                return array_filter($menus, function($menu) {
                    return in_array($menu['id'], [10, 11, 12, 13]);
                });
            case 'projects':
                return array_filter($menus, function($menu) {
                    return in_array($menu['id'], [20, 21, 22, 23, 24, 25, 26]);
                });
            default:
                return array_filter($menus, function($menu) {
                    return in_array($menu['id'], [10, 11, 20, 21]);
                });
        }
    }
    
    private function getContextParam($context) {
        switch ($context) {
            case 'companies': return 'company_id';
            case 'projects': return 'project_id';
            default: return 'id';
        }
    }
    
    private function ensurePageExists($url) {
        // Extract file path from URL
        $path = parse_url($url, PHP_URL_PATH);
        if (!$path) return;
        
        // Convert to file system path
        $filePath = __DIR__ . '/../..' . $path;
        
        // Skip if file already exists
        if (file_exists($filePath)) return;
        
        // Create directory if needed
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // Generate page name from URL
        $pageName = ucfirst(str_replace(['.php', '_'], ['', ' '], basename($path)));
        $pageVar = strtolower(str_replace(' ', '_', $pageName));
        
        // Create basic page template
        $template = "<?php\n" .
            "\$pageTitle = '{$pageName} - ZeroAI CRM';\n" .
            "\$currentPage = '{$pageVar}';\n" .
            "include __DIR__ . '/includes/header.php';\n" .
            "?>\n\n" .
            "<div class=\"container-fluid mt-4\">\n" .
            "    <div class=\"row\">\n" .
            "        <div class=\"col-12\">\n" .
            "            <h1 class=\"h2 mb-4\">{$pageName}</h1>\n" .
            "            <div class=\"card\">\n" .
            "                <div class=\"card-body\">\n" .
            "                    <p>This page was automatically generated. Please customize it as needed.</p>\n" .
            "                </div>\n" .
            "            </div>\n" .
            "        </div>\n" .
            "    </div>\n" .
            "</div>\n\n" .
            "<?php include __DIR__ . '/includes/footer.php'; ?>";
        
        file_put_contents($filePath, $template);
    }
}
?>