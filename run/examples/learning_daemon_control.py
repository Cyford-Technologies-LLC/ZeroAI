# run/internal/learning_daemon_control.py

import argparse
import time
from rich.console import Console
import sys
from pathlib import Path

# Add the src directory to the Python path
sys.path.insert(0, str(Path(__file__).parent.parent.parent / "src"))

from learning.daemon import LearningDaemon
from learning.task_manager import TaskManager

console = Console()

def main():
    parser = argparse.ArgumentParser(description="Control the ZeroAI Learning Daemon")
    subparsers = parser.add_subparsers(dest="command", help="Command to execute")
    
    # Start command
    start_parser = subparsers.add_parser("start", help="Start the learning daemon")
    
    # Stop command
    stop_parser = subparsers.add_parser("stop", help="Stop the learning daemon")
    
    # Status command
    status_parser = subparsers.add_parser("status", help="Check daemon status")
    
    # Add task command
    add_task_parser = subparsers.add_parser("add-task", help="Add a new task")
    add_task_parser.add_argument("--title", required=True, help="Task title")
    add_task_parser.add_argument("--description", required=True, help="Task description")
    add_task_parser.add_argument("--repo", help="Repository URL")
    add_task_parser.add_argument("--category", default="general", help="Task category")
    add_task_parser.add_argument("--priority", type=int, default=3, help="Priority (1-5, 1 is highest)")
    
    # List tasks command
    list_tasks_parser = subparsers.add_parser("list-tasks", help="List all tasks")
    list_tasks_parser.add_argument("--status", help="Filter by status (pending, in_progress, completed, failed)")
    
    # Sync GitHub command
    sync_github_parser = subparsers.add_parser("sync-github", help="Sync tasks from GitHub issues")
    sync_github_parser.add_argument("--repo", required=True, help="GitHub repository URL")
    sync_github_parser.add_argument("--token", help="GitHub token (optional)")
    
    args = parser.parse_args()
    
    # Handle commands
    if args.command == "start":
        daemon = LearningDaemon()
        daemon.start()
        console.print("🚀 Learning daemon started. Use 'stop' to terminate.", style="green")
    
    elif args.command == "stop":
        # Use a signal or shared file to communicate with the running daemon
        console.print("🛑 Sending stop signal to daemon...", style="yellow")
        # For demo, we'll just create a signal file
        with open("daemon_stop_signal", "w") as f:
            f.write(str(time.time()))
        console.print("Stop signal sent. Daemon should terminate soon.", style="green")
    
    elif args.command == "status":
        # Check if daemon is running (this is a simplified approach)
        import os
        if os.path.exists("daemon_stop_signal"):
            last_stop = os.path.getmtime("daemon_stop_signal")
            last_start = 0
            if os.path.exists("daemon_start_signal"):
                last_start = os.path.getmtime("daemon_start_signal")
            
            if last_start > last_stop:
                console.print("✅ Learning daemon is running", style="green")
            else:
                console.print("❌ Learning daemon is not running", style="red")
        else:
            console.print("⚠️ Unknown daemon status (no signal files found)", style="yellow")
    
    elif args.command == "add-task":
        task_manager = TaskManager()
        task_id = task_manager.add_task(
            title=args.title,
            description=args.description,
            repository=args.repo,
            category=args.category,
            priority=args.priority
        )
        console.print(f"✅ Task added with ID: {task_id}", style="green")
    
    elif args.command == "list-tasks":
        import json
        task_manager = TaskManager()
        with open(task_manager.tasks_file, 'r') as f:
            data = json.load(f)
        
        tasks = data["tasks"]
        if args.status:
            tasks = [t for t in tasks if t["status"] == args.status]
        
        if not tasks:
            console.print("No tasks found", style="yellow")
        else:
            console.print(f"Found {len(tasks)} tasks:", style="blue")
            for task in tasks:
                status_color = {
                    "pending": "yellow",
                    "in_progress": "blue",
                    "completed": "green",
                    "failed": "red"
                }.get(task["status"], "white")
                
                console.print(f"[{status_color}]{task['status'].upper()}[/{status_color}] - {task['title']}")
                console.print(f"  ID: {task['id']}")
                console.print(f"  Category: {task['category']}")
                console.print(f"  Priority: {task['priority']}")
                if task.get("repository"):
                    console.print(f"  Repository: {task['repository']}")
                console.print("")
    
    elif args.command == "sync-github":
        task_manager = TaskManager()
        task_manager.add_github_issues_as_tasks(args.repo, args.token)
    
    else:
        parser.print_help()

if __name__ == "__main__":
    main()