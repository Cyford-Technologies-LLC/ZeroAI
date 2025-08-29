from crewai import Agent
from crewai.tools import BaseTool

class CalculatorTool(BaseTool):
    name: str = "Calculator"
    description: str = "Performs mathematical calculations. Input should be a valid mathematical expression (e.g., '5+5')."

    def _run(self, expression: str):
        try:
            return str(eval(expression))
        except Exception as e:
            return f"Error: Could not evaluate expression '{expression}'. Reason: {e}"

calculator_tool = CalculatorTool()
