Development and Maintenance Crew
This document outlines a versatile AI crew designed to work with different software projects for maintenance, bug fixes, updates, and documentation. The key to its versatility is a hierarchical structure that isolates project context and delegates specific tasks to specialized sub-crews.
Architecture: Manager-Specialist Model
The crew's architecture is a hierarchical system where a central manager agent orchestrates the workflow by delegating tasks to specialized sub-crews.
1. Manager Crew
This is the top-level crew that receives project requests and delegates tasks.
Manager Agent (Orchestrator)
Role: Project Manager
Goal: Receive a project request (e.g., "fix bug #123 in project_repo") and break it down into actionable steps.
Tasks:
plan_project: Given a bug report or feature request, create a sequential plan of tasks.
delegate_task: Assign a specific step to the correct specialist crew, providing all necessary context.
Crew Process: Process.hierarchical
2. Code-Fixer Crew
This crew focuses on writing, testing, and applying code changes.
Agents:
Researcher: Reads and analyzes code, bug reports, and project context.
Coder: Writes and applies code changes.
Tester: Creates and runs tests to verify the fix.
Tasks:
analyze_codebase: Identify relevant files based on a bug report and repository path.
fix_bug: Write and apply a code change for a specific file.
write_tests: Create a test to verify the bug fix.
run_tests: Execute tests and report the results.
Crew Process: Process.sequential
3. Documentation Crew
This crew is responsible for updating and generating documentation.
Agent:
Writer: Generates or updates documentation based on provided information.
Tasks:
write_readme_doc: Given a repository and bug fix, update the README.md file.
create_issue_doc: Write a new GitHub issue based on a bug report.
Crew Process: Process.sequential
4. Repo Management Crew
This crew handles all Git and repository operations.
Agent:
Git Operator: Executes all Git commands.
Tools:
GitTool (Custom): Wraps Git commands for cloning, committing, and pushing.
Tasks:
clone_repo: Clone a specific repository to a local directory.
commit_and_push: Commit changes with a message and push to a specified branch and repository.
Crew Process: Process.sequential
Custom Tools
To enable the crew to interact with the file system and Git, you will need to create custom tools.
GitTool (Manages repository interactions)
_clone_repo: Runs git clone <repo_url>.
_commit_and_push: Runs git commit -m "<message>" and git push.
_checkout_branch: Runs git checkout -b <branch_name>.
FileTool (Manages file system interactions)
_read_file: Reads the content of a file at a given path.
_write_file: Writes content to a file at a given path.
Isolating Projects
To prevent mixing up different projects, the crew uses explicit context passing and isolated working directories.
Isolate project data: Each project should have its own temporary working directory (e.g., /tmp/project_name_issue_id).
Pass context via inputs: All necessary information (repository URL, bug ID, local path) is passed explicitly as task inputs. Agents do not remember global state.
Example Task Flow
Request: A user initiates a request: fix bug #123 in project "my_app" at repo "https://github.com/user/my_app.git".
Manager's Task: plan_project(bug_id='123', project_name='my_app', repo_url='<repo_url>').
Manager Delegates:
Repo Management Crew: clone_repo(repo_url='<repo_url>', project_path='/tmp/my_app_123').
Code-Fixer Crew: fix_bug(bug_id='123', project_path='/tmp/my_app_123').
Documentation Crew: write_readme_doc(project_path='/tmp/my_app_123').
Repo Management Crew: commit_and_push(project_path='/tmp/my_app_123', commit_msg='Fix for bug #123').
Cleanup: A final task can be added to delete the temporary project directory, ensuring no residual files interfere with future projects.