# run/internal/analyze_learning.py
#!/usr/bin/env python3
"""
Analyze ZeroAI Learning Data

This script provides tools to analyze the learning data collected
by the feedback loop system.
"""

import sys
import argparse
import json
from pathlib import Path
from rich.console import Console
from rich.table import Table
from rich.panel import Panel

# Add the src directory to the Python path
sys.path.insert(0, str(Path(__file__).parent.parent.parent / "src"))

console = Console()

def load_learning_data():
    """Load learning data from files."""
    try:
        metrics_file = Path("knowledge/learning/learning_metrics.json")
        outcomes_file = Path("knowledge/learning/task_outcomes.json")
        
        if not metrics_file.exists() or not outcomes_file.exists():
            console.print("❌ Learning data files not found", style="red")
            return None, None
        
        with open(metrics_file, 'r') as f:
            metrics = json.load(f)
        
        with open(outcomes_file, 'r') as f:
            outcomes = json.load(f)
        
        return metrics, outcomes
    
    except Exception as e:
        console.print(f"❌ Error loading learning data: {e}", style="red")
        return None, None

def show_summary(metrics, outcomes):
    """Show a summary of learning data."""
    if not metrics or not outcomes:
        console.print("❌ No learning data available", style="red")
        return
    
    console.print("\n📊 [bold blue]ZeroAI Learning Summary[/bold blue]")
    console.print("=" * 60)
    
    # Overall stats
    total_tasks = len(outcomes)
    successful_tasks = sum(1 for outcome in outcomes if outcome["success"])
    success_rate = (successful_tasks / total_tasks) * 100 if total_tasks > 0 else 0
    
    console.print(f"Total tasks: {total_tasks}")
    console.print(f"Successful tasks: {successful_tasks}")
    console.print(f"Success rate: {success_rate:.2f}%")
    
    # Model stats
    console.print("\n🤖 [bold]Model Performance[/bold]")
    
    model_table = Table(show_header=True)
    model_table.add_column("Model", style="cyan")
    model_table.add_column("Tasks", style="white", justify="right")
    model_table.add_column("Success Rate", style="green", justify="right")
    model_table.add_column("Avg. Time (s)", style="yellow", justify="right")
    model_table.add_column("Tokens", style="blue", justify="right")
    
    for model, stats in metrics["models"].items():
        model_table.add_row(
            model,
            str(stats["tasks"]),
            f"{stats['success_rate']*100:.2f}%" if stats["tasks"] > 0 else "N/A",
            f"{stats['avg_time']:.2f}" if stats["tasks"] > 0 else "N/A",
            str(stats["tokens"])
        )
    
    console.print(model_table)
    
    # Category stats
    console.print("\n🏷️ [bold]Category Performance[/bold]")
    
    category_table = Table(show_header=True)
    category_table.add_column("Category", style="cyan")
    category_table.add_column("Tasks", style="white", justify="right")
    category_table.add_column("Success Rate", style="green", justify="right")
    category_table.add_column("Tokens", style="blue", justify="right")
    category_table.add_column("Preferred Model", style="magenta")
    
    for category, stats in metrics["categories"].items():
        success_rate = (stats["successes"] / stats["tasks"]) * 100 if stats["tasks"] > 0 else 0
        
        # Find preferred model for this category
        preferred_model = "N/A"
        if stats["models"]:
            preferred_model = max(stats["models"].items(), key=lambda x: x[1])[0]
        
        category_table.add_row(
            category,
            str(stats["tasks"]),
            f"{success_rate:.2f}%",
            str(stats["tokens"]),
            preferred_model
        )
    
    console.print(category_table)
    
    # Peer stats
    console.print("\n🖥️ [bold]Peer Performance[/bold]")
    
    peer_table = Table(show_header=True)
    peer_table.add_column("Peer", style="cyan")
    peer_table.add_column("Tasks", style="white", justify="right")
    peer_table.add_column("Success Rate", style="green", justify="right")
    peer_table.add_column("Tokens", style="blue", justify="right")
    
    for peer, stats in metrics["peers"].items():
        success_rate = (stats["successes"] / stats["tasks"]) * 100 if stats["tasks"] > 0 else 0
        
        peer_table.add_row(
            peer,
            str(stats["tasks"]),
            f"{success_rate:.2f}%",
            str(stats["tokens"])
        )
    
    console.print(peer_table)

