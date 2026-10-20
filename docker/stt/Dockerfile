# STT service: local Persian speech-to-text (faster-whisper, CPU int8).
# The large-v3-turbo model (~1.6 GB) is downloaded at build time so the
# container starts ready. Override with --build-arg STT_MODEL=<name-or-local-dir>.
FROM python:3.12-slim

ARG STT_MODEL=large-v3-turbo
ARG DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y --no-install-recommends \
        curl ca-certificates \
    && rm -rf /var/lib/apt/lists/*

RUN pip install --no-cache-dir "faster-whisper==1.2.1" "av==12.3.0"

WORKDIR /srv/stt
COPY server.py /srv/stt/server.py

ENV HF_HUB_CACHE=/srv/stt/cache \
    STT_MODEL=${STT_MODEL} \
    STT_HOST=0.0.0.0 \
    STT_PORT=11437

# Bake the model into the image (needs network at build time).
RUN python -c "from faster_whisper import WhisperModel; WhisperModel('${STT_MODEL}', device='cpu', compute_type='int8'); print('model ready')" \
    && chown -R nobody:nogroup /srv/stt

EXPOSE 11437

USER nobody

CMD ["python", "/srv/stt/server.py"]
