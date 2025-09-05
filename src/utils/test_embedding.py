import sys
import logging
from chromadb.utils.embedding_functions import OllamaEmbeddingFunction

# Set up logging for httpx and httpcore
logging.basicConfig(stream=sys.stdout, level=logging.DEBUG)
logging.getLogger("httpx").setLevel(logging.DEBUG)
logging.getLogger("httpcore").setLevel(logging.DEBUG)

def test_ollama_embedding():
    """
    Test the OllamaEmbeddingFunction directly.
    """
    try:
        ollama_ef = OllamaEmbeddingFunction(
            model_name="nomic-embed-text",
            url="http://149.36.1.65:11434/api/embeddings"
        )
        test_texts = ["test document one", "test document two"]
        print("Starting embedding test...")
        embeddings = ollama_ef(test_texts)
        print("Embedding test successful!")
        print(f"Received embeddings of size: {len(embeddings)}")
    except Exception as e:
        print(f"Embedding test failed with error: {e}")

if __name__ == "__main__":
    test_ollama_embedding()
