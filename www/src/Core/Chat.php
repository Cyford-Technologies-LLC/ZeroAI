<?php
namespace Core;

class Chat {
    private $system;
    private $contexts = [];
    
    public function __construct() {
        $this->system = System::getInstance();
        $this->initializeContexts();
    }
    
    private function initializeContexts(): void {
        $this->contexts = [
            'claude' => new ClaudeContext(),
            'agent' => new AgentContext(),
            'crew' => new CrewContext(),
            'system' => new SystemContext(),
            'user' => new UserContext()
        ];
    }
    
    public function processMessage(string $message, string $aiType, string $mode = 'hybrid'): array {
        try {
            $context = $this->contexts[$aiType] ?? $this->contexts['system'];
            
            // Get context-specific system prompt
            $systemPrompt = $context->getSystemPrompt($mode);
            
            // Process commands in message
            $processedMessage = $context->processCommands($message, $mode);
            
            // Get AI-specific response
            $response = $context->generateResponse($processedMessage, $systemPrompt, $mode);
            
            $this->system->getLogger()->info("Chat processed", [
                'ai_type' => $aiType,
                'mode' => $mode,
                'message_length' => strlen($message)
            ]);
            
            return [
                'success' => true,
                'response' => $response['message'],
                'tokens' => $response['tokens'] ?? 0,
                'model' => $response['model'] ?? $aiType
            ];
            
        } catch (\Exception $e) {
            $this->system->getLogger()->error("Chat processing failed", [
                'ai_type' => $aiType,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    public function getAvailableContexts(): array {
        return array_keys($this->contexts);
    }
    
    public function addContext(string $name, ChatContext $context): void {
        $this->contexts[$name] = $context;
    }
}