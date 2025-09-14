<?php
class PythonCloudBridge {
    private $pythonPath = '/app/venv/bin/python';
    private $projectRoot = '/app';
    
    public function updateCloudProvider($provider, $config) {
        // Update Python config with new cloud provider settings
        $script = "
import sys
sys.path.append('/app')
sys.path.append('/app/src')

try:
    from src.config import config
    import json
    import os
    
    # Update cloud provider in config
    config.cloud.provider = '{$provider}'
    
    # Update environment variables
    env_updates = {}
";

        // Add provider-specific config
        switch ($provider) {
            case 'anthropic':
                $script .= "
    if '{$config['api_key']}':
        env_updates['ANTHROPIC_API_KEY'] = '{$config['api_key']}'
        config.cloud.anthropic_api_key = '{$config['api_key']}'
";
                break;
            case 'openai':
                $script .= "
    if '{$config['api_key']}':
        env_updates['OPENAI_API_KEY'] = '{$config['api_key']}'
        config.cloud.openai_api_key = '{$config['api_key']}'
";
                break;
        }

        $script .= "
    # Save config
    config.save_to_file()
    
    # Update .env file
    env_file = '/app/.env'
    with open(env_file, 'r') as f:
        env_content = f.read()
    
    for key, value in env_updates.items():
        if key + '=' in env_content:
            # Update existing
            import re
            env_content = re.sub(f'{key}=.*', f'{key}={value}', env_content)
        else:
            # Add new
            env_content += f'\\n{key}={value}\\n'
    
    with open(env_file, 'w') as f:
        f.write(env_content)
    
    print(json.dumps({'success': True, 'provider': '{$provider}'}))
    
except Exception as e:
    print(json.dumps({'success': False, 'error': str(e)}))
";

        $tempFile = '/tmp/update_cloud_' . time() . '.py';
        file_put_contents($tempFile, $script);
        
        $output = shell_exec("{$this->pythonPath} {$tempFile} 2>&1");
        unlink($tempFile);
        
        $result = json_decode(trim($output), true);
        return $result ?: ['success' => false, 'error' => 'Failed to parse response'];
    }
    
    public function testCloudProvider($provider) {
        $script = "
import sys
sys.path.append('/app')
sys.path.append('/app/src')

try:
    from src.providers.cloud_providers import CloudProviderManager
    from crewai import Agent, Task
    import json
    
    # Create LLM based on provider
    if '{$provider}' == 'anthropic':
        llm = CloudProviderManager.create_anthropic_llm(model='claude-3-5-sonnet-20241022')
    elif '{$provider}' == 'openai':
        llm = CloudProviderManager.create_openai_llm(model='gpt-4')
    elif '{$provider}' == 'azure':
        llm = CloudProviderManager.create_azure_llm()
    elif '{$provider}' == 'google':
        llm = CloudProviderManager.create_google_llm()
    else:
        raise Exception(f'Unknown provider: {$provider}')
    
    # Test with simple agent
    agent = Agent(
        role='Test Agent',
        goal='Test cloud connection',
        backstory='Testing cloud provider integration',
        llm=llm,
        verbose=False
    )
    
    task = Task(
        description='Respond with: Cloud connection successful for {$provider}',
        agent=agent,
        expected_output='Simple confirmation message'
    )
    
    result = task.execute()
    
    print(json.dumps({
        'success': True, 
        'provider': '{$provider}',
        'response': str(result),
        'model': llm.model if hasattr(llm, 'model') else 'unknown'
    }))
    
except Exception as e:
    print(json.dumps({'success': False, 'error': str(e)}))
";

        $tempFile = '/tmp/test_cloud_' . time() . '.py';
        file_put_contents($tempFile, $script);
        
        $output = shell_exec("{$this->pythonPath} {$tempFile} 2>&1");
        unlink($tempFile);
        
        $result = json_decode(trim($output), true);
        return $result ?: ['success' => false, 'error' => 'Failed to parse response'];
    }
    
