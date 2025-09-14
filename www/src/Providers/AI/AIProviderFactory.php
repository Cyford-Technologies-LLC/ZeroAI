<?php

namespace ZeroAI\Providers\AI;

use ZeroAI\Providers\AI\Claude\ClaudeIntegration;

class AIProviderFactory
{
    private static array $providers = [];
    
    public static function create(string $provider, string $apiKey, array $config = []): BaseAIProvider
    {
        $cacheKey = $provider . '_' . md5($apiKey);
        
        if (isset(self::$providers[$cacheKey])) {
            return self::$providers[$cacheKey];
        }
        
        $instance = match(strtolower($provider)) {
            'claude' => new ClaudeIntegration($apiKey, $config),
            'openai' => new OpenAIIntegration($apiKey, $config),
            'gemini' => new GeminiIntegration($apiKey, $config),
            'cohere' => new CohereIntegration($apiKey, $config),
            default => throw new \InvalidArgumentException("Unsupported AI provider: $provider")
        };
        
        self::$providers[$cacheKey] = $instance;
        return $instance;
    }
    
    public static function getAvailableProviders(): array
    {
        return ['claude', 'openai', 'gemini', 'cohere'];
    }
    
    public static function clearCache(): void
    {
        self::$providers = [];
    }
}