def show_models(metrics, outcomes):
    """Show detailed model performance."""
    if not metrics or not outcomes:
        console.print("❌ No learning data available", style="red")
        return
    
    console.print("\n🤖 [bold blue]ZeroAI Model Performance[/bold blue]")
    console.print("=" * 60)
    
    # Model ranking
    console.print("\n🏆 [bold]Model Ranking (by tokens)[/bold]")
    
    ranked_models = sorted(metrics["models"].items(), key=lambda x: x[1]["tokens"], reverse=True)
    
    rank_table = Table(show_header=True)
    rank_table.add_column("Rank", style="cyan", justify="right")
    rank_table.add_column("Model", style="white")
    rank_table.add_column("Tokens", style="green", justify="right")
    rank_table.add_column("Tasks", style="blue", justify="right")
    rank_table.add_column("Success Rate", style="yellow", justify="right")
    
    for i, (model, stats) in enumerate(ranked_models, 1):
        rank_table.add_row(
            str(i),
            model,
            str(stats["tokens"]),
            str(stats["tasks"]),
            f"{stats['success_rate']*100:.2f}%" if stats["tasks"] > 0 else "N/A"
        )
    
    console.print(rank_table)
    
    # Model details
    for model, stats in metrics["models"].items():
        console.print(f"\n📋 [bold]Details for model: {model}[/bold]")
        
        detail_table = Table(show_header=True)
        detail_table.add_column("Metric", style="cyan")
        detail_table.add_column("Value", style="white")
        
        detail_table.add_row("Total tasks", str(stats["tasks"]))
        detail_table.add_row("Successful tasks", str(stats["successes"]))
        detail_table.add_row("Failed tasks", str(stats["failures"]))
        detail_table.add_row("Success rate", f"{stats['success_rate']*100:.2f}%" if stats["tasks"] > 0 else "N/A")
        detail_table.add_row("Total tokens used", str(stats["total_tokens"]))
        detail_table.add_row("Average tokens per task", f"{stats['avg_tokens']:.2f}" if stats["tasks"] > 0 else "N/A")
        detail_table.add_row("Total execution time", f"{stats['total_time']:.2f} seconds")
        detail_table.add_row("Average time per task", f"{stats['avg_time']:.2f} seconds" if stats["tasks"] > 0 else "N/A")
        detail_table.add_row("Learning tokens earned", str(stats["tokens"]))
        
        console.print(detail_table)
        
        # Find categories where this model is preferred
        preferred_categories = []
        for category, cat_stats in metrics["categories"].items():
            if cat_stats["models"] and model in cat_stats["models"]:
                if model == max(cat_stats["models"].items(), key=lambda x: x[1])[0]:
                    preferred_categories.append(category)
        
        if preferred_categories:
            console.print(f"Preferred for categories: {', '.join(preferred_categories)}")
        else:
            console.print("Not the preferred model for any category")

def main():
    """Main entry point for the script."""
    parser = argparse.ArgumentParser(description="Analyze ZeroAI Learning Data")
    parser.add_argument("--action", default="summary",
                       choices=["summary", "models", "categories", "peers"],
                       help="Analysis action to perform")
    parser.add_argument("--reset", action="store_true",
                       help="Reset learning data (use with caution)")
    
    args = parser.parse_args()
    
    if args.reset:
        confirm = input("⚠️ Are you sure you want to reset all learning data? (yes/no): ").strip().lower()
        if confirm == "yes":
            try:
                metrics_file = Path("knowledge/learning/learning_metrics.json")
                outcomes_file = Path("knowledge/learning/task_outcomes.json")
                
                # Backup files first
                if metrics_file.exists():
                    metrics_file.rename(metrics_file.with_suffix(".json.bak"))
                if outcomes_file.exists():
                    outcomes_file.rename(outcomes_file.with_suffix(".json.bak"))
                
                # Create new empty files
                Path("knowledge/learning").mkdir(parents=True, exist_ok=True)
                with open(metrics_file, 'w') as f:
                    json.dump({
                        "models": {},
                        "peers": {},
                        "categories": {},
                        "tokens": {}
                    }, f)
                
                with open(outcomes_file, 'w') as f:
                    json.dump([], f)
                
                console.print("✅ Learning data has been reset", style="green")
            except Exception as e:
                console.print(f"❌ Error resetting learning data: {e}", style="red")
            
            return
        else:
            console.print("⚠️ Reset cancelled", style="yellow")
    
    metrics, outcomes = load_learning_data()
    
    if args.action == "summary":
        show_summary(metrics, outcomes)
    elif args.action == "models":
        show_models(metrics, outcomes)
    elif args.action == "categories":
        # Implement category-specific analysis if needed
        console.print("🚧 Category analysis not yet implemented", style="yellow")
    elif args.action == "peers":
        # Implement peers-specific analysis if needed
        console.print("🚧 Peer analysis not yet implemented", style="yellow")
    else:
        show_summary(metrics, outcomes)

if __name__ == "__main__":
    main()