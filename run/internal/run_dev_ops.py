#   run/internal/run_dev_ops.py


import sys
import logging
from src.ai_dev_ops_crew import run_ai_dev_ops_crew_securely # Corrected import path
from your_secure_internal_router_setup import get_router

# Configure logging for the command-line script
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

if __name__ == '__main__':
    if len(sys.argv) > 1:
        topic = " ".join(sys.argv[1:])
        inputs = {"topic": topic, "category": "dev_ops"}

        try:
            router = get_router()
        except Exception as e:
            logger.error(f"Failed to get secure router: {e}")
            sys.exit(1)

        logger.info(f"Running DevOps crew for topic: {topic}")
        # Call the corrected function name
        result = run_ai_dev_ops_crew_securely(router, inputs)

        print("\n--- Final Result ---")
        print(result)
    else:
        print("Usage: python run_dev_ops.py <topic>")
