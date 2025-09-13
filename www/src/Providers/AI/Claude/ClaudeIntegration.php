<?php

namespace ZeroAI\Providers\AI\Claude;

class ClaudeIntegration {
    private $apiKey;
    private $baseUrl = 'https://api.anthropic.com/v1/messages';
    private $toolSystem;
    
    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
        $this->toolSystem = new ClaudeToolSystem();
    }
    
    public function chatWithClaude($message, $systemPrompt, $model, $conversationHistory = []) {
        // Check if Claude needs to use tools before generating response
        $toolResults = $this->processTools($message);
        if ($toolResults) {
            $message .= "\n\nTool Results:\n" . $toolResults;
        }
        
        $messages = [];
        
        // Use pre-converted history from ClaudeProvider
        if (is_array($conversationHistory) && !empty($conversationHistory)) {
            foreach ($conversationHistory as $msg) {
                if (isset($msg['role']) && isset($msg['content'])) {
                    $messages[] = $msg;
                }
            }
        }
        
        $messages[] = [
            'role' => 'user',
            'content' => $message
        ];
        
        // Ensure system prompt is not empty
        if (empty($systemPrompt)) {
            $systemPrompt = 'You are Claude, integrated into ZeroAI.';
        }
        
        $data = [
            'model' => $model,
            'max_tokens' => 4096,
            'system' => [
                [
                    'type' => 'text',
                    'text' => $systemPrompt
                ]
            ],
            'messages' => $messages
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01'
            ],
            CURLOPT_TIMEOUT => 300,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new \Exception("API request failed with status $httpCode: $response");
        }
        
        $decoded = json_decode($response, true);
        
        if (!$decoded || !isset($decoded['content'][0]['text'])) {
            throw new \Exception('Invalid API response format');
        }
        
        return [
            'message' => $decoded['content'][0]['text'],
            'usage' => $decoded['usage'] ?? [],
            'model' => $decoded['model'] ?? $model
        ];
    }
    
    private function processTools($message) {
        $results = '';
        $mode = $GLOBALS['claudeMode'] ?? 'hybrid';
        // Convert autonomous to agentic for tool permissions
        if ($mode === 'autonomous') $mode = 'agentic';
        
        // Process @exec commands
        if (preg_match_all('/@exec\s+([^\s]+)\s+(.+)/m', $message, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $result = $this->toolSystem->execute('exec', [$match[1], $match[2]], $mode);
                if (isset($result['success'])) {
                    $results .= $result['formatted'] . "\n\n";
                } else {
                    $results .= "❌ " . $result['error'] . "\n\n";
                }
            }
        }
        
        // Process @file commands
        if (preg_match_all('/@file\s+([^\s]+)/m', $message, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $result = $this->toolSystem->execute('file', [$match[1]], $mode);
                if (isset($result['success'])) {
                    $results .= $result['formatted'] . "\n\n";
                } else {
                    $results .= "❌ " . $result['error'] . "\n\n";
                }
            }
        }
        
        // Process @list commands
        if (preg_match_all('/@list\s+([^\s]+)/m', $message, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $result = $this->toolSystem->execute('list', [$match[1]], $mode);
                if (isset($result['success'])) {
                    $results .= $result['formatted'] . "\n\n";
                } else {
                    $results .= "❌ " . $result['error'] . "\n\n";
                }
            }
        }
        
        // Process @agents commands
        if (preg_match('/@agents/', $message)) {
            $result = $this->toolSystem->execute('agents', [], $mode);
            if (isset($result['success'])) {
                $results .= $result['formatted'] . "\n\n";
            } else {
                $results .= "❌ " . $result['error'] . "\n\n";
            }
        }
        
        // Process @docker commands
        if (preg_match_all('/@docker\s+(.+)/m', $message, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $result = $this->toolSystem->execute('docker', [$match[1]], $mode);
                if (isset($result['success'])) {
                    $results .= $result['formatted'] . "\n\n";
                } else {
                    $results .= "❌ " . $result['error'] . "\n\n";
                }
            }
        }
        
        // Process @ps commands
        if (preg_match('/@ps/', $message)) {
            $result = $this->toolSystem->execute('ps', [], $mode);
            if (isset($result['success'])) {
                $results .= $result['formatted'] . "\n\n";
            } else {
                $results .= "❌ " . $result['error'] . "\n\n";
            }
        }
        
        return $results;
    }
}
?>