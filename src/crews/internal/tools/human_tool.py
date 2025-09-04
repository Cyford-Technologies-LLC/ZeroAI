# src/crews/internal/tools/human_tool.py
from crewai_tools import BaseTool
from pydantic import BaseModel, Field

class HumanInputToolSchema(BaseModel):
    message: str = Field(description="The message to send to the user.")

class HumanInputTool(BaseTool):
    name: str = "Human Input Tool"
    description = "Sends a message to the user and waits for input."
    args_schema: type[BaseModel] = HumanInputToolSchema

    def _run(self, message: str) -> str:
        # This is a basic example; in a real app, you'd
        # send this to a user interface and get a response.
        print(f"\n--- AI Agent needs human input ---")
        print(f"Message from agent: {message}")
        user_input = input("Your response: ")
        print("----------------------------------\n")
        return user_input

human_input_tool = HumanInputTool()
