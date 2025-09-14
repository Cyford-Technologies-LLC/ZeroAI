<?php
class ClaudeFileTools {
    
    public static function getFileContent($filePath) {
        $safePath = realpath($filePath);
        if (!$safePath || !file_exists($safePath)) {
            return "File not found: $filePath";
        }
        
        // Security check - only allow files in /app directory
        if (!str_starts_with($safePath, '/app/')) {
            return "Access denied: File outside allowed directory";
        }
        
        $content = file_get_contents($safePath);
        $size = strlen($content);
        
        return "=== $filePath ===\nSize: $size bytes\n\n$content";
    }
    
    public static function listDirectory($dirPath) {
        $safePath = realpath($dirPath);
        if (!$safePath || !is_dir($safePath)) {
            return "Directory not found: $dirPath";
        }
        
        if (!str_starts_with($safePath, '/app/')) {
            return "Access denied: Directory outside allowed path";
        }
        
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($safePath),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = str_replace('/app/', '', $file->getPathname());
            }
        }
        
        return "Files in $dirPath:\n" . implode("\n", array_slice($files, 0, 50));
    }
    
    public static function searchFiles($pattern, $directory = '/app/src') {
        $results = [];
        $command = "grep -r --include='*.py' --include='*.php' --include='*.yaml' --include='*.yml' " . 
                  escapeshellarg($pattern) . " " . escapeshellarg($directory) . " 2>/dev/null";
        
        exec($command, $output);
        return "Search results for '$pattern':\n" . implode("\n", array_slice($output, 0, 20));
    }
}
?>