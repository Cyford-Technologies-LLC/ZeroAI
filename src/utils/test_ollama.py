import ollama

try:
    ollama_client = ollama.Client(host='http://149.36.1.65:11434')
    response = ollama_client.embeddings(
        model='mxbai-embed-large',
        prompt='test'
    )
    print("Ollama client test successful.")
    print(response)
except Exception as e:
    print(f"Ollama client test failed: {e}")
