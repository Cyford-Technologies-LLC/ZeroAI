from crewai.tools import BaseTool
from datetime import datetime


class SchedulingTool(BaseTool):
    name: str = "Schedule Event"
    description: str = "Schedules an event on a calendar. Input must be a dictionary with 'title', 'start_time', and 'end_time'."

    def _run(self, event_details: dict):
        try:
            # Example implementation (replace with actual calendar API calls)
            title = event_details.get("title")
            start = event_details.get("start_time")
            end = event_details.get("end_time")

            if not all([title, start, end]):
                return "Error: Missing required event details."

            print(f"Scheduling event '{title}' from {start} to {end}")
            # Simulate API call success
            return f"Event '{title}' successfully scheduled."
        except Exception as e:
            return f"An error occurred: {str(e)}"
