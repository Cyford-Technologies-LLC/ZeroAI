"""
ZeroAI GPU Bridge API

Deploy this on your Prime Intellect instance to bridge ZeroAI with GPU.
This is NOT the main ZeroAI API - it's just a GPU processing bridge.
"""

import os
import sys
from pathlib import Path
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
import uvicorn

# Add ZeroAI src to path (assuming ZeroAI repo is cloned on GPU instance)
sys.path.insert(0, str(Path(__file__).parent.parent.parent / "src"))

app = FastAPI(title="ZeroAI GPU Bridge", version="1.0.0")

class ProcessRequest(BaseModel):
    task: str
    model: str = "llama3.1:8b"
    temperature: float = 0.7
    max_tokens: int = 4096

class ProcessResponse(BaseModel):
    result: str
    processing_time: float

@app.post("/process", response_model=ProcessResponse)
async def process_task(request: ProcessRequest):
    """Process task using GPU Ollama instance."""
    import time
    import requests
    
    start_time = time.time()
    
    try:
        # Call local Ollama on GPU instance
        ollama_response = requests.post(
            "http://ollama:11434/api/generate",
            json={
                "model": request.model,
                "prompt": request.task,
                "stream": False,
                "options": {
                    "temperature": request.temperature,
                    "num_predict": request.max_tokens
                }
            },
            timeout=300
        )
        
        if ollama_response.status_code == 200:
            result = ollama_response.json()["response"]
            processing_time = time.time() - start_time
            
            return ProcessResponse(
                result=result,
                processing_time=processing_time
            )
        else:
            raise HTTPException(status_code=500, detail="Ollama processing failed")
            
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"GPU processing error: {str(e)}")

@app.get("/health")
async def health_check():
    """Check if GPU bridge is healthy."""
    try:
        import requests
        response = requests.get("http://ollama:11434/api/tags", timeout=5)
        ollama_healthy = response.status_code == 200
        
        return {
            "status": "healthy" if ollama_healthy else "unhealthy",
            "ollama_available": ollama_healthy,
            "gpu_bridge": "ZeroAI GPU Bridge v1.0.0"
        }
    except:
        return {"status": "unhealthy", "ollama_available": False}

@app.get("/")
async def root():
    """GPU bridge info."""
    return {
        "service": "ZeroAI GPU Bridge",
        "purpose": "Bridge ZeroAI to Prime Intellect GPU",
        "endpoints": ["/process", "/health"],
        "note": "This is NOT the main ZeroAI API"
    }

if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=8000)