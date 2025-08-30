# /opt/ZeroAI/run/internal/run_dev_ops.py (partial modification)

# Add these imports
import time
from learning import record_task_result

# Inside your main function, add timing:
start_time = time.time()

# Before executing the crew:
task_id = args.task_id or str(int(time.time()))  # Use timestamp as task ID if none provided

# After the execution, before returning:
end_time = time.time()

# Gather information about what model and peer were used
model_used = getattr(manager, 'model_used', 'unknown')
peer_used = getattr(manager, 'peer_used', 'unknown')

# Record the task result
record_task_result(
    task_id=task_id,
    prompt=args.prompt,
    category=args.category,
    model_used=model_used,
    peer_used=peer_used,
    start_time=start_time,
    end_time=end_time,
    success=(result is not None),
    error_message=str(e) if not (result is not None) else None,
    git_changes=getattr(result, 'git_changes', None),
    token_usage=getattr(result, 'token_usage', None)
)