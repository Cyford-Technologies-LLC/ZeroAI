<?php
namespace ZeroAI\Services;

require_once __DIR__ . '/../bootstrap.php';

class SubscriptionManager {
    private $logger;
    private $tenantDb;
    private $organizationId;
    
    public function __construct($organizationId) {
        $this->logger = \ZeroAI\Core\Logger::getInstance();
        $this->organizationId = $organizationId;
        
        $tenantManager = new TenantManager();
        $this->tenantDb = $tenantManager->getTenantDatabase($organizationId);
        
        $this->ensureSubscriptionTables();
    }
    
    private function ensureSubscriptionTables() {
        $sql = "
        CREATE TABLE IF NOT EXISTS subscriptions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            plan_id TEXT NOT NULL,
            plan_name TEXT NOT NULL,
            status TEXT DEFAULT 'active',
            price DECIMAL(10,2) NOT NULL,
            billing_cycle TEXT NOT NULL,
            current_period_start DATE,
            current_period_end DATE,
            trial_end DATE,
            stripe_subscription_id TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS token_balance (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tokens_available INTEGER DEFAULT 0,
            tokens_used INTEGER DEFAULT 0,
            tokens_purchased INTEGER DEFAULT 0,
            last_reset_date DATE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS token_transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            type TEXT NOT NULL, -- 'purchase', 'usage', 'refund', 'bonus'
            amount INTEGER NOT NULL,
            description TEXT,
            reference_id TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS usage_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            feature TEXT NOT NULL,
            tokens_used INTEGER NOT NULL,
            description TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        ";
        
        $this->tenantDb->execute($sql);
    }
    
    public function createSubscription($planId, $planName, $price, $billingCycle, $stripeSubscriptionId = null) {
        $trialEnd = date('Y-m-d', strtotime('+14 days'));
        $periodStart = date('Y-m-d');
        $periodEnd = date('Y-m-d', strtotime('+1 month'));
        
        $data = [
            'plan_id' => $planId,
            'plan_name' => $planName,
            'price' => $price,
            'billing_cycle' => $billingCycle,
            'current_period_start' => $periodStart,
            'current_period_end' => $periodEnd,
            'trial_end' => $trialEnd,
            'stripe_subscription_id' => $stripeSubscriptionId,
            'status' => 'trialing'
        ];
        
        return $this->tenantDb->insert('subscriptions', $data);
    }
    
    public function getCurrentSubscription() {
        $subscriptions = $this->tenantDb->select('subscriptions', '*', ['status' => 'active'], 1);
        return !empty($subscriptions) ? $subscriptions[0] : null;
    }
    
    public function addTokens($amount, $type = 'purchase', $description = '', $referenceId = '') {
        // Add to balance
        $balance = $this->getTokenBalance();
        $newAvailable = $balance['tokens_available'] + $amount;
        $newPurchased = $balance['tokens_purchased'] + ($type === 'purchase' ? $amount : 0);
        
        $this->tenantDb->update('token_balance', [
            'tokens_available' => $newAvailable,
            'tokens_purchased' => $newPurchased,
            'updated_at' => date('Y-m-d H:i:s')
        ], ['id' => $balance['id']]);
        
        // Log transaction
        $this->tenantDb->insert('token_transactions', [
            'type' => $type,
            'amount' => $amount,
            'description' => $description,
            'reference_id' => $referenceId
        ]);
        
        return $newAvailable;
    }
    
    public function useTokens($amount, $feature, $description = '') {
        $balance = $this->getTokenBalance();
        
        if ($balance['tokens_available'] < $amount) {
            throw new \Exception('Insufficient tokens available');
        }
        
        $newAvailable = $balance['tokens_available'] - $amount;
        $newUsed = $balance['tokens_used'] + $amount;
        
        // Update balance
        $this->tenantDb->update('token_balance', [
            'tokens_available' => $newAvailable,
            'tokens_used' => $newUsed,
            'updated_at' => date('Y-m-d H:i:s')
        ], ['id' => $balance['id']]);
        
        // Log transaction
        $this->tenantDb->insert('token_transactions', [
            'type' => 'usage',
            'amount' => -$amount,
            'description' => $description,
            'reference_id' => $feature
        ]);
        
        // Log usage
        $this->tenantDb->insert('usage_logs', [
            'feature' => $feature,
            'tokens_used' => $amount,
            'description' => $description
        ]);
        
        return $newAvailable;
    }
    
    public function getTokenBalance() {
        $balance = $this->tenantDb->select('token_balance', '*', [], 1);
        
        if (empty($balance)) {
            // Create initial balance record
            $id = $this->tenantDb->insert('token_balance', [
                'tokens_available' => 0,
                'tokens_used' => 0,
                'tokens_purchased' => 0,
                'last_reset_date' => date('Y-m-d')
            ]);
            
            return [
                'id' => $id,
                'tokens_available' => 0,
                'tokens_used' => 0,
                'tokens_purchased' => 0,
                'last_reset_date' => date('Y-m-d')
            ];
        }
        
        return $balance[0];
    }
    
    public function getUsageStats($days = 30) {
        $startDate = date('Y-m-d', strtotime("-{$days} days"));
        
        // Get usage by feature
        $usageByFeature = $this->tenantDb->getConnection()->prepare("
            SELECT feature, SUM(tokens_used) as total_tokens, COUNT(*) as usage_count
            FROM usage_logs 
            WHERE DATE(created_at) >= ? 
            GROUP BY feature 
            ORDER BY total_tokens DESC
        ");
        $usageByFeature->execute([$startDate]);
        
        // Get daily usage
        $dailyUsage = $this->tenantDb->getConnection()->prepare("
            SELECT DATE(created_at) as date, SUM(tokens_used) as tokens_used
            FROM usage_logs 
            WHERE DATE(created_at) >= ? 
            GROUP BY DATE(created_at) 
            ORDER BY date DESC
        ");
        $dailyUsage->execute([$startDate]);
        
        return [
            'by_feature' => $usageByFeature->fetchAll(\PDO::FETCH_ASSOC),
            'daily_usage' => $dailyUsage->fetchAll(\PDO::FETCH_ASSOC)
        ];
    }
    
    public function resetMonthlyTokens() {
        $subscription = $this->getCurrentSubscription();
        if (!$subscription) return false;
        
        // Get monthly token allowance based on plan
        $monthlyTokens = $this->getMonthlyTokenAllowance($subscription['plan_id']);
        
        if ($monthlyTokens > 0) {
            $this->addTokens($monthlyTokens, 'monthly_reset', 'Monthly subscription token reset');
            
            // Update last reset date
            $balance = $this->getTokenBalance();
            $this->tenantDb->update('token_balance', [
                'last_reset_date' => date('Y-m-d')
            ], ['id' => $balance['id']]);
            
            return true;
        }
        
        return false;
    }
    
    private function getMonthlyTokenAllowance($planId) {
        $allowances = [
            'starter' => 5000,
            'professional' => 25000,
            'enterprise' => -1 // Unlimited
        ];
        
        return $allowances[$planId] ?? 0;
    }
}