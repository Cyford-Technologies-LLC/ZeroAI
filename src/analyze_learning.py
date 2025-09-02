#!/usr/bin/env python3
"""
Learning Analysis Tool

This script analyzes the learning metrics collected by the feedback loop system
and provides insights on model performance, peer performance, and category statistics.
"""

import sys
import os
import argparse
import json
from pathlib import Path
from rich.console import Console
from rich.table import Table
import time
from datetime import datetime

# Add the src directory to the Python path
sys.path.insert(0, str(Path(__file__).parent.parent.parent / "src"))

# Configure console for rich output
console = Console()

# Helper function to ensure directory exists
def ensure_dir_exists(directory_path):
    """Ensure that a directory exists, creating it if necessary."""
    if isinstance(directory_path, str):
        directory_path = Path(directory_path)

    directory_path.mkdir(parents=True, exist_ok=True)
    return directory_path

# Try to import learning module
try:
    from learning.feedback_loop import feedback_loop
    has_learning = True
except ImportError:
    console.print("❌ Learning module not found. Unable to analyze learning metrics.", style="red")
    console.print("Make sure you're running from the project root directory or install the learning module.", style="red")
    has_learning = False
    sys.exit(1)

def setup_arg_parser():
    """Set up and return the argument parser."""
    parser = argparse.ArgumentParser(description="Analyze Learning Metrics")

    # Action argument
    parser.add_argument("--action", default="summary",
                      help="Action to perform: summary, models, peers, categories, tasks")

    # Optional filters
    parser.add_argument("--category", default=None,
                      help="Filter by category")
    parser.add_argument("--model", default=None,
                      help="Filter by model")
    parser.add_argument("--peer", default=None,
                      help="Filter by peer")
    parser.add_argument("--days", type=int, default=None,
                      help="Show data from the last N days")
    parser.add_argument("--verbose", "-v", action="store_true",
                      help="Show more detailed information")

    return parser

def load_metrics():
    """Load the learning metrics."""
    try:
        metrics_file = Path("knowledge/learning/learning_metrics.json")
        if not metrics_file.exists():
            console.print(f"❌ Metrics file not found at {metrics_file}", style="red")
            return None

        with open(metrics_file, 'r') as f:
            return json.load(f)
    except Exception as e:
        console.print(f"❌ Error loading metrics: {e}", style="red")
        return None

def load_outcomes(days=None):
    """Load the task outcomes, optionally filtered by days."""
    try:
        outcomes_file = Path("knowledge/learning/task_outcomes.json")
        if not outcomes_file.exists():
            console.print(f"❌ Outcomes file not found at {outcomes_file}", style="red")
            return []

        with open(outcomes_file, 'r') as f:
            outcomes = json.load(f)

        # Filter by days if specified
        if days is not None:
            now = time.time()
            cutoff = now - (days * 24 * 60 * 60)
            outcomes = [o for o in outcomes if o.get("timestamp", 0) >= cutoff]

        return outcomes
    except Exception as e:
        console.print(f"❌ Error loading outcomes: {e}", style="red")
        return []

