from datetime import datetime
import uuid
from .crew_logger import crew_logger

class CrewExecutionWrapper:
    """Wrapper to log crew conversations during execution"""
    
    def __init__(self, crew, task_id=None):
        self.crew = crew
        self.task_id = task_id or str(uuid.uuid4())[:8]
        self.start_time = datetime.now()
    
    def execute_with_logging(self, inputs):
        """Execute crew with conversation logging"""
        try:
            # Log task start
            crew_logger.log_conversation(
                task_id=self.task_id,
                agent_role="SYSTEM",
                prompt=f"Task started: {inputs}",
                response="Task execution initiated",
                timestamp=self.start_time.isoformat()
            )
            
            # Execute the crew
            result = self.crew.kickoff(inputs=inputs)
            
            # Log task completion
            crew_logger.log_conversation(
                task_id=self.task_id,
                agent_role="SYSTEM", 
                prompt="Task completion",
                response=str(result)[:500] + "..." if len(str(result)) > 500 else str(result),
                timestamp=datetime.now().isoformat()
            )
            
            return result
            
        except Exception as e:
            # Log errors
            crew_logger.log_conversation(
                task_id=self.task_id,
                agent_role="SYSTEM",
                prompt="Task error",
                response=f"Error: {str(e)}",
                timestamp=datetime.now().isoformat()
            )
            raise

def log_agent_interaction(task_id, agent_role, prompt, response):
    """Helper function to log individual agent interactions"""
    crew_logger.log_conversation(
        task_id=task_id,
        agent_role=agent_role,
        prompt=prompt,
        response=response
    )