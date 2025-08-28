# download models
for model in llama3.2:latest mixtral-8x7b-instruct-v0.1 gemma2:2b codellama:7b llava:7b; do
  ollama pull $model
done

#   this is how it should go...
#   1   connect t the strongest  server,  gpu,memory, cpu    in that order..
#   if it falls back to local,   its should try an use a model
#   that is good for that cpu..
#   if all else fails..   it should use   3.2:1b
#
# docker exec -it zeroai_api-prod bash
#   sites:
#   https://www.cyfordtechnologies.com/zeroai.php
#   https://www.cyfordtechnologies.com/allens_ai.php
#   https://github.com/Cyford-Technologies-LLC/ZeroAI/



# Add peers
python3 run/internal/peer_manager.py  --ip 0.0.0.0 --name GPU-01 --model codellama:13b add
python3 run/internal/peer_manager.py    list

#cli  direct command
clear ; python3 run/internal/simple_chat.py
clear ; python3 run/internal
/api_crew.py
clear ; python3 run/internal/smart_ai_demo.py
clear ; python3 run/internal/code_generator.py
clear ; python3 run/internal/basic_crew.py
clear ; python3 run/examples/advanced_analysis.py


# curl  api  linux endpoint test ..   in or outside containers
 curl -X POST "http://localhost:3939/run_crew_ai/"   -H "Content-Type: application/json"   -d '{ "inputs": { "topic": "what is your name", "context": "general", "focus": "standard" } }'


#windows curl test outside containers
$body = @{ inputs = @{ topic = "what is your name"; context = "general"; focus = "standard" } } | ConvertTo-Json
Invoke-RestMethod -Method Post -Uri "http://localhost:3939/run_crew_ai/" -ContentType "application/json" -Body $body

