#!/usr/bin/env python3
"""
Import Fixer - Standardizes all imports to use src. prefix
Run this after every merge to fix import issues automatically
"""

import os
import re
from pathlib import Path

def fix_imports_in_file(file_path):
    """Fix imports in a single file."""
    try:
        with open(file_path, 'r', encoding='utf-8') as f:
            content = f.read()
        
        original_content = content
        
        # Fix common import patterns
        fixes = [
            (r'from distributed_router import', 'from src.distributed_router import'),
            (r'from peer_discovery import', 'from src.peer_discovery import'),
            (r'from config import', 'from src.config import'),
            (r'from env_loader import', 'from src.utils.env_loader import'),
            (r'from devops_router import', 'from src.distributed_router import'),
        ]
        
        for pattern, replacement in fixes:
            content = re.sub(pattern, replacement, content)
        
        # Only write if changed
        if content != original_content:
            with open(file_path, 'w', encoding='utf-8') as f:
                f.write(content)
            print(f"✅ Fixed: {file_path}")
            return True
        return False
        
    except Exception as e:
        print(f"❌ Error fixing {file_path}: {e}")
        return False

def main():
    """Fix all Python files in the project."""
    project_root = Path(__file__).parent
    python_files = list(project_root.rglob("*.py"))
    
    fixed_count = 0
    for file_path in python_files:
        # Skip __pycache__ and .git directories
        if '__pycache__' in str(file_path) or '.git' in str(file_path):
            continue
            
        if fix_imports_in_file(file_path):
            fixed_count += 1
    
    print(f"\n🎉 Fixed {fixed_count} files")
    print("✅ All imports standardized!")

if __name__ == "__main__":
    main()