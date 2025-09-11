# /opt/ZeroAI/src/learning/__init__.py

from .feedback_loop import FeedbackLoop, TaskOutcome

# Initialize global feedback loop instance
feedback_loop = FeedbackLoop()

def record_task_result(task_id, prompt, category, model_used, peer_used, 
                      start_time, end_time, success, error_message=None,
                      git_changes=None, token_usage=None):
    """Helper function to record task results"""
    outcome = TaskOutcome(
        task_id=task_id,
        prompt=prompt,
        category=category,
        model_used=model_used,
        peer_used=peer_used,
        start_time=start_time,
        end_time=end_time,
        success=success,
        error_message=error_message,
        git_changes=git_changes,
        token_usage=token_usage
    )
    
    return feedback_loop.record_task_outcome(outcome)