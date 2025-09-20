#!/bin/bash
echo "Downloading missing SadTalker checkpoint files directly..."

# Download epoch_20.pth (the critical missing file)
docker exec zeroai_avatar wget -O /app/SadTalker/checkpoints/epoch_20.pth https://huggingface.co/vinthony/SadTalker/resolve/main/checkpoints/epoch_20.pth

# Download BFM_Fitting.pth
docker exec zeroai_avatar wget -O /app/SadTalker/checkpoints/BFM_Fitting.pth https://huggingface.co/vinthony/SadTalker/resolve/main/checkpoints/BFM_Fitting.pth

# Download hub_module.pth  
docker exec zeroai_avatar wget -O /app/SadTalker/checkpoints/hub_module.pth https://huggingface.co/vinthony/SadTalker/resolve/main/checkpoints/hub_module.pth

echo "Verifying downloads..."
docker exec zeroai_avatar ls -lh /app/SadTalker/checkpoints/epoch_20.pth
docker exec zeroai_avatar ls -lh /app/SadTalker/checkpoints/BFM_Fitting.pth
docker exec zeroai_avatar ls -lh /app/SadTalker/checkpoints/hub_module.pth