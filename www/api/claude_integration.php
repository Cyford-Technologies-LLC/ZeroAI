<?php
class ClaudeIntegration {
    private $apiKey;
    private $baseUrl = 'https://api.anthropic.com/v1/messages';
    
    public function __construct($apiKey = null) {
        $this->apiKey = $apiKey ?: getenv('ANTHROPIC_API_KEY');
    }
    
    public function chatWithClaude($message, $systemPrompt = null, $model = null, $conversationHistory = []) {
        if (!$this->apiKey) {
            throw new Exception('Anthropic API key not configured');
        }
        
        $headers = [
            'Content-Type: application/json',
            'x-api-key: ' . $this->apiKey,
            'anthropic-version: 2023-06-01'
        ];
        
        // Build messages array with conversation history
        $messages = [];
        
        // Process conversation history with improved validation
        if (is_array($conversationHistory) && !empty($conversationHistory)) {
            // Limit to last 20 messages to maintain more context
            $recentHistory = array_slice($conversationHistory, -20);
            
            foreach ($recentHistory as $historyItem) {
                // Validate history item structure
                if (!is_array($historyItem) || !isset($historyItem['sender']) || !isset($historyItem['message'])) {
                    continue;
                }
                
                $sender = trim($historyItem['sender']);
                $messageContent = trim($historyItem['message']);
                
                // Skip empty messages
                if (empty($messageContent)) {
                    continue;
                }
                
                // Map sender names to Claude API roles
                if ($this->isClaudeMessage($sender)) {
                    $messages[] = [
                        'role' => 'assistant',
                        'content' => $messageContent
                    ];
                } elseif ($this->isUserMessage($sender)) {
                    $messages[] = [
                        'role' => 'user', 
                        'content' => $messageContent
                    ];
                }
                // Skip system messages as they don't belong in the messages array
            }
        }
        
        // Add current message
        $messages[] = [
            'role' => 'user',
            'content' => $message
        ];
        
        // Debug: Log what we're sending to Claude
        file_put_contents('/app/logs/claude_debug.log', date('Y-m-d H:i:s') . " - Input History: " . json_encode($conversationHistory, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
        file_put_contents('/app/logs/claude_debug.log', date('Y-m-d H:i:s') . " - Processed Messages: " . json_encode($messages, JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);
        
        $data = [
            'model' => $model ?: 'claude-3-5-sonnet-20241022',
            'max_tokens' => 4000,
            'messages' => $messages
        ];
        
        if ($systemPrompt) {
            $data['system'] = $systemPrompt;
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 minutes
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60); // 1 minute
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        // Retry on timeout
        if ($curlError && strpos($curlError, 'timeout') !== false) {
            sleep(2);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->baseUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 300);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        }
        
        if ($httpCode !== 200) {
            $errorMsg = 'Claude API error (HTTP ' . $httpCode . ')';
            if ($httpCode === 0) {
                $errorMsg = 'Claude API unavailable - connection failed';
            } elseif ($httpCode >= 500) {
                $errorMsg = 'Claude API server error - service may be down';
            } elseif ($httpCode === 429) {
                $errorMsg = 'Claude API rate limit exceeded';
            }
            throw new Exception($errorMsg . ': ' . $response);
        }
        
        $result = json_decode($response, true);
        
        if (!$result || !isset($result['content'][0]['text'])) {
            throw new Exception('Invalid response from Claude API');
        }
        
        return [
            'message' => $result['content'][0]['text'],
            'usage' => $result['usage'] ?? [],
            'model' => $result['model'] ?? ($model ?: 'claude-3-5-sonnet-20241022')
        ];
    }
    
    public function helpWithZeroAI($userQuery, $context = []) {
        $systemPrompt = "You are Claude, an AI assistant helping with ZeroAI - a zero-cost AI workforce platform. 

ZeroAI Context:
- ZeroAI runs entirely on user's hardware with local Ollama models
- It has internal crews for development, devops, research, documentation
- Users can create custom agents and crews
- The system supports both local and cloud AI providers
- Current user is managing their AI workforce through the admin portal

Your role:
- Help optimize ZeroAI configurations and workflows
- Suggest improvements for agent crews and tasks
- Assist with troubleshooting and development
- Provide coding help for Python/PHP integration
- Recommend best practices for AI workforce management

Be concise, practical, and focus on actionable advice.";
        
        if (!empty($context)) {
            $systemPrompt .= "\n\nCurrent Context:\n" . json_encode($context, JSON_PRETTY_PRINT);
        }
        
        return $this->chatWithClaude($userQuery, $systemPrompt);
    }
    
    public function analyzeAgentPerformance($agentData) {
        $prompt = "Analyze this ZeroAI agent's performance data and provide optimization recommendations:

Agent Data:
" . json_encode($agentData, JSON_PRETTY_PRINT) . "

Please provide:
1. Performance assessment
2. Specific optimization recommendations
3. Suggested role/goal improvements
4. Tool recommendations
5. Training suggestions";

        return $this->chatWithClaude($prompt);
    }
    
    public function generateAgentCode($agentSpec) {
        $prompt = "Generate Python code for a ZeroAI CrewAI agent based on this specification:

" . json_encode($agentSpec, JSON_PRETTY_PRINT) . "

Please provide:
1. Complete Python agent class
2. Required imports
3. Tool integrations
4. Error handling
5. Documentation

Follow ZeroAI patterns and CrewAI best practices.";

        return $this->chatWithClaude($prompt);
    }
    
    /**
     * Check if sender represents Claude/assistant
     */
    private function isClaudeMessage($sender) {
        $claudeSenders = ['Claude', 'claude', 'Assistant', 'assistant', 'Claude (Auto)', 'AI'];
        return in_array($sender, $claudeSenders, true);
    }
    
    /**
     * Check if sender represents user
     */
    private function isUserMessage($sender) {
        $userSenders = ['You', 'you', 'User', 'user', 'Human', 'human'];
        return in_array($sender, $userSenders, true);
    }
    
    public function testConnection() {
        try {
            $response = $this->chatWithClaude("Hello, please respond with 'Claude is connected to ZeroAI' to test the integration.");
            return [
                'success' => true,
                'message' => $response['message'],
                'model' => $response['model']
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
?>