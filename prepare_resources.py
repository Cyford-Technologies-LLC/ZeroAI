# prepare_resources.py

import os
import psutil
import subprocess
import json
import ollama

# Mapping model names to their approximate system memory requirements in GB
# (This map needs to be kept up-to-date with your models)
MODEL_MEMORY_MAP = {
    "llama3.1:8b": 5.6,
    "llama3.2:latest": 3.0,
    "llama3.2:1b": 2.3,
    "codellama:13b": 8.0,
    "codellama:7b": 5.0,
    "gemma2:2b": 3.5,
    "llava:7b": 5.0,
}

# The host of the local Ollama instance
OLLAMA_HOST = os.environ.get("OLLAMA_HOST", "http://ollama:11434")

def check_resources_and_pull_models():
    """
    Checks system resources, pulls suitable Ollama models, and
    saves the list of models to a JSON file.
    """
    try:
        # Get available memory in GiB
        available_memory_gb = psutil.virtual_memory().available / (1024**3)
        print(f"System has {available_memory_gb:.1f} GiB available memory.")

        # Determine eligible models based on memory
        eligible_models = [
            model for model, mem_req in MODEL_MEMORY_MAP.items()
            if mem_req <= available_memory_gb
        ]

        if not eligible_models:
            print("No suitable models found based on available memory. Skipping model pull.")
            with open("pulled_models.json", "w") as f:
                json.dump([], f)
            return

        print(f"Found eligible models based on memory: {eligible_models}")

        # Pull each eligible model
        pulled_models = []
        for model in eligible_models:
            print(f"Pulling model: {model}...")
            try:
                # Use the ollama Python library to pull the model
                client = ollama.Client(host=OLLAMA_HOST)
                client.pull(model)
                pulled_models.append(model)
                print(f"Successfully pulled {model}.")
            except Exception as e:
                print(f"Failed to pull model {model}: {e}")

        # Save pulled models to a file
        with open("pulled_models.json", "w") as f:
            json.dump(pulled_models, f)
        print("Pulled models list saved to pulled_models.json.")

    except Exception as e:
        print(f"An error occurred during resource check and model pull: {e}")

if __name__ == "__main__":
    check_resources_and_pull_models()
