<?php
function processFileCommands(&$message) {
    // @file command - Unrestricted read access
    if (preg_match('/\@file\s+(.+)/', $message, $matches)) {
        $filePath = trim($matches[1]);
        @file_put_contents('/app/logs/claude_commands.log', date('Y-m-d H:i:s') . " @file: $filePath\n", FILE_APPEND);
        
        // Try multiple path variations for unrestricted access
        $paths = [
            $filePath,
            '/app/' . ltrim($filePath, '/'),
            '/app/' . $filePath
        ];
        
        $found = false;
        foreach ($paths as $tryPath) {
            if (file_exists($tryPath) && is_readable($tryPath)) {
                $fileContent = file_get_contents($tryPath);
                $message .= "\n\nFile content of " . $filePath . ":\n" . $fileContent;
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $message .= "\n\nFile not found or not readable: " . $filePath;
        }
    }

    // @read command (alias for @file)
    if (preg_match('/\@read\s+(.+)/', $message, $matches)) {
        $filePath = trim($matches[1]);
        @file_put_contents('/app/logs/claude_commands.log', date('Y-m-d H:i:s') . " @read: $filePath\n", FILE_APPEND);
        
        $paths = [
            $filePath,
            '/app/' . $filePath,
            '/app/' . ltrim($filePath, '/')
        ];
        
        $found = false;
        foreach ($paths as $tryPath) {
            if (file_exists($tryPath)) {
                $fileContent = file_get_contents($tryPath);
                $message .= "\n\nFile content of " . $filePath . ":\n" . $fileContent;
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $message .= "\n\nFile not found: " . $filePath;
        }
    }

    // @list command
    if (preg_match('/\@list\s+(.+)/', $message, $matches)) {
        $dirPath = trim($matches[1]);
        @file_put_contents('/app/logs/claude_commands.log', date('Y-m-d H:i:s') . " @list: $dirPath\n", FILE_APPEND);
        
        // Smart path handling - don't double-prepend /app/
        if (strpos($dirPath, '/app/') === 0) {
            $fullDirPath = $dirPath; // Already has /app/
        } else {
            $cleanPath = ltrim($dirPath, '/');
            $fullDirPath = '/app/' . $cleanPath;
        }
        
        if (is_dir($fullDirPath)) {
            $files = scandir($fullDirPath);
            $listing = "Directory listing for " . $dirPath . ":\n" . implode("\n", array_filter($files, function($f) { return $f !== '.' && $f !== '..'; }));
            $message .= "\n\n" . $listing;
        } else {
            $message .= "\n\nDirectory not found: " . $dirPath . " (tried: $fullDirPath)";
        }
    }

    // @create command - Check permissions first
    if (preg_match('/\@create\s+([^\s\n]+)(?:\s+```([\s\S]*?)```)?/', $message, $matches)) {
        // Get current mode from global or default to hybrid
        global $claudeMode;
        $currentMode = $claudeMode ?? 'hybrid';
        
        // Check permission
        require_once __DIR__ . '/check_command_permission.php';
        if (!checkCommandPermission('create', $currentMode)) {
            $message .= "\n\n" . getPermissionError('create', $currentMode);
        } else {
        $filePath = trim($matches[1]);
        $fileContent = isset($matches[2]) ? trim($matches[2]) : "";
        
        // Smart path handling
        if (strpos($filePath, '/app/') === 0) {
            $fullPath = $filePath;
        } else {
            $cleanPath = ltrim($filePath, '/');
            $fullPath = '/app/' . $cleanPath;
        }
        
        // Check if writing to Claude's learning directory
        $claudeDir = '/app/knowledge/internal_crew/agent_learning/self/claude';
        $isClaudeDir = strpos($fullPath, $claudeDir) === 0;
        
        if ($isClaudeDir) {
            // Unrestricted write access to Claude's directory
            $dir = dirname($fullPath);
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $result = file_put_contents($fullPath, $fileContent);
            if ($result !== false) {
                $message .= "\n\n[SUCCESS] File created in Claude's directory: " . $filePath . " (" . $result . " bytes)";
            } else {
                $message .= "\n\n[ERROR] Failed to create in Claude's directory: " . $filePath;
            }
        } else {
            // Read-only mode for other directories
            $message .= "\n\n[RESTRICTED] Write access restricted. Claude can only write to: knowledge/internal_crew/agent_learning/self/claude/";
        }
        }
    }

    // @edit command - Check permissions first
    if (preg_match('/\@edit\s+([^\s\n]+)(?:\s+```([\s\S]*?)```)?/', $message, $matches)) {
        global $claudeMode;
        $currentMode = $claudeMode ?? 'hybrid';
        
        require_once __DIR__ . '/check_command_permission.php';
        if (!checkCommandPermission('edit', $currentMode)) {
            $message .= "\n\n" . getPermissionError('edit', $currentMode);
        } else {
        $filePath = trim($matches[1]);
        $newContent = isset($matches[2]) ? trim($matches[2]) : "";
        
        if (strpos($filePath, '/app/') === 0) {
            $fullPath = $filePath;
        } else {
            $cleanPath = ltrim($filePath, '/');
            $fullPath = '/app/' . $cleanPath;
        }
        
        // Check if writing to Claude's learning directory
        $claudeDir = '/app/knowledge/internal_crew/agent_learning/self/claude';
        $isClaudeDir = strpos($fullPath, $claudeDir) === 0;
        
        if ($isClaudeDir) {
            if (file_exists($fullPath)) {
                $result = file_put_contents($fullPath, $newContent);
                if ($result !== false) {
                    $message .= "\n\n[SUCCESS] File updated in Claude's directory: " . $filePath . " (" . $result . " bytes)";
                } else {
                    $message .= "\n\n[ERROR] Failed to update in Claude's directory: " . $filePath;
                }
            } else {
                $message .= "\n\nFile not found in Claude's directory: " . $filePath;
            }
        } else {
            $message .= "\n\n[RESTRICTED] Write access restricted. Claude can only edit files in: knowledge/internal_crew/agent_learning/self/claude/";
        }
        }
    }

    // @append command - Unrestricted write to Claude's directory only
    if (preg_match('/\@append\s+([^\s\n]+)(?:\s+```([\s\S]*?)```)?/', $message, $matches)) {
        $filePath = trim($matches[1]);
        $appendContent = isset($matches[2]) ? trim($matches[2]) : "";
        
        if (strpos($filePath, '/app/') === 0) {
            $fullPath = $filePath;
        } else {
            $cleanPath = ltrim($filePath, '/');
            $fullPath = '/app/' . $cleanPath;
        }
        
        // Check if writing to Claude's learning directory
        $claudeDir = '/app/knowledge/internal_crew/agent_learning/self/claude';
        $isClaudeDir = strpos($fullPath, $claudeDir) === 0;
        
        if ($isClaudeDir) {
            $result = file_put_contents($fullPath, "\n" . $appendContent, FILE_APPEND);
            if ($result !== false) {
                $message .= "\n\n[SUCCESS] Content appended to Claude's file: " . $filePath . " (" . $result . " bytes)";
            } else {
                $message .= "\n\n[ERROR] Failed to append to Claude's file: " . $filePath;
            }
        } else {
            $message .= "\n\n[RESTRICTED] Write access restricted. Claude can only append to files in: knowledge/internal_crew/agent_learning/self/claude/";
        }
    }

    // @delete command - Unrestricted delete in Claude's directory only
    if (preg_match('/\@delete\s+(.+)/', $message, $matches)) {
        $filePath = trim($matches[1]);
        
        if (strpos($filePath, '/app/') === 0) {
            $fullPath = $filePath;
        } else {
            $cleanPath = ltrim($filePath, '/');
            $fullPath = '/app/' . $cleanPath;
        }
        
        // Check if deleting from Claude's learning directory
        $claudeDir = '/app/knowledge/internal_crew/agent_learning/self/claude';
        $isClaudeDir = strpos($fullPath, $claudeDir) === 0;
        
        if ($isClaudeDir) {
            if (file_exists($fullPath)) {
                if (unlink($fullPath)) {
                    $message .= "\n\n[SUCCESS] File deleted from Claude's directory: " . $filePath;
                } else {
                    $message .= "\n\n[ERROR] Failed to delete from Claude's directory: " . $filePath;
                }
            } else {
                $message .= "\n\nFile not found in Claude's directory: " . $filePath;
            }
        } else {
            $message .= "\n\n[RESTRICTED] Delete access restricted. Claude can only delete files in: knowledge/internal_crew/agent_learning/self/claude/";
        }
    }
}
?>