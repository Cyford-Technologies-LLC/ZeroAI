# src/learning/task_manager.py

import logging
import json
import time
import uuid
from datetime import datetime
from pathlib import Path
from typing import Dict, Any, List, Optional
from pydantic import BaseModel
from rich.console import Console

console = Console()
logger = logging.getLogger(__name__)

class Task(BaseModel):
    id: str
    title: str
    description: str
    repository: Optional[str] = None
    branch: Optional[str] = None
    status: str = "pending"  # pending, in_progress, completed, failed
    priority: int = 1  # 1 (highest) to 5 (lowest)
    created_at: float = time.time()
    started_at: Optional[float] = None
    completed_at: Optional[float] = None
    assigned_to: Optional[str] = None
    category: str = "general"
    dependencies: List[str] = []
    result: Optional[Dict[str, Any]] = None

class TaskManager:
    def __init__(self, tasks_file: str = "knowledge/tasks/task_queue.json"):
        self.tasks_file = Path(tasks_file)
        self.tasks_file.parent.mkdir(parents=True, exist_ok=True)
        
        # Initialize tasks file if it doesn't exist
        if not self.tasks_file.exists():
            with open(self.tasks_file, 'w') as f:
                json.dump({"tasks": []}, f)
    
    def add_task(self, title: str, description: str, repository: Optional[str] = None, 
                branch: Optional[str] = None, priority: int = 3, 
                category: str = "general", dependencies: List[str] = []) -> str:
        """Add a new task to the queue"""
        task_id = str(uuid.uuid4())
        task = Task(
            id=task_id,
            title=title,
            description=description,
            repository=repository,
            branch=branch,
            priority=priority,
            category=category,
            dependencies=dependencies
        )
        
        # Load existing tasks
        with open(self.tasks_file, 'r') as f:
            data = json.load(f)
        
        # Add new task
        data["tasks"].append(task.dict())
        
        # Save updated tasks
        with open(self.tasks_file, 'w') as f:
            json.dump(data, f, indent=2)
        
        console.print(f"✅ Added task {task_id}: {title}", style="green")
        return task_id
    
    def get_next_task(self) -> Optional[Task]:
        """Get the next task to work on"""
        try:
            with open(self.tasks_file, 'r') as f:
                data = json.load(f)
            
            # Filter for pending tasks
            pending_tasks = [Task(**task) for task in data["tasks"] 
                            if task["status"] == "pending"]
            
            if not pending_tasks:
                return None
            
            # Sort by priority and creation time
            pending_tasks.sort(key=lambda t: (t.priority, t.created_at))
            
            # Filter out tasks with unmet dependencies
            executable_tasks = []
            for task in pending_tasks:
                dependencies_met = True
                for dep_id in task.dependencies:
                    # Find the dependency task
                    dep_task = next((t for t in data["tasks"] if t["id"] == dep_id), None)
                    if not dep_task or dep_task["status"] != "completed":
                        dependencies_met = False
                        break
                
                if dependencies_met:
                    executable_tasks.append(task)
            
            if not executable_tasks:
                console.print("No tasks with satisfied dependencies found", style="yellow")
                return None
            
            # Return the highest priority task with satisfied dependencies
            return executable_tasks[0]
        
        except Exception as e:
            logger.error(f"Error getting next task: {e}")
            return None
    
    def update_task_status(self, task_id: str, status: str, result: Optional[Dict[str, Any]] = None) -> bool:
        """Update the status of a task"""
        try:
            with open(self.tasks_file, 'r') as f:
                data = json.load(f)
            
            # Find the task
            for task in data["tasks"]:
                if task["id"] == task_id:
                    task["status"] = status
                    
                    if status == "in_progress" and not task.get("started_at"):
                        task["started_at"] = time.time()
                    
                    if status in ["completed", "failed"]:
                        task["completed_at"] = time.time()
                    
                    if result is not None:
                        task["result"] = result
                    
                    # Save updated tasks
                    with open(self.tasks_file, 'w') as f:
                        json.dump(data, f, indent=2)
                    
                    console.print(f"📝 Updated task {task_id} status to {status}", style="blue")
                    return True
            
            console.print(f"❌ Task {task_id} not found", style="red")
            return False
        
        except Exception as e:
            logger.error(f"Error updating task status: {e}")
            return False
    
    def add_github_issues_as_tasks(self, repo_url: str, token: Optional[str] = None):
        """Pull GitHub issues and add them as tasks"""
        try:
            from github import Github
            from github.GithubException import GithubException
            
            # Extract owner and repo from URL
            parts = repo_url.rstrip('/').split('/')
            owner = parts[-2]
            repo_name = parts[-1]
            if repo_name.endswith('.git'):
                repo_name = repo_name[:-4]
            
            console.print(f"🔍 Scanning GitHub issues from {owner}/{repo_name}...", style="blue")
            
            # Connect to GitHub
            g = Github(token) if token else Github()
            repo = g.get_repo(f"{owner}/{repo_name}")
            
            # Get open issues
            open_issues = repo.get_issues(state="open")
            added_count = 0
            
            for issue in open_issues:
                # Check if we already have this issue as a task
                with open(self.tasks_file, 'r') as f:
                    data = json.load(f)
                
                # Skip if the issue is already in our tasks
                if any(f"Issue #{issue.number}" in task["title"] for task in data["tasks"]):
                    continue
                
                # Add as a new task
                task_title = f"Issue #{issue.number}: {issue.title}"
                task_description = issue.body or "No description provided."
                
                # Add labels as metadata
                label_text = ", ".join([label.name for label in issue.labels])
                task_description += f"\n\nLabels: {label_text}"
                
                # Determine category based on labels
                category = "general"
                for label in issue.labels:
                    label_lower = label.name.lower()
                    if "bug" in label_lower:
                        category = "developer"
                        break
                    elif "documentation" in label_lower:
                        category = "documentation"
                        break
                    elif "feature" in label_lower:
                        category = "developer"
                        break
                
                # Add the task
                self.add_task(
                    title=task_title,
                    description=task_description,
                    repository=repo_url,
                    category=category,
                    priority=2 if "bug" in label_text.lower() else 3
                )
                added_count += 1
            
            console.print(f"✅ Added {added_count} new tasks from GitHub issues", style="green")
            
        except GithubException as e:
            console.print(f"❌ GitHub API error: {e.status} - {e.data.get('message', '')}", style="red")
        except Exception as e:
            console.print(f"❌ Error scanning GitHub issues: {e}", style="red")