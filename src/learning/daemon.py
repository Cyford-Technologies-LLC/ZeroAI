# src/learning/daemon.py

import time
import logging
import threading
import os
import subprocess
from datetime import datetime
from pathlib import Path
from rich.console import Console
from learning.task_manager import TaskManager
from learning.feedback_loop import FeedbackLoop, TaskOutcome
from learning.adaptive_router import AdaptiveRouter
from peer_discovery import PeerDiscovery

console = Console()
logger = logging.getLogger("learning_daemon")

class LearningDaemon:
    def __init__(self):
        self.task_manager = TaskManager()
        self.feedback_loop = FeedbackLoop()
        self.peer_discovery = PeerDiscovery()
        self.peer_discovery.start_discovery_service()
        self.adaptive_router = AdaptiveRouter(self.peer_discovery)
        self.running = False
        self.daemon_thread = None
        self.log_file = Path("logs/learning_daemon.log")
        self.log_file.parent.mkdir(parents=True, exist_ok=True)
        
        # Configure logging
        logging.basicConfig(
            filename=self.log_file,
            level=logging.INFO,
            format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
        )
    
    def start(self):
        """Start the daemon"""
        if self.running:
            console.print("Daemon is already running", style="yellow")
            return
        
        self.running = True
        self.daemon_thread = threading.Thread(target=self._daemon_loop)
        self.daemon_thread.daemon = True
        self.daemon_thread.start()
        console.print("🚀 Learning daemon started", style="green")
    
    def stop(self):
        """Stop the daemon"""
        if not self.running:
            console.print("Daemon is not running", style="yellow")
            return
        
        self.running = False
        if self.daemon_thread:
            self.daemon_thread.join(timeout=5.0)
        console.print("🛑 Learning daemon stopped", style="yellow")
    
    def _daemon_loop(self):
        """Main daemon loop"""
        logger.info("Learning daemon started")
        
        while self.running:
            try:
                # Refresh adaptive router with latest learning
                self.adaptive_router.refresh_learned_mappings()
                
                # Check for new GitHub issues every hour
                self._check_github_repos()
                
                # Process the next task
                next_task = self.task_manager.get_next_task()
                if next_task:
                    console.print(f"🏃 Processing task: {next_task.title}", style="blue")
                    
                    # Mark as in progress
                    self.task_manager.update_task_status(next_task.id, "in_progress")
                    
                    # Execute the task
                    start_time = time.time()
                    success, error_message, result = self._execute_task(next_task)
                    end_time = time.time()
                    
                    # Update task status
                    status = "completed" if success else "failed"
                    self.task_manager.update_task_status(next_task.id, status, result)
                    
                    # Record feedback
                    if "model_used" in result and "peer_used" in result:
                        outcome = TaskOutcome(
                            task_id=next_task.id,
                            prompt=next_task.description,
                            category=next_task.category,
                            model_used=result["model_used"],
                            peer_used=result["peer_used"],
                            start_time=start_time,
                            end_time=end_time,
                            success=success,
                            error_message=error_message,
                            git_changes=result.get("git_changes"),
                            token_usage=result.get("token_usage")
                        )
                        self.feedback_loop.record_task_outcome(outcome)
                else:
                    console.print("No pending tasks found", style="yellow")
                    # Sleep longer when no tasks
                    time.sleep(300)  # 5 minutes
                    continue
            
            except Exception as e:
                logger.error(f"Error in daemon loop: {e}")
                console.print(f"❌ Daemon error: {e}", style="red")
            
            # Sleep between task processing
            time.sleep(60)  # 1 minute
    
    def _check_github_repos(self):
        """Check GitHub repos for new issues"""
        try:
            # This should be configured somewhere, for now just hardcode an example
            repos = [
                "https://github.com/Cyford-Technologies-LLC/ZeroAI.git"
            ]
            
            for repo in repos:
                self.task_manager.add_github_issues_as_tasks(repo)
        
        except Exception as e:
            logger.error(f"Error checking GitHub repos: {e}")
    
    def _execute_task(self, task):
        """Execute a task using the DevOps Crew"""
        try:
            # Prepare the run_dev_ops.py command
            cmd = [
                "python", 
                "run/internal/run_dev_ops.py",
                f"--task-id={task.id}",
                f"--category={task.category}",
                f"--repo={task.repository}" if task.repository else "",
                f"--branch={task.branch}" if task.branch else "",
                task.description
            ]
            
            # Run the command
            process = subprocess.Popen(
                cmd,
                stdout=subprocess.PIPE,
                stderr=subprocess.PIPE,
                text=True
            )
            
            stdout, stderr = process.communicate(timeout=3600)  # 1 hour timeout
            
            # Check if successful
            success = process.returncode == 0
            
            # Parse the output to extract results
            result = self._parse_output(stdout)
            
            return success, stderr if not success else None, result
        
        except subprocess.TimeoutExpired:
            return False, "Task execution timed out after 1 hour", {}
        
        except Exception as e:
            logger.error(f"Error executing task: {e}")
            return False, str(e), {}
    
    def _parse_output(self, output):
        """Parse the output from run_dev_ops.py to extract results"""
        result = {}
        
        # Simple parsing for key information
        lines = output.splitlines()
        for line in lines:
            if "Model=" in line and "Base URL=" in line:
                # Extract model and peer information
                model_part = line.split("Model=")[1].split(",")[0].strip("'")
                result["model_used"] = model_part
                
                base_url = line.split("Base URL=")[1].strip("'")
                # Extract peer name from URL
                peer_used = base_url.split("://")[1].split(":")[0]
                result["peer_used"] = peer_used
            
            elif "token_usage" in line:
                # Try to extract token usage
                try:
                    # This assumes token usage is output in a recognizable format
                    token_part = line.split("token_usage=")[1].strip()
                    if token_part.startswith("{") and token_part.endswith("}"):
                        import json
                        result["token_usage"] = json.loads(token_part)
                except:
                    pass
            
            elif "Git changes:" in line:
                # Extract git changes summary
                try:
                    git_changes = {}
                    git_part = line.split("Git changes:")[1].strip()
                    # Parse simple key-value pairs
                    for pair in git_part.split(","):
                        if ":" in pair:
                            key, value = pair.split(":", 1)
                            git_changes[key.strip()] = value.strip()
                    result["git_changes"] = git_changes
                except:
                    pass
        
        return result