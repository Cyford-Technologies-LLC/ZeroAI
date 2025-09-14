<?php
namespace ZeroAI\Core;

class System {
    private static $instance = null;
    private $logger;
    private $security;
    private $database;
    private $config = [];
    
    private function __construct() {
        $this->loadEnvironment();
        $this->logger = \ZeroAI\Core\Logger::getInstance();
        $this->security = new \ZeroAI\Core\Security();
        $this->database = new \ZeroAI\Core\DatabaseManager();
        $this->logger->info('System initialized');
    }
    
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function loadEnvironment(): void {
        try {
            if (file_exists('/app/.env')) {
                $lines = file('/app/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (strpos($line, '=') !== false && !str_starts_with($line, '#')) {
                        list($key, $value) = explode('=', $line, 2);
                        $_ENV[trim($key)] = trim($value);
                        putenv(trim($key) . '=' . trim($value));
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("Environment loading failed: " . $e->getMessage());
        }
    }
    
    public function executeCommand(string $command, array $params = [], string $user = 'system'): array {
        try {
            if (!$this->security->hasPermission($user, $command)) {
                throw new \Exception("Permission denied for command: $command");
            }
            
            $this->logger->info("Executing: $command", ['user' => $user]);
            $result = $this->processCommand($command, $params);
            $this->logger->info("Command success: $command", ['user' => $user]);
            
            return ['success' => true, 'data' => $result];
        } catch (\Exception $e) {
            $this->logger->error("Command failed: $command", ['error' => $e->getMessage(), 'user' => $user]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    private function processCommand(string $command, array $params): mixed {
        return match($command) {
            'file_read' => $this->readFile($params['path'] ?? ''),
            'file_write' => $this->writeFile($params['path'] ?? '', $params['content'] ?? ''),
            'file_list' => $this->listDirectory($params['path'] ?? ''),
            'docker_exec' => $this->dockerExec($params['container'] ?? '', $params['cmd'] ?? ''),
            'system_status' => $this->getSystemStatus(),
            default => throw new \Exception("Unknown command: $command")
        };
    }
    
    private function readFile(string $path): string {
        $fullPath = $this->resolvePath($path);
        if (!file_exists($fullPath)) throw new \Exception("File not found: $path");
        return file_get_contents($fullPath);
    }
    
    private function writeFile(string $path, string $content): bool {
        $fullPath = $this->resolvePath($path);
        $dir = dirname($fullPath);
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        return file_put_contents($fullPath, $content) !== false;
    }
    
    private function listDirectory(string $path): array {
        $fullPath = $this->resolvePath($path);
        if (!is_dir($fullPath)) throw new \Exception("Directory not found: $path");
        return array_diff(scandir($fullPath), ['.', '..']);
    }
    
    private function dockerExec(string $container, string $cmd): string {
        $output = shell_exec("docker exec $container $cmd 2>&1");
        return $output ?: "Command executed";
    }
    
    private function getSystemStatus(): array {
        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'memory_usage' => memory_get_usage(true),
            'disk_free' => disk_free_space('/app')
        ];
    }
    
    private function resolvePath(string $path): string {
        if (str_starts_with($path, '/app/')) return $path;
        return '/app/' . ltrim($path, '/');
    }
    
    public function getConfig(?string $key = null): mixed {
        return $key ? ($this->config[$key] ?? null) : $this->config;
    }
    
    public function setConfig(string $key, mixed $value): void {
        $this->config[$key] = $value;
    }
    
    public function getDatabase(): \ZeroAI\Core\DatabaseManager { return $this->database; }
    public function getLogger(): \ZeroAI\Core\Logger { return $this->logger; }
    public function getSecurity(): \ZeroAI\Core\Security { return $this->security; }
}