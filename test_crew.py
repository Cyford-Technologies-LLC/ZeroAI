#!/usr/bin/env python3
"""
Test script to directly run the AI DevOps crew and show conversations.
This bypasses the run_dev_ops.py script to test if the core functionality works.
"""

import sys
import os
from pathlib import Path

# Enable verbose output to see agent conversations
os.environ["CREWAI_VERBOSE"] = "1"

# Print current working directory for debugging
print(f"Current working directory: {os.getcwd()}")
print(f"Python path: {sys.path}")

# Try to import and run the AI DevOps crew
try:
    # Import router
    from devops_router import get_router

    # Get router
    router = get_router()
    print("✅ Successfully imported and initialized router")

    # Import crew manager
    from src.ai_dev_ops_crew import AIOpsCrewManager, run_ai_dev_ops_crew_securely
    print("✅ Successfully imported AI DevOps crew modules")

    # Execute a test task
    result = run_ai_dev_ops_crew_securely(
        router=router,
        project_id="test",
        inputs={
            "prompt": "Test task to demonstrate agent conversations",
            "category": "general",
            "task_id": "test-direct-123",
            "verbose": True
        }
    )

    # Print the result
    print("\n===== EXECUTION RESULT =====")
    print(result)

except ImportError as e:
    print(f"❌ Import error: {e}")
    print("Available modules in src:")
    try:
        src_dir = Path("src")
        if src_dir.exists():
            for file in src_dir.glob("*.py"):
                print(f"  - {file.name}")
    except Exception as nested_e:
        print(f"Error listing modules: {nested_e}")
except Exception as e:
    print(f"❌ Execution error: {e}")