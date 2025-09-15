<?php
namespace Core;

abstract class ChatContext {
    protected $system;
    
    public function __construct() {
        $this->system = System::getInstance();
    }
    
    abstract public function getSystemPrompt(string $mode): string;
    abstract public function processCommands(string $message, string $mode): string;
    abstract public function generateResponse(string $message, string $systemPrompt, string $mode): array;
    
    protected function executeCommand(string $command, array $params, string $user): array {
        return $this->system->executeCommand($command, $params, $user);
    }
    
    protected function hasPermission(string $user, string $command, string $mode): bool {
        return $this->system->getSecurity()->hasPermission($user, $command, $mode);
    }
}
