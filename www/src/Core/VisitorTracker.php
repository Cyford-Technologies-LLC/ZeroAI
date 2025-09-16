<?php
namespace ZeroAI\Core;

class VisitorTracker {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseManager::getInstance();
        $this->initTables();
    }
    
    private function initTables() {
        // IP tracking table
        $this->db->query("CREATE TABLE IF NOT EXISTS visitor_ips (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip_address TEXT NOT NULL,
            username TEXT,
            user_agent TEXT,
            country TEXT,
            city TEXT,
            first_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
            visit_count INTEGER DEFAULT 1,
            is_blocked BOOLEAN DEFAULT 0,
            UNIQUE(ip_address)
        )");
        
        // Login attempts table
        $this->db->query("CREATE TABLE IF NOT EXISTS login_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip_address TEXT NOT NULL,
            username TEXT,
            success BOOLEAN DEFAULT 0,
            user_agent TEXT,
            attempt_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            failure_reason TEXT
        )");
        
        // Page visits table
        $this->db->query("CREATE TABLE IF NOT EXISTS page_visits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip_address TEXT NOT NULL,
            username TEXT,
            page_url TEXT NOT NULL,
            referrer TEXT,
            user_agent TEXT,
            visit_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            session_id TEXT
        )");
        
        // Companies/organizations table
        $this->db->query("CREATE TABLE IF NOT EXISTS visitor_companies (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip_address TEXT NOT NULL UNIQUE,
            company_name TEXT,
            organization TEXT,
            isp TEXT,
            lookup_count INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_updated DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }
    
    public function trackVisitor($username = null) {
        $ip = $this->getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $sessionId = session_id();
        
        // Upsert IP record with count tracking
        $this->db->query("
            INSERT INTO visitor_ips (ip_address, username, user_agent, visit_count, first_seen, last_seen) 
            VALUES (?, ?, ?, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON CONFLICT(ip_address) DO UPDATE SET
                username = COALESCE(excluded.username, username),
                user_agent = excluded.user_agent,
                visit_count = visit_count + 1,
                last_seen = CURRENT_TIMESTAMP
        ", [$ip, $username, $userAgent]);
        
        // Track page visit
        $this->db->insert('page_visits', [
            'ip_address' => $ip,
            'username' => $username,
            'page_url' => $_SERVER['REQUEST_URI'] ?? '/',
            'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
            'user_agent' => $userAgent,
            'session_id' => $sessionId
        ]);
    }
    
    public function trackLogin($username, $success, $failureReason = null) {
        $ip = $this->getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $this->db->insert('login_attempts', [
            'ip_address' => $ip,
            'username' => $username,
            'success' => $success ? 1 : 0,
            'user_agent' => $userAgent,
            'failure_reason' => $failureReason
        ]);
        
        // Update visitor record with username if successful
        if ($success) {
            $this->trackVisitor($username);
        }
    }
    
    public function getStats() {
        return [
            'total_visitors' => $this->db->query("SELECT COUNT(DISTINCT ip_address) as count FROM visitor_ips")[0]['count'],
            'total_users' => $this->db->query("SELECT COUNT(DISTINCT username) as count FROM visitor_ips WHERE username IS NOT NULL")[0]['count'],
            'today_visits' => $this->db->query("SELECT COUNT(*) as count FROM page_visits WHERE DATE(visit_time) = DATE('now')")[0]['count'],
            'failed_logins_today' => $this->db->query("SELECT COUNT(*) as count FROM login_attempts WHERE success = 0 AND DATE(attempt_time) = DATE('now')")[0]['count'],
            'successful_logins_today' => $this->db->query("SELECT COUNT(*) as count FROM login_attempts WHERE success = 1 AND DATE(attempt_time) = DATE('now')")[0]['count']
        ];
    }
    
    public function getTopVisitors($limit = 10) {
        return $this->db->query("SELECT ip_address, username, visit_count, last_seen FROM visitor_ips ORDER BY visit_count DESC LIMIT $limit");
    }
    
    public function getRecentLogins($limit = 20) {
        return $this->db->query("SELECT * FROM login_attempts ORDER BY attempt_time DESC LIMIT $limit");
    }
    
    public function getFailedLogins($hours = 24) {
        return $this->db->query("SELECT ip_address, username, COUNT(*) as attempts, MAX(attempt_time) as last_attempt 
                                FROM login_attempts 
                                WHERE success = 0 AND attempt_time > datetime('now', '-$hours hours') 
                                GROUP BY ip_address, username 
                                ORDER BY attempts DESC");
    }
    
    public function trackCompanyLookup($companyName = null, $organization = null, $isp = null) {
        $ip = $this->getClientIP();
        
        // Upsert company record with count tracking
        $this->db->query("
            INSERT INTO visitor_companies (ip_address, company_name, organization, isp, lookup_count, created_at, last_updated) 
            VALUES (?, ?, ?, ?, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON CONFLICT(ip_address) DO UPDATE SET
                company_name = COALESCE(excluded.company_name, company_name),
                organization = COALESCE(excluded.organization, organization),
                isp = COALESCE(excluded.isp, isp),
                lookup_count = lookup_count + 1,
                last_updated = CURRENT_TIMESTAMP
        ", [$ip, $companyName, $organization, $isp]);
    }
    
    private function getClientIP() {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}


