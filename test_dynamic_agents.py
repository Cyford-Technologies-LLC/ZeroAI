#!/usr/bin/env python3
"""
Test script to verify agents are using database settings instead of static files
"""
import sys
import os
sys.path.append('/app')

from src.utils.dynamic_agent_loader import dynamic_loader
from src.distributed_router import DistributedRouter
from src.config import config

def test_dynamic_agents():
    print("🧪 Testing Dynamic Agent Loading...")
    
    # Initialize router
    router = DistributedRouter()
    
    # Get available roles from database
    roles = dynamic_loader.get_available_roles()
    print(f"📋 Available agent roles in database: {roles}")
    
    if not roles:
        print("❌ No agents found in database. Run import first!")
        return
    
    # Test creating an agent from database
    test_role = roles[0] if roles else "Project Manager"
    print(f"\n🔧 Testing agent creation for role: {test_role}")
    
    # Get config from database
    config_data = dynamic_loader.get_agent_config(role=test_role)
    if config_data:
        print(f"✅ Found database config for {test_role}:")
        print(f"   - Memory: {config_data.get('memory', 'Not set')}")
        print(f"   - Learning: {config_data.get('learning_enabled', 'Not set')}")
        print(f"   - Allow Delegation: {config_data.get('allow_delegation', 'Not set')}")
        print(f"   - Verbose: {config_data.get('verbose', 'Not set')}")
        print(f"   - Max Iterations: {config_data.get('max_iter', 'Not set')}")
        print(f"   - Personality Traits: {config_data.get('personality_traits', 'Not set')}")
        print(f"   - Communication Style: {config_data.get('communication_formality', 'Not set')}")
        
        # Try to create the agent
        try:
            agent = dynamic_loader.create_agent_from_config(config_data, router)
            print(f"✅ Successfully created dynamic agent: {agent.role}")
            print(f"   - Agent verbose setting: {agent.verbose}")
            print(f"   - Agent allow_delegation: {agent.allow_delegation}")
            print(f"   - Agent max_iter: {agent.max_iter}")
            return True
        except Exception as e:
            print(f"❌ Failed to create agent: {e}")
            return False
    else:
        print(f"❌ No database config found for {test_role}")
        return False

def test_crew_integration():
    print("\n🚀 Testing Crew Integration...")
    
    try:
        from src.crews.internal.developer.crew import create_developer_crew
        from src.distributed_router import DistributedRouter
        
        router = DistributedRouter()
        inputs = {"project_name": "test", "working_dir": "/tmp"}
        
        print("Creating developer crew...")
        crew = create_developer_crew(router, inputs)
        
        print(f"✅ Developer crew created with {len(crew.agents)} agents:")
        for agent in crew.agents:
            print(f"   - {agent.role} (verbose: {agent.verbose}, delegation: {agent.allow_delegation})")
        
        return True
    except Exception as e:
        print(f"❌ Failed to create crew: {e}")
        return False

if __name__ == "__main__":
    print("=" * 60)
    print("🔍 ZeroAI Dynamic Agent Configuration Test")
    print("=" * 60)
    
    success1 = test_dynamic_agents()
    success2 = test_crew_integration()
    
    print("\n" + "=" * 60)
    if success1 and success2:
        print("✅ ALL TESTS PASSED - Agents are using database settings!")
    else:
        print("❌ SOME TESTS FAILED - Check configuration")
    print("=" * 60)