<?php

namespace ZeroAI\Providers\AI;

abstract class BaseAIProvider
{
    protected string $name;
    protected string $apiKey;
    protected array $config;
    protected array $models = [];
    
    public function __construct(string $name, string $apiKey, array $config = [])
    {
        $this->name = $name;
        $this->apiKey = $apiKey;
        $this->config = $config;
    }
    
    abstract public function chat(string $message, array $options = []): array;
    abstract public function getModels(): array;
    abstract public function validateApiKey(): bool;
    abstract public function calculateCost(array $usage): float;
    
    public function getName(): string
    {
        return $this->name;
    }
    
    public function getConfig(): array
    {
        return $this->config;
    }
    
    protected function makeRequest(string $url, array $data, array $headers = []): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => json_encode($data),
                'timeout' => $this->config['timeout'] ?? 30
            ]
        ]);
        
        $response = file_get_contents($url, false, $context);
        
        if ($response === false) {
            throw new \Exception("API request failed");
        }
        
        return json_decode($response, true);
    }
    
    protected function logUsage(array $usage): void
    {
        $db = new \SQLite3('/app/data/zeroai.db');
        $stmt = $db->prepare('
            INSERT INTO ai_usage (provider, model, input_tokens, output_tokens, cost, created_at)
            VALUES (?, ?, ?, ?, ?, datetime("now"))
        ');
        $stmt->bindValue(1, $this->name);
        $stmt->bindValue(2, $usage['model'] ?? 'unknown');
        $stmt->bindValue(3, $usage['input_tokens'] ?? 0);
        $stmt->bindValue(4, $usage['output_tokens'] ?? 0);
        $stmt->bindValue(5, $usage['cost'] ?? 0);
        $stmt->execute();
    }
}