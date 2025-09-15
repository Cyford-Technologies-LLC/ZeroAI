<?php

namespace ZeroAI\Providers\AI\OpenAI;

use ZeroAI\Providers\AI\BaseAIProvider;

class OpenAIIntegration extends BaseAIProvider
{
    private string $baseUrl = 'https://api.openai.com/v1';
    
    public function __construct(string $apiKey, array $config = [])
    {
        parent::__construct('OpenAI', $apiKey, $config);
        $this->models = [
            'gpt-4o' => ['input' => 2.50, 'output' => 10.00],
            'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
            'gpt-4-turbo' => ['input' => 10.00, 'output' => 30.00],
            'gpt-3.5-turbo' => ['input' => 0.50, 'output' => 1.50]
        ];
    }
    
    public function chat(string $message, array $options = []): array
    {
        $model = $options['model'] ?? 'gpt-4o-mini';
        $systemPrompt = $options['system'] ?? 'You are a helpful assistant.';
        $history = $options['history'] ?? [];
        
        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        
        foreach ($history as $msg) {
            if (isset($msg['role']) && isset($msg['content'])) {
                $messages[] = $msg;
            }
        }
        
        $messages[] = ['role' => 'user', 'content' => $message];
        
        $data = [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $options['max_tokens'] ?? 4096,
            'temperature' => $options['temperature'] ?? 0.7
        ];
        
        $response = $this->makeRequest($this->baseUrl . '/chat/completions', $data, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ]);
        
        $usage = [
            'model' => $model,
            'input_tokens' => $response['usage']['prompt_tokens'] ?? 0,
            'output_tokens' => $response['usage']['completion_tokens'] ?? 0
        ];
        
        $this->logUsage($usage);
        
        return [
            'message' => $response['choices'][0]['message']['content'] ?? '',
            'usage' => $usage,
            'model' => $model
        ];
    }
    
    public function getModels(): array
    {
        return array_keys($this->models);
    }
    
    public function validateApiKey(): bool
    {
        try {
            $this->makeRequest($this->baseUrl . '/models', [], [
                'Authorization: Bearer ' . $this->apiKey
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    public function calculateCost(array $usage): float
    {
        $model = $usage['model'] ?? 'gpt-4o-mini';
        $inputTokens = $usage['input_tokens'] ?? 0;
        $outputTokens = $usage['output_tokens'] ?? 0;
        
        $modelPricing = $this->models[$model] ?? ['input' => 0.15, 'output' => 0.60];
        
        $inputCost = ($inputTokens / 1000000) * $modelPricing['input'];
        $outputCost = ($outputTokens / 1000000) * $modelPricing['output'];
        
        return $inputCost + $outputCost;
    }
}


