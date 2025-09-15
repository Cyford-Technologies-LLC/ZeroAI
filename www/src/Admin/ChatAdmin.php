<?php

namespace ZeroAI\Admin;

require_once __DIR__ . '/../AI/AIManager.php';

use ZeroAI\AI\AIManager;

class ChatAdmin extends BaseAdmin {
    private $aiManager;
    
    protected function handleRequest() {
        $this->aiManager = new AIManager();
        
        if ($_POST['action'] ?? '' === 'send_message') {
            $this->handleChat();
        }
    }
    
    private function handleChat() {
        $message = $_POST['message'] ?? '';
        $provider = $_POST['provider'] ?? 'claude';
        
        if ($message) {
            try {
                $response = $this->aiManager->chat($message, $provider);
                $this->data['response'] = $response;
            } catch (\Exception $e) {
                $this->data['error'] = $e->getMessage();
            }
        }
    }
    
    protected function renderContent() {
        $providers = $this->aiManager->getAvailableProviders();
        ?>
        <h1>AI Chat Interface</h1>
        
        <div class="card">
            <h3>Chat with AI</h3>
            
            <form method="POST">
                <input type="hidden" name="action" value="send_message">
                
                <label>Provider:</label>
                <select name="provider">
                    <?php foreach ($providers['cloud'] as $provider): ?>
                        <option value="<?= $provider ?>"><?= ucfirst($provider) ?></option>
                    <?php endforeach; ?>
                    <?php foreach ($providers['local'] as $provider): ?>
                        <option value="<?= $provider ?>">Local: <?= ucfirst($provider) ?></option>
                    <?php endforeach; ?>
                    <option value="smart">Smart Route</option>
                </select>
                
                <label>Message:</label>
                <textarea name="message" rows="4" placeholder="Enter your message..."></textarea>
                
                <button type="submit">Send Message</button>
            </form>
            
            <?php if (isset($this->data['response'])): ?>
                <div class="response">
                    <h4>Response:</h4>
                    <p><?= nl2br(htmlspecialchars($this->data['response']['message'])) ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (isset($this->data['error'])): ?>
                <div class="error">Error: <?= htmlspecialchars($this->data['error']) ?></div>
            <?php endif; ?>
        </div>
        <?php
    }
}
