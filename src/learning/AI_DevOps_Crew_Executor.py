# run/internal/run_dev_ops.py (modified version)

import sys
import os
import argparse
import json
import time
from pathlib import Path
from rich.console import Console
import logging

# Add the src directory to the Python path
sys.path.insert(0, str(Path(__file__).parent.parent.parent / "src"))

from ai_dev_ops_crew import AIOpsCrewManager
from learning.adaptive_router import AdaptiveRouter
from peer_discovery import PeerDiscovery
from learning.feedback_loop import TaskOutcome

console = Console()
logger = logging.getLogger(__name__)

def setup_logging():
    log_dir = Path("logs")
    log_dir.mkdir(exist_ok=True)
    
    logging.basicConfig(
        filename=log_dir / "dev_ops.log",
        level=logging.INFO,
        format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
    )

def main():
    setup_logging()
    
    parser = argparse.ArgumentParser(description="Run the AI DevOps Crew")
    parser.add_argument("prompt", type=str, help="The task description to process")
    parser.add_argument("--task-id", type=str, help="Task ID from the task manager")
    parser.add_argument("--category", type=str, default="general", help="Task category")
    parser.add_argument("--repo", type=str, help="Repository URL")
    parser.add_argument("--branch", type=str, help="Git branch name")
    
    args = parser.parse_args()
    
    console.print("🚀 [bold blue]ZeroAI DevOps Crew Executor[/bold blue]")
    console.print("=" * 50)
    
    # Use the adaptive router instead of the regular one
    peer_discovery = PeerDiscovery()
    router = AdaptiveRouter(peer_discovery)
    
    try:
        # Start timing the task
        start_time = time.time()
        
        # Create the manager with task context
        manager = AIOpsCrewManager(
            router=router,
            prompt=args.prompt,
            task_id=args.task_id,
            category=args.category,
            repository=args.repo,
            branch=args.branch
        )
        
        # Execute the crew
        result = manager.execute()
        
        # Finish timing
        end_time = time.time()
        
        # Record detailed results for the feedback loop
        if hasattr(manager, 'model_used') and hasattr(manager, 'peer_used'):
            model_used = manager.model_used
            peer_used = manager.peer_used
            
            # Print for output parsing
            console.print(f"Model={model_used}, Base URL={manager.base_url}")
            
            if args.task_id:
                # Create outcome data
                outcome = TaskOutcome(
                    task_id=args.task_id,
                    prompt=args.prompt,
                    category=args.category,
                    model_used=model_used,
                    peer_used=peer_used,
                    start_time=start_time,
                    end_time=end_time,
                    success=True,
                    git_changes=result.get("git_changes", {})
                )
                
                # Add token usage if available
                if hasattr(result, 'token_usage'):
                    outcome.token_usage = result.token_usage
                    console.print(f"token_usage={result.token_usage}")
                
                # Log git changes for parsing
                if "git_changes" in result:
                    console.print(f"Git changes: {json.dumps(result['git_changes'])}")
        
        console.print("\n✅ [bold green]DevOps task completed successfully![/bold green]")
        return 0
        
    except Exception as e:
        console.print(f"❌ Error during DevOps execution: {e}", style="red")
        logger.error(f"DevOps execution error: {e}")
        return 1

if __name__ == "__main__":
    sys.exit(main())