#!/bin/sh
# Ollama sidecar: ensure the embeddings model is present, then serve.
set -e
MODEL="${OLLAMA_EMBED_MODEL:-nomic-embed-text}"

# Start the server in the background, wait for readiness, pull the model.
ollama serve &
SERVER_PID=$!

echo "[ollama] waiting for server..."
for i in $(seq 1 60); do
  if curl -sf http://127.0.0.1:11434/api/tags >/dev/null 2>&1; then
    break
  fi
  sleep 2
done

if ollama list 2>/dev/null | grep -qi "$MODEL"; then
  echo "[ollama] model already present: $MODEL"
else
  echo "[ollama] pulling model: $MODEL (one-time download)"
  ollama pull "$MODEL"
fi

wait $SERVER_PID
