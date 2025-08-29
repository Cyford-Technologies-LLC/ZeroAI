# run/internal/run_dev_ops.py

import sys
import os
import logging
from pathlib import Path

# --- FIX: Manually add project root to sys.path ---
script_dir = Path(__file__).resolve().parent
project_root = script_dir.parent.parent
sys.path.insert(0, str(project_root))
# ----------------------------------------------------

from src.ai_dev_ops_crew import run_ai_dev_ops_crew_securely
from your_secure_internal_router_setup import get_router

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

if __name__ == '__main__':
    if len(sys.argv) > 2:
        # FIX: Correctly extract project_id and topic from sys.argv
        project_id = sys.argv[1]
        topic = " ".join(sys.argv[2:])
        inputs = {"topic": topic, "category": "ai_dev_ops"}

        try:
            router = get_router()
        except Exception as e:
            logger.error(f"Failed to get secure router: {e}")
            sys.exit(1)

        logger.info(f"Running AI DevOps crew for project '{project_id}' on topic: {topic}")
        try:
            # FIX: Pass project_id as a string
            result = run_ai_dev_ops_crew_securely(router, project_id, inputs)
            print("\n--- Final Result ---")
            print(result)
        except Exception as e:
            logger.error(f"Error during AI DevOps crew execution: {e}", exc_info=True)
            sys.exit(1)
    else:
        print("Usage: python run/internal/run_dev_ops.py <project_id> <topic>")
