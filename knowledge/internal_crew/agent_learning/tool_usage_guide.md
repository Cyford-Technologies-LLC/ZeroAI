# 🛠️ Tool Usage Guide for ZeroAI Agents

## Available Tools

### 1. **File System Tool**
**Purpose**: Read and write files to the working directory

**Parameters**:
- `action`: "read" or "write"
- `path`: Full file path (e.g., "/tmp/internal_crew/zeroai/script.php")
- `content`: File content (required for write operations)

**Examples**:
```json
// Write a file
{
  "action": "write",
  "path": "/tmp/internal_crew/zeroai/example.php",
  "content": "<?php\necho 'Hello World';\n?>"
}

// Read a file
{
  "action": "read",
  "path": "/tmp/internal_crew/zeroai/example.php"
}
```

### 2. **Git Operator Tool**
**Purpose**: Execute Git commands for repository management

**Parameters**:
- `command`: Git command to execute
- `repo_path`: Repository path (optional)

**Examples**:
```json
{
  "command": "git status"
}

{
  "command": "git add .",
  "repo_path": "/tmp/internal_crew/zeroai"
}
```

### 3. **Docker Operator Tool**
**Purpose**: Run Docker commands for containerized operations

**Parameters**:
- `command`: Docker command to execute
- `kwargs`: Additional arguments

**Examples**:
```json
{
  "command": "docker ps"
}

{
  "command": "docker run hello-world"
}
```

### 4. **Project Tool**
**Purpose**: Get project configuration information

**Parameters**:
- `project_location`: Project path (e.g., "cyford/zeroai")
- `mode`: "all", "file", or specific key like "repository.url"

**Examples**:
```json
// Get full config
{
  "project_location": "cyford/zeroai",
  "mode": "all"
}

// Get specific value
{
  "project_location": "cyford/zeroai", 
  "mode": "repository.url"
}
```

### 5. **Dynamic GitHub Search Tool**
**Purpose**: Search GitHub repositories

**Parameters**:
- `repo_name`: Repository name (e.g., "Cyford-Technologies-LLC/ZeroAI")
- `token_key`: Authentication token key (optional, auto-configured)
- `query`: Search query

**Examples**:
```json
{
  "repo_name": "Cyford-Technologies-LLC/ZeroAI",
  "query": "file creation"
}
```

## 🎯 Best Practices

### File Creation Tasks
1. **Always use File System Tool** when asked to create files
2. **Include all required parameters**: action, path, content
3. **Use full paths**: `/tmp/internal_crew/zeroai/filename.ext`
4. **Verify file creation** by reading it back if needed

### Working Directory
- Default working directory: `/tmp/internal_crew/zeroai/`
- Always use full paths for reliability
- Create subdirectories as needed

### Error Handling
- Check tool responses for errors
- Retry with corrected parameters if needed
- Report specific error messages to user

## ⚠️ Common Mistakes to Avoid

1. **Missing content parameter** in File System Tool write operations
2. **Using relative paths** instead of full paths
3. **Not checking tool responses** for errors
4. **Providing code in response** instead of creating actual files

## 🔧 Tool Selection Guide

- **Creating files**: Use File System Tool
- **Reading files**: Use File System Tool
- **Git operations**: Use Git Operator Tool
- **Project info**: Use Project Tool
- **GitHub search**: Use Dynamic GitHub Search Tool
- **Docker operations**: Use Docker Operator Tool

Remember: When asked to implement or create files, always use the appropriate tool to create the actual files, not just provide code in your response!