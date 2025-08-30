# project_overview.yaml
project:
name: "ZeroAI"
description: "A secure, automated development and maintenance workflow using CrewAI"

context:
architecture: "Distributed"
key_components:
- name: "DistributedRouter"
description: "Dynamically assigns tasks to available LLMs, optimizing performance"
- name: "Modular Crews"
description: "Different crews defined for specific categories (customer_service, coding, math, tech_support)"
- name: "Two-Tier System"
description: "Clear distinction between public-facing crews and secure internal crews"

problem_statement:
issue: "Security risks from routing system-modifying tasks through public-facing API"
solution: "Robust, two-tiered system design"

accomplishments:
- name: "Secure Separation"
  description: "Successfully implemented secure separation between public-facing and internal development tasks"
- name: "Hierarchical Design"
  description: "New dev ops crew utilizes Process.hierarchical design for delegation to specialized sub-crews"
- name: "Modular Architecture"
  description: "Crew definitions are modular and encapsulated for easy extension and maintenance"
- name: "Clear Workflow"
  description: "Well-defined workflow for internal tasks with robust error handling and logging"