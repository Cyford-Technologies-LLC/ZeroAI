#!/bin/bash
# ZeroAI Root Directory Cleanup Script

echo "🧹 Starting ZeroAI root directory cleanup..."

# Create backup trash directory
mkdir -p backup/trash/$(date +%Y%m%d_%H%M%S)
TRASH_DIR="backup/trash/$(date +%Y%m%d_%H%M%S)"

# Create tools directory if it doesn't exist
mkdir -p tools

echo "📁 Created backup directory: $TRASH_DIR"

# Files to KEEP in root (essential for Docker/system)
KEEP_FILES=(
    "Dockerfile"
    "Docker-compose.yml"
    "docker-compose.test.yml"
    "docker-compose.gpu.override.yml"
    "nginx.conf"
    "requirements.txt"
    "setup_zeroai.sh"
    "setup-docker.sh"
    "cleanup_root.sh"
    ".env"
    ".env.example"
    ".gitignore"
    "README.md"
    "LICENSE"
    "CONTRIBUTING.md"
)

# Directories to KEEP in root (essential)
KEEP_DIRS=(
    "src"
    "API"
    "run"
    "config"
    "examples"
    "logs"
    "www"
    "data"
    "backup"
    "scripts"
    "knowledge"
    "tools"
    ".git"
)

# Move useful tools to tools directory
echo "🔧 Moving useful tools to tools/ directory..."

# Move any test/debug files to tools
find . -maxdepth 1 -name "test_*" -type f -exec mv {} tools/ \;
find . -maxdepth 1 -name "debug_*" -type f -exec mv {} tools/ \;
find . -maxdepth 1 -name "*_test.py" -type f -exec mv {} tools/ \;
find . -maxdepth 1 -name "*_debug.py" -type f -exec mv {} tools/ \;

# Move setup/install scripts to tools
find . -maxdepth 1 -name "install_*" -type f -exec mv {} tools/ \;
find . -maxdepth 1 -name "setup_*" -type f ! -name "setup_zeroai.sh" ! -name "setup-docker.sh" -exec mv {} tools/ \;

# Move any .sh scripts except essential ones to tools
find . -maxdepth 1 -name "*.sh" -type f ! -name "setup_zeroai.sh" ! -name "setup-docker.sh" ! -name "cleanup_root.sh" -exec mv {} tools/ \;

# Move documentation files to backup (keep README.md in root)
find . -maxdepth 1 -name "*.md" -type f ! -name "README.md" ! -name "CONTRIBUTING.md" -exec mv {} $TRASH_DIR/ \;

# Move any Python files in root to tools
find . -maxdepth 1 -name "*.py" -type f -exec mv {} tools/ \;

# Move any config files that aren't essential
find . -maxdepth 1 -name "*.conf" -type f ! -name "nginx.conf" -exec mv {} $TRASH_DIR/ \;
find . -maxdepth 1 -name "*.ini" -type f -exec mv {} $TRASH_DIR/ \;
find . -maxdepth 1 -name "*.cfg" -type f -exec mv {} $TRASH_DIR/ \;

# Move any temporary/cache files
find . -maxdepth 1 -name "*.tmp" -type f -exec mv {} $TRASH_DIR/ \;
find . -maxdepth 1 -name "*.cache" -type f -exec mv {} $TRASH_DIR/ \;
find . -maxdepth 1 -name "*.log" -type f -exec mv {} $TRASH_DIR/ \;

# Move any build artifacts
find . -maxdepth 1 -name "*.tar.gz" -type f -exec mv {} $TRASH_DIR/ \;
find . -maxdepth 1 -name "*.zip" -type f -exec mv {} $TRASH_DIR/ \;

# Move any other files not in keep list
echo "🗑️ Moving non-essential files to trash..."
for file in *; do
    if [ -f "$file" ]; then
        keep=false
        for keep_file in "${KEEP_FILES[@]}"; do
            if [ "$file" = "$keep_file" ]; then
                keep=true
                break
            fi
        done
        if [ "$keep" = false ]; then
            echo "  Moving file: $file"
            mv "$file" "$TRASH_DIR/"
        fi
    fi
done

# Move any directories not in keep list
for dir in */; do
    dir=${dir%/}  # Remove trailing slash
    if [ -d "$dir" ]; then
        keep=false
        for keep_dir in "${KEEP_DIRS[@]}"; do
            if [ "$dir" = "$keep_dir" ]; then
                keep=true
                break
            fi
        done
        if [ "$keep" = false ]; then
            echo "  Moving directory: $dir"
            mv "$dir" "$TRASH_DIR/"
        fi
    fi
done

echo "✅ Cleanup complete!"
echo ""
echo "📊 Summary:"
echo "  - Essential files kept in root"
echo "  - Useful tools moved to tools/"
echo "  - Everything else moved to $TRASH_DIR"
echo ""
echo "🗂️ Root directory now contains only:"
ls -la | grep -E '^-' | awk '{print "  - " $9}'
echo ""
echo "📁 Directories:"
ls -la | grep -E '^d' | grep -v '^\.$' | grep -v '^\.\.$' | awk '{print "  - " $9 "/"}'
echo ""
echo "🔧 Tools directory contains:"
ls -la tools/ 2>/dev/null | grep -E '^-' | awk '{print "  - " $9}' || echo "  (empty)"
echo ""
echo "🗑️ Backup location: $TRASH_DIR"
echo "💡 You can safely delete the backup after confirming everything works"