# /opt/ZeroAI/run/internal/analyze_learning.py

import sys
import argparse
from pathlib import Path
import json
from rich.console import Console
from rich.table import Table

# Add src to path
sys.path.insert(0, str(Path(__file__).parent.parent.parent / "src"))

from learning.feedback_loop import FeedbackLoop

console = Console()

def main():
    parser = argparse.ArgumentParser(description="Analyze ZeroAI learning data")
    parser.add_argument("--action", choices=["summary", "models", "categories", "keywords"], 
                       default="summary", help="Type of analysis to perform")
    
    args = parser.parse_args()
    
    feedback_loop = FeedbackLoop()
    
    if args.action == "summary":
        show_summary(feedback_loop)
    elif args.action == "models":
        show_model_analysis(feedback_loop)
    elif args.action == "categories":
        show_category_analysis(feedback_loop)
    elif args.action == "keywords":
        show_keyword_analysis(feedback_loop)

def show_summary(feedback_loop):
    """Show overall learning system summary"""
    console.print("\n[bold blue]ZeroAI Learning System Summary[/bold blue]", style="blue")
    console.print("=" * 50)
    
    # Load metrics
    with open(feedback_loop.metrics_path, 'r') as f:
        metrics = json.load(f)
    
    # Load outcomes
    with open(feedback_loop.database_path, 'r') as f:
        outcomes = json.load(f)
    
    # Calculate summary stats
    total_tasks = len(outcomes.get("task_outcomes", []))
    successful_tasks = sum(1 for o in outcomes.get("task_outcomes", []) if o.get("success", False))
    total_tokens = sum(o.get("tokens_earned", 0) for o in outcomes.get("task_outcomes", []))
    total_models = len(metrics.get("model_metrics", {}))
    total_categories = len(metrics.get("category_metrics", {}))
    
    console.print(f"[bold green]Tasks Recorded:[/bold green] {total_tasks}")
    console.print(f"[bold green]Success Rate:[/bold green] {successful_tasks/total_tasks*100:.1f}% ({successful_tasks}/{total_tasks})")
    console.print(f"[bold green]Total Learning Tokens:[/bold green] {total_tokens}")
    console.print(f"[bold green]Models Tracked:[/bold green] {total_models}")
    console.print(f"[bold green]Categories Learned:[/bold green] {total_categories}")
    
    # Show top 3 models
    console.print("\n[bold cyan]Top Performing Models:[/bold cyan]")
    models = []
    for model, data in metrics.get("model_metrics", {}).items():
        total = data.get("success_count", 0) + data.get("failure_count", 0)
        if total > 0:
            success_rate = data.get("success_count", 0) / total
            models.append((model, success_rate, data.get("total_tokens", 0)))
    
    models.sort(key=lambda x: (x[1], x[2]), reverse=True)
    
    table = Table(show_header=True)
    table.add_column("Model", style="cyan")
    table.add_column("Success Rate", style="green")
    table.add_column("Total Tokens", style="yellow")
    
    for model, rate, tokens in models[:3]:
        table.add_row(model, f"{rate*100:.1f}%", str(tokens))
    
    console.print(table)

def show_model_analysis(feedback_loop):
    """Show detailed model performance analysis"""
    console.print("\n[bold blue]Model Performance Analysis[/bold blue]", style="blue")
    console.print("=" * 50)
    
    # Load metrics
    with open(feedback_loop.metrics_path, 'r') as f:
        metrics = json.load(f)
    
    table = Table(show_header=True)
    table.add_column("Model", style="cyan")
    table.add_column("Success", style="green")
    table.add_column("Failure", style="red")
    table.add_column("Success Rate", style="green")
    table.add_column("Tokens", style="yellow")
    table.add_column("Best Categories", style="magenta")
    
    for model, data in metrics.get("model_metrics", {}).items():
        success = data.get("success_count", 0)
        failure = data.get("failure_count", 0)
        total = success + failure
        
        if total > 0:
            rate = success / total
            
            # Find best categories for this model
            categories = []
            for cat, cat_data in data.get("categories", {}).items():
                cat_total = cat_data.get("success_count", 0) + cat_data.get("failure_count", 0)
                if cat_total > 0:
                    cat_rate = cat_data.get("success_count", 0) / cat_total
                    categories.append((cat, cat_rate))
            
            categories.sort(key=lambda x: x[1], reverse=True)
            best_cats = ", ".join([cat for cat, _ in categories[:2]])
            
            table.add_row(
                model,
                str(success),
                str(failure),
                f"{rate*100:.1f}%",
                str(data.get("total_tokens", 0)),
                best_cats
            )
    
    console.print(table)

def show_category_analysis(feedback_loop):
    """Show category performance and model preferences"""
    console.print("\n[bold blue]Category Analysis[/bold blue]", style="blue")
    console.print("=" * 50)
    
    # Load metrics
    with open(feedback_loop.metrics_path, 'r') as f:
        metrics = json.load(f)
    
    table = Table(show_header=True)
    table.add_column("Category", style="cyan")
    table.add_column("Success", style="green")
    table.add_column("Failure", style="red")
    table.add_column("Success Rate", style="green")
    table.add_column("Best Models", style="yellow")
    
    for category, data in metrics.get("category_metrics", {}).items():
        success = data.get("success_count", 0)
        failure = data.get("failure_count", 0)
        total = success + failure
        
        if total > 0:
            rate = success / total
            best_models = ", ".join(data.get("best_models", [])[:3])
            
            table.add_row(
                category,
                str(success),
                str(failure),
                f"{rate*100:.1f}%",
                best_models
            )
    
    console.print(table)

def show_keyword_analysis(feedback_loop):
    """Show keyword to category mapping analysis"""
    console.print("\n[bold blue]Keyword to Category Mapping Analysis[/bold blue]", style="blue")
    console.print("=" * 50)
    
    # Load metrics
    with open(feedback_loop.metrics_path, 'r') as f:
        metrics = json.load(f)
    
    table = Table(show_header=True)
    table.add_column("Keyword", style="cyan")
    table.add_column("Uses", style="yellow")
    table.add_column("Best Category", style="green")
    table.add_column("Confidence", style="magenta")
    
    improved_mapping = feedback_loop.get_improved_keyword_mapping()
    
    for keyword, data in metrics.get("keyword_metrics", {}).items():
        if data.get("total_uses", 0) < 3:
            continue  # Skip rarely used keywords
        
        total_uses = data.get("total_uses", 0)
        
        # Find best category
        best_category = improved_mapping.get(keyword)
        if not best_category:
            continue
        
        # Calculate confidence
        best_count = data.get("categories", {}).get(best_category, {}).get("count", 0)
        confidence = best_count / total_uses if total_uses > 0 else 0
        
        table.add_row(
            keyword,
            str(total_uses),
            best_category,
            f"{confidence*100:.1f}%"
        )
    
    console.print(table)

if __name__ == "__main__":
    main()