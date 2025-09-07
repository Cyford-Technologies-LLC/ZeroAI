### Learning Tool

**Purpose:** To save discovered information or resolutions to a persistent file.

*   **Schema:** `LearningToolSchema` requires two arguments:
    1.  `input_dict`: A dictionary containing the learning data.
    2.  `agent`: The name of the agent.
*   **Input:** `{"input_dict": {"content": "Your detailed findings here", "filename": "docker_setup_details.md"}, "agent": "Senior Developer"}`
*   **Guidance:** When saving learning, you **must** provide both the `content` and a `filename` within the `input_dict`. The `agent` name should correspond to your current role.
