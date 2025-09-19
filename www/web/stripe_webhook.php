<?php
// Stripe webhook handler
header('Content-Type: application/json');

try {
    // Get the payload and signature
    $payload = file_get_contents('php://input');
    $signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
    
    if (empty($payload) || empty($signature)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing payload or signature']);
        exit;
    }
    
    // For webhook handling, we need to determine which organization this belongs to
    // This is a simplified approach - in production you might want to include org ID in webhook URL
    require_once __DIR__ . '/../src/autoload.php';
    require_once __DIR__ . '/../src/Services/StripeIntegration.php';
    
    // Get all organizations with Stripe configured (simplified approach)
    require_once __DIR__ . '/../config/database.php';
    $db = new Database();
    $pdo = $db->getConnection();
    
    $stmt = $pdo->prepare("SELECT DISTINCT organization_id FROM users WHERE organization_id IS NOT NULL");
    $stmt->execute();
    $organizations = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $eventHandled = false;
    
    foreach ($organizations as $orgId) {
        try {
            $stripe = new \ZeroAI\Services\StripeIntegration($orgId);
            if ($stripe->isConfigured()) {
                $event = $stripe->handleWebhook($payload, $signature);
                $eventHandled = true;
                
                // Process the event based on type
                switch ($event['type']) {
                    case 'payment_intent.succeeded':
                        // Handle successful payment
                        error_log("Stripe: Payment succeeded for org {$orgId}: " . $event['data']['object']['id']);
                        break;
                        
                    case 'customer.created':
                        // Handle new customer
                        error_log("Stripe: Customer created for org {$orgId}: " . $event['data']['object']['id']);
                        break;
                        
                    case 'invoice.payment_succeeded':
                        // Handle successful subscription payment
                        error_log("Stripe: Invoice paid for org {$orgId}: " . $event['data']['object']['id']);
                        break;
                        
                    default:
                        error_log("Stripe: Unhandled event type for org {$orgId}: " . $event['type']);
                }
                
                break; // Event handled successfully
            }
        } catch (Exception $e) {
            // Continue to next organization if signature doesn't match
            continue;
        }
    }
    
    if (!$eventHandled) {
        http_response_code(400);
        echo json_encode(['error' => 'Webhook signature verification failed']);
        exit;
    }
    
    http_response_code(200);
    echo json_encode(['status' => 'success']);
    
} catch (Exception $e) {
    error_log("Stripe webhook error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}