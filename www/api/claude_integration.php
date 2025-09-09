<?php
class ClaudeIntegration {
    private $apiKey;
    private $baseUrl = 'https://api.anthropic.com/v1/messages';
    
    public function __construct($apiKey = null) {
        $this->apiKey = $apiKey ?: getenv('ANTHROPIC_API_KEY');
    }
    
    public function chatWithClaude($message, $systemPrompt = null) {
        if (!$this->apiKey) {
            throw new Exception('Anthropic API key not configured');
        }
        
        $headers = [
            'Content-Type: application/json',
            'x-api-key: ' . $this->apiKey,
            'anthropic-version: 2023-06-01'
        ];
        
        $data = [
            'model' => 'claude-3-5-sonnet-latest',
            'max_tokens' => 4000,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $message
                ]
            ]
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception('Claude API error: ' . $response);
        }
        
        $result = json_decode($response, true);
        
        if (!$result || !isset($result['content'][0]['text'])) {
            throw new Exception('Invalid response from Claude API');
        }
        
        return [
            'message' => $result['content'][0]['text'],
            'usage' => $result['usage'] ?? [],
            'model' => $result['model'] ?? 'claude-3-5-sonnet-latest'
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