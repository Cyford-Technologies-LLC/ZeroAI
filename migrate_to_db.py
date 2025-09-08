#!/usr/bin/env python3
"""Migration script to transform ZeroAI from static configs to database-driven architecture."""

import sys
from pathlib import Path
import yaml
import json
# from rich.console import Console

# Add project root to path
project_root = Path(__file__).parent
sys.path.insert(0, str(project_root))

from src.database.models import ZeroAIDatabase

class SimpleConsole:
    def print(self, *args, **kwargs):
        print(*args)

console = SimpleConsole()

def migrate_project_configs():
    """Migrate existing YAML project configs to database."""
    console.print("🔄 [bold blue]Migrating project configurations to database...[/bold blue]")
    
    db = ZeroAIDatabase()
    knowledge_dir = project_root / "knowledge" / "internal_crew"
    
    if not knowledge_dir.exists():
        console.print("⚠️ No existing project configs found")
        return
    
    migrated_count = 0
    
    # Scan for project configs
    for config_file in knowledge_dir.rglob("project_config.yaml"):
        try:
            with open(config_file, 'r') as f:
                config = yaml.safe_load(f)
            
            project_name = config.get("project_name", config_file.parent.name)
            
            with db.get_connection() as conn:
                conn.execute("""
                    INSERT OR REPLACE INTO projects (name, description, repository, default_branch, config)
                    VALUES (?, ?, ?, ?, ?)
                """, (
                    project_name,
                    config.get("description", ""),
                    config.get("repository"),
                    config.get("default_branch", "main"),
                    json.dumps(config)
                ))
                conn.commit()
            
            migrated_count += 1
            console.print(f"✅ Migrated project: {project_name}")
            
        except Exception as e:
            console.print(f"❌ Failed to migrate {config_file}: {e}")
    
    console.print(f"🎉 Migration complete! Migrated {migrated_count} projects")

def setup_initial_data():
    """Setup initial database data."""
    console.print("🚀 [bold blue]Setting up initial database data...[/bold blue]")
    
    db = ZeroAIDatabase()
    db.create_core_agents()
    
    console.print("✅ Core agents created")
    console.print("✅ Database schema initialized")

if __name__ == "__main__":
    console.print("🔧 [bold green]ZeroAI Database Migration Tool[/bold green]")
    console.print("Transforming from static configs to database-driven architecture...")
    
    setup_initial_data()
    migrate_project_configs()
    
    console.print("\n🎯 [bold green]Migration Complete![/bold green]")
    console.print("📝 Next steps:")
    console.print("  1. Use run/internal/run_dev_ops_db.py for database-driven operations")
    console.print("  2. Start the web portal: python www/api/portal_api.py")
    console.print("  3. Access admin interface at http://localhost:333")