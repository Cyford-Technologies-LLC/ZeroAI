<?php
namespace Core;

class AgentContext extends ChatContext {
    public function getSystemPrompt(string $mode): string {
        return "You are a ZeroAI Agent. You work within crews to accomplish tasks.\n\n" .
               "Available commands: @file, @list, @agents, @crews, @logs\n" .
               "Mode: $mode";
    }
    
    public function processCommands(string $message, string $mode): string {
        // Basic command processing for agents
        $commandOutputs = '';
        
        if (str_contains($message, '@agents')) {
            $db = $this->system->getDatabase();
            $result = $db->query("SELECT name, role FROM agents WHERE status = 'active'");
            $commandOutputs .= "\n\nActive Agents:\n";
            foreach ($result[0]['data'] ?? [] as $agent) {
                $commandOutputs .= "- {$agent['name']}: {$agent['role']}\n";
            }
        }
        
        return $message . $commandOutputs;
    }
    
    public function generateResponse(string $message, string $systemPrompt, string $mode): array {
        // Simple local response for agents
        return [
            'message' => "Agent processed: " . substr($message, 0, 100) . "...",
            'tokens' => 0,
            'model' => 'local-agent'
        ];
    }
}
