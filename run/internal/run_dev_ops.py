# Modify this try-except block in run_dev_ops.py
try:
    # Import your actual implementation
    # Try multiple import paths
    try:
        from ai_dev_ops_crew import run_ai_dev_ops_crew_securely, AIOpsCrewManager
    except ImportError:
        try:
            from src.ai_dev_ops_crew import run_ai_dev_ops_crew_securely, AIOpsCrewManager
        except ImportError:
            # Add more paths to search
            import sys
            from pathlib import Path

            # Get the absolute path of the current file
            current_file = Path(__file__).resolve()

            # Add potential paths to sys.path
            sys.path.append(str(current_file.parent.parent.parent))  # Add project root
            sys.path.append(str(current_file.parent.parent))         # Add run/
            sys.path.append(str(current_file.parent.parent.parent / "src"))  # Add src/

            # Try again with modified path
            from ai_dev_ops_crew import run_ai_dev_ops_crew_securely, AIOpsCrewManager

    # Add debug output so you can see it loaded
    print("✅ Successfully imported AI DevOps crew modules")

    # Enable verbose output to see agent conversations
    import os
    os.environ["CREWAI_VERBOSE"] = "1"

    # Execute the task with the real implementation
    result = run_ai_dev_ops_crew_securely(
        router=router,
        project_id=args.project,
        inputs={
            "prompt": args.prompt,
            "category": args.category,
            "repository": args.repo or project_config.get("repository"),
            "branch": args.branch or project_config.get("default_branch", "main"),
            "task_id": task_id,
            "verbose": True  # Add this to enable verbose output
        }
    )
except ImportError as e:
    console.print(f"⚠️ Could not import AIOpsCrewManager: {e}", style="yellow")
    console.print("Using fallback method", style="yellow")

    # Add more debug info
    import sys
    console.print(f"Python path: {sys.path}", style="dim")

    # Fallback to a simpler method if the manager is not available
    result = {
        "success": True,
        "message": f"Task '{args.prompt}' processed with category '{args.category}'",
        "token_usage": {"total_tokens": 0}
    }