    public function chatWithCloudAgent($provider, $message, $context = []) {
        $contextStr = json_encode($context);
        
        $script = "
import sys
sys.path.append('/app')
sys.path.append('/app/src')

try:
    from src.providers.cloud_providers import CloudProviderManager
    from crewai import Agent, Task
    import json
    
    # Create LLM
    if '{$provider}' == 'anthropic':
        llm = CloudProviderManager.create_anthropic_llm(model='claude-3-5-sonnet-20241022')
    elif '{$provider}' == 'openai':
        llm = CloudProviderManager.create_openai_llm(model='gpt-4')
    else:
        raise Exception(f'Provider {$provider} not supported for chat')
    
    # Import ZeroAI tools
    from src.tools.file_tool import FileTool
    from src.tools.git_tool import GitTool
    
    # Create tools list
    tools = [FileTool(), GitTool()]
    
    # Create specialized ZeroAI assistant agent with tools
    agent = Agent(
        role='ZeroAI Cloud Assistant',
        goal='Help optimize and manage ZeroAI system using cloud AI capabilities and tools',
        backstory='''You are a cloud-powered AI assistant integrated into the ZeroAI system. 
        You help users optimize their AI workforce, analyze agent performance, and provide 
        advanced insights using cloud AI capabilities. You have access to file operations 
        and git tools to help manage the ZeroAI codebase. You understand ZeroAI architecture,
        CrewAI framework, and can provide code suggestions and system optimizations.''',
        llm=llm,
        tools=tools,
        verbose=False,
        allow_delegation=False
    )
    
    # Add context to message
    context_data = json.loads('{$contextStr}')
    full_message = '''
Context: {$contextStr}

User Query: {$message}

Please provide helpful, actionable advice for ZeroAI system management.
'''
    
    task = Task(
        description=full_message,
        agent=agent,
        expected_output='Detailed, actionable response with specific recommendations'
    )
    
    result = task.execute()
    
    print(json.dumps({
        'success': True,
        'response': str(result),
        'provider': '{$provider}',
        'model': llm.model if hasattr(llm, 'model') else 'unknown'
    }))
    
except Exception as e:
    print(json.dumps({'success': False, 'error': str(e)}))
";

        $tempFile = '/tmp/chat_cloud_' . time() . '.py';
        file_put_contents($tempFile, $script);
        
        $output = shell_exec("{$this->pythonPath} {$tempFile} 2>&1");
        unlink($tempFile);
        
        $result = json_decode(trim($output), true);
        return $result ?: ['success' => false, 'error' => 'Failed to parse response'];
    }
    
    public function getCurrentCloudConfig() {
        $script = "
import sys
sys.path.append('/app')
sys.path.append('/app/src')

try:
    from src.config import config
    import json
    import os
    
    cloud_config = {
        'provider': config.cloud.provider,
        'has_openai_key': bool(os.getenv('OPENAI_API_KEY')),
        'has_anthropic_key': bool(os.getenv('ANTHROPIC_API_KEY')),
        'has_azure_key': bool(os.getenv('AZURE_OPENAI_API_KEY')),
        'has_google_key': bool(os.getenv('GOOGLE_API_KEY')),
        'model_config': {
            'name': config.model.name,
            'temperature': config.model.temperature,
            'max_tokens': config.model.max_tokens
        }
    }
    
    print(json.dumps(cloud_config))
    
except Exception as e:
    print(json.dumps({'error': str(e)}))
";

        $tempFile = '/tmp/get_config_' . time() . '.py';
        file_put_contents($tempFile, $script);
        
        $output = shell_exec("{$this->pythonPath} {$tempFile} 2>&1");
        unlink($tempFile);
        
        $result = json_decode(trim($output), true);
        return $result ?: ['error' => 'Failed to get config'];
    }
}
?>