<?php
namespace ZeroAI\Core;

class Security {
    private $permissions = [
        'admin' => ['*'],
        'user' => ['file_read', 'file_list', 'system_status'],
        'claude' => ['file_read', 'file_write', 'file_list', 'docker_exec', 'system_status', 'cmd_file', 'cmd_list', 'cmd_exec', 'cmd_agents', 'cmd_crews', 'cmd_logs', 'cmd_memory'],
        'agent' => ['file_read', 'file_list', 'system_status'],
        'system' => ['*']
    ];
    
    private $modes = [
        'autonomous' => ['file_read', 'file_write', 'file_list', 'docker_exec', 'cmd_file', 'cmd_list', 'cmd_exec', 'cmd_agents', 'cmd_crews', 'cmd_logs', 'cmd_memory'],
        'hybrid' => ['file_read', 'file_list', 'docker_exec', 'cmd_file', 'cmd_list', 'cmd_exec', 'cmd_agents', 'cmd_crews', 'cmd_logs'],
        'chat' => ['file_read', 'file_list', 'cmd_file', 'cmd_list', 'cmd_agents', 'cmd_crews', 'cmd_logs']
    ];
    
    public function hasPermission(string $user, string $command, string $mode = null): bool {
        try {
            // Check user permissions
            $userPerms = $this->permissions[$user] ?? [];
            if (in_array('*', $userPerms) || in_array($command, $userPerms)) {
                // If mode is specified, check mode permissions too
                if ($mode && isset($this->modes[$mode])) {
                    return in_array($command, $this->modes[$mode]);
                }
                return true;
            }
            
            // Check mode permissions if user not found
            if ($mode && isset($this->modes[$mode])) {
                return in_array($command, $this->modes[$mode]);
            }
            
            return false;
        } catch (\Exception $e) {
            \ZeroAI\Core\Logger::getInstance()->error('Permission check failed', [
                'user' => $user,
                'command' => $command,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    public function checkAccess(string $user, string $resource, string $action = 'read'): bool {
        try {
            // File access control
            if (str_starts_with($resource, '/app/knowledge/internal_crew/agent_learning/self/claude')) {
                return $user === 'claude' || $user === 'admin' || $user === 'system';
            }
            
            // System files
            if (str_starts_with($resource, '/app/src') || str_starts_with($resource, '/app/config')) {
                return in_array($user, ['admin', 'system']) || ($action === 'read' && $user === 'claude');
            }
            
            // Data directory
            if (str_starts_with($resource, '/app/data')) {
                return in_array($user, ['admin', 'system', 'claude']);
            }
            
            return true;
        } catch (\Exception $e) {
            \ZeroAI\Core\Logger::getInstance()->error('Access check failed', [
                'user' => $user,
                'resource' => $resource,
                'action' => $action,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    public function getPermissionError(string $command, string $user, string $mode = null): string {
        $modeText = $mode ? " in $mode mode" : "";
        return "[PERMISSION DENIED] Command '$command' not allowed for user '$user'$modeText";
    }
    
    public function addPermission(string $user, string $command): void {
        if (!isset($this->permissions[$user])) {
            $this->permissions[$user] = [];
        }
        if (!in_array($command, $this->permissions[$user])) {
            $this->permissions[$user][] = $command;
        }
    }
    
    public function removePermission(string $user, string $command): void {
        if (isset($this->permissions[$user])) {
            $this->permissions[$user] = array_filter(
                $this->permissions[$user], 
                fn($perm) => $perm !== $command
            );
        }
    }
    
    public function getUserPermissions(string $user): array {
        return $this->permissions[$user] ?? [];
    }
    
    public function getModePermissions(string $mode): array {
        return $this->modes[$mode] ?? [];
    }
}