def format_duration(seconds):
    """Format duration in seconds to a readable string."""
    if seconds < 1:
        return f"{seconds*1000:.1f}ms"
    elif seconds < 60:
        return f"{seconds:.2f}s"
    else:
        minutes = int(seconds // 60)
        seconds = seconds % 60
        return f"{minutes}m {seconds:.1f}s"

def show_summary(metrics, outcomes, args):
    """Show a summary of the learning metrics."""
    console.print("\n[bold blue]📊 Learning System Summary[/bold blue]")

    # Summary Table
    table = Table(title="Overall Statistics")
    table.add_column("Category", style="cyan")
    table.add_column("Tasks", style="green")
    table.add_column("Success Rate", style="yellow")
    table.add_column("Preferred Model", style="magenta")

    for category, stats in metrics["categories"].items():
        # Skip if we're filtering by category
        if args.category and args.category != category:
            continue

        tasks = stats.get("tasks", 0)
        successes = stats.get("successes", 0)
        success_rate = f"{(successes / tasks * 100):.1f}%" if tasks > 0 else "N/A"

        # Get preferred model
        models = stats.get("models", {})
        preferred_model = max(models.items(), key=lambda x: x[1])[0] if models else "None"

        table.add_row(category, str(tasks), success_rate, preferred_model)

    console.print(table)

    # Recent Tasks Table
    recent_outcomes = sorted(outcomes, key=lambda x: x.get("timestamp", 0), reverse=True)[:5]

    if recent_outcomes:
        console.print("\n[bold blue]🕒 Recent Tasks[/bold blue]")

        table = Table(title="Last 5 Tasks")
        table.add_column("Date", style="cyan")
        table.add_column("Category", style="green")
        table.add_column("Model", style="magenta")
        table.add_column("Success", style="yellow")
        table.add_column("Time", style="blue")

        for outcome in recent_outcomes:
            date = outcome.get("date", "Unknown")
            category = outcome.get("category", "Unknown")
            model = outcome.get("model", "Unknown")
            success = "✅" if outcome.get("success", False) else "❌"
            exec_time = format_duration(outcome.get("execution_time", 0))

            table.add_row(date, category, model, success, exec_time)

        console.print(table)

def show_models(metrics, outcomes, args):
    """Show detailed information about models."""
    console.print("\n[bold blue]🤖 Model Performance[/bold blue]")

    # Models Table
    table = Table(title="Model Statistics")
    table.add_column("Model", style="cyan")
    table.add_column("Tasks", style="green")
    table.add_column("Success Rate", style="yellow")
    table.add_column("Avg Time", style="blue")
    table.add_column("Avg Tokens", style="magenta")
    table.add_column("Learning Tokens", style="red")

    for model, stats in metrics["models"].items():
        # Skip if we're filtering by model
        if args.model and args.model != model:
            continue

        tasks = stats.get("tasks", 0)
        successes = stats.get("successes", 0)
        success_rate = f"{(successes / tasks * 100):.1f}%" if tasks > 0 else "N/A"
        avg_time = format_duration(stats.get("avg_time", 0))
        avg_tokens = f"{stats.get('avg_tokens', 0):.1f}"
        learning_tokens = str(stats.get("tokens", 0))

        table.add_row(model, str(tasks), success_rate, avg_time, avg_tokens, learning_tokens)

    console.print(table)

    # Model Category Distribution
    if args.verbose:
        console.print("\n[bold blue]📊 Model Category Distribution[/bold blue]")

        model_categories = {}
        for category, stats in metrics["categories"].items():
            models = stats.get("models", {})
            for model, tokens in models.items():
                if model not in model_categories:
                    model_categories[model] = {}
                model_categories[model][category] = tokens

        table = Table(title="Model Usage by Category")
        table.add_column("Model", style="cyan")

        # Add a column for each category
        categories = sorted(metrics["categories"].keys())
        for category in categories:
            table.add_column(category, style="green")

        for model, categories_dict in model_categories.items():
            # Skip if we're filtering by model
            if args.model and args.model != model:
                continue

            row = [model]
            for category in categories:
                tokens = categories_dict.get(category, 0)
                row.append(str(tokens))

            table.add_row(*row)

        console.print(table)

def show_peers(metrics, outcomes, args):
    """Show detailed information about peers."""
    console.print("\n[bold blue]🖥️ Peer Performance[/bold blue]")

    # Peers Table
    table = Table(title="Peer Statistics")
    table.add_column("Peer", style="cyan")
    table.add_column("Tasks", style="green")
    table.add_column("Success Rate", style="yellow")
    table.add_column("Learning Tokens", style="red")

    for peer, stats in metrics["peers"].items():
        # Skip if we're filtering by peer
        if args.peer and args.peer != peer:
            continue

        tasks = stats.get("tasks", 0)
        successes = stats.get("successes", 0)
        success_rate = f"{(successes / tasks * 100):.1f}%" if tasks > 0 else "N/A"
        learning_tokens = str(stats.get("tokens", 0))

        table.add_row(peer, str(tasks), success_rate, learning_tokens)

    console.print(table)

def show_categories(metrics, outcomes, args):
    """Show detailed information about categories."""
    console.print("\n[bold blue]📁 Category Performance[/bold blue]")

    # Categories Table
    table = Table(title="Category Statistics")
    table.add_column("Category", style="cyan")
    table.add_column("Tasks", style="green")
    table.add_column("Success Rate", style="yellow")
    table.add_column("Preferred Model", style="magenta")
    table.add_column("Learning Tokens", style="red")

    for category, stats in metrics["categories"].items():
        # Skip if we're filtering by category
        if args.category and args.category != category:
            continue

        tasks = stats.get("tasks", 0)
        successes = stats.get("successes", 0)
        success_rate = f"{(successes / tasks * 100):.1f}%" if tasks > 0 else "N/A"

        # Get preferred model
        models = stats.get("models", {})
        preferred_model = max(models.items(), key=lambda x: x[1])[0] if models else "None"

        learning_tokens = str(stats.get("tokens", 0))

        table.add_row(category, str(tasks), success_rate, preferred_model, learning_tokens)

    console.print(table)

    # If verbose and filtering by a specific category, show models for that category
    if args.verbose and args.category:
        category_stats = metrics["categories"].get(args.category, {})
        models = category_stats.get("models", {})

        if models:
            console.print(f"\n[bold blue]🤖 Models for {args.category} Category[/bold blue]")

            table = Table(title=f"Model Performance in {args.category}")
            table.add_column("Model", style="cyan")
            table.add_column("Learning Tokens", style="red")

            for model, tokens in sorted(models.items(), key=lambda x: x[1], reverse=True):
                table.add_row(model, str(tokens))

            console.print(table)

def show_tasks(metrics, outcomes, args):
    """Show detailed information about recent tasks."""
    console.print("\n[bold blue]📋 Task History[/bold blue]")

    # Filter outcomes
    filtered_outcomes = outcomes
    if args.category:
        filtered_outcomes = [o for o in filtered_outcomes if o.get("category") == args.category]
    if args.model:
        filtered_outcomes = [o for o in filtered_outcomes if o.get("model") == args.model]
    if args.peer:
        filtered_outcomes = [o for o in filtered_outcomes if o.get("peer") == args.peer]

    # Sort by timestamp (newest first)
    filtered_outcomes = sorted(filtered_outcomes, key=lambda x: x.get("timestamp", 0), reverse=True)

    # Limit to 20 unless verbose
    if not args.verbose and len(filtered_outcomes) > 20:
        filtered_outcomes = filtered_outcomes[:20]

    # Tasks Table
    table = Table(title="Task History")
    table.add_column("Date", style="cyan")
    table.add_column("Category", style="green")
    table.add_column("Model", style="magenta")
    table.add_column("Success", style="yellow")
    table.add_column("Time", style="blue")
    table.add_column("Tokens", style="red")

    for outcome in filtered_outcomes:
        date = outcome.get("date", "Unknown")
        category = outcome.get("category", "Unknown")
        model = outcome.get("model", "Unknown")
        success = "✅" if outcome.get("success", False) else "❌"
        exec_time = format_duration(outcome.get("execution_time", 0))
        tokens = str(outcome.get("tokens", 0))

        table.add_row(date, category, model, success, exec_time, tokens)

    console.print(table)

    # If verbose, show detailed task information
    if args.verbose and len(filtered_outcomes) > 0:
        task = filtered_outcomes[0]  # Show details for the most recent task

        console.print(f"\n[bold blue]🔍 Task Details: {task.get('task_id', 'Unknown ID')}[/bold blue]")

        console.print(f"📅 Date: {task.get('date', 'Unknown')}")
        console.print(f"📁 Category: {task.get('category', 'Unknown')}")
        console.print(f"🤖 Model: {task.get('model', 'Unknown')}")
        console.print(f"🖥️ Peer: {task.get('peer', 'Unknown')}")
        console.print(f"⏱️ Execution Time: {format_duration(task.get('execution_time', 0))}")
        console.print(f"🎯 Success: {'Yes' if task.get('success', False) else 'No'}")
        console.print(f"🎓 Learning Tokens: {task.get('learning_tokens', 0)}")

        console.print("\n📝 Prompt:")
        console.print(task.get("prompt", ""))

        if not task.get("success", False) and task.get("error_message"):
            console.print("\n❌ Error Message:")
            console.print(task.get("error_message", ""))

def main():
    """Main entry point for the script."""
    try:
        # Parse command-line arguments
        parser = setup_arg_parser()
        args = parser.parse_args()

        # Load metrics and outcomes
        metrics = load_metrics()
        if not metrics:
            return 1

        outcomes = load_outcomes(args.days)

        # Show requested information
        console.print(f"[bold]Learning Analysis - {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}[/bold]")

        if args.action == "summary":
            show_summary(metrics, outcomes, args)
        elif args.action == "models":
            show_models(metrics, outcomes, args)
        elif args.action == "peers":
            show_peers(metrics, outcomes, args)
        elif args.action == "categories":
            show_categories(metrics, outcomes, args)
        elif args.action == "tasks":
            show_tasks(metrics, outcomes, args)
        else:
            console.print(f"❌ Unknown action: {args.action}", style="red")
            return 1

        return 0

    except KeyboardInterrupt:
        console.print("\n⚠️ Operation cancelled by user.")
        return 130
    except Exception as e:
        console.print(f"\n❌ Fatal error: {e}", style="red")
        return 1

if __name__ == "__main__":
    sys.exit(main())