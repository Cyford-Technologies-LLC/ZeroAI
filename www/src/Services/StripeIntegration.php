<?php
namespace ZeroAI\Services;

require_once __DIR__ . '/../bootstrap.php';

class StripeIntegration {
    private $logger;
    private $integrationManager;
    private $config;
    
    public function __construct($organizationId) {
        $this->logger = \ZeroAI\Core\Logger::getInstance();
        $this->integrationManager = new IntegrationManager($organizationId);
        
        // Get Stripe configuration
        $integrations = $this->integrationManager->getIntegrations();
        $stripeIntegration = array_filter($integrations, fn($i) => $i['type'] === 'stripe');
        $this->config = !empty($stripeIntegration) ? reset($stripeIntegration)['config'] : null;
    }
    
    public function isConfigured() {
        return $this->config && 
               isset($this->config['publishable_key']) && 
               isset($this->config['secret_key']);
    }
    
    public function createPaymentIntent($amount, $currency = 'usd', $metadata = []) {
        if (!$this->isConfigured()) {
            throw new \Exception('Stripe integration not configured');
        }
        
        $data = [
            'amount' => $amount * 100, // Convert to cents
            'currency' => $currency,
            'metadata' => $metadata
        ];
        
        return $this->makeStripeRequest('payment_intents', $data, 'POST');
    }
    
    public function createCustomer($email, $name = null, $metadata = []) {
        if (!$this->isConfigured()) {
            throw new \Exception('Stripe integration not configured');
        }
        
        $data = [
            'email' => $email,
            'metadata' => $metadata
        ];
        
        if ($name) {
            $data['name'] = $name;
        }
        
        return $this->makeStripeRequest('customers', $data, 'POST');
    }
    
    public function getCustomer($customerId) {
        if (!$this->isConfigured()) {
            throw new \Exception('Stripe integration not configured');
        }
        
        return $this->makeStripeRequest("customers/{$customerId}", [], 'GET');
    }
    
    private function makeStripeRequest($endpoint, $data = [], $method = 'GET') {
        $url = "https://api.stripe.com/v1/{$endpoint}";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->config['secret_key'],
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $error = $result['error']['message'] ?? 'Stripe API error';
            throw new \Exception("Stripe API Error: {$error}");
        }
        
        return $result;
    }
    
    public function handleWebhook($payload, $signature) {
        if (!$this->isConfigured() || !isset($this->config['webhook_secret'])) {
            throw new \Exception('Stripe webhook not configured');
        }
        
        // Verify webhook signature
        $computedSignature = hash_hmac('sha256', $payload, $this->config['webhook_secret']);
        
        if (!hash_equals($signature, $computedSignature)) {
            throw new \Exception('Invalid webhook signature');
        }
        
        $event = json_decode($payload, true);
        
        // Log webhook event
        $this->integrationManager->logAction(
            $this->getStripeIntegrationId(),
            'webhook_received',
            'success',
            "Received {$event['type']} event",
            $event
        );
        
        return $event;
    }
    
    private function getStripeIntegrationId() {
        $integrations = $this->integrationManager->getIntegrations();
        $stripeIntegration = array_filter($integrations, fn($i) => $i['type'] === 'stripe');
        return !empty($stripeIntegration) ? reset($stripeIntegration)['id'] : null;
    }
}