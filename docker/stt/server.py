#!/usr/bin/env python3
"""Persistent Persian STT server: loads faster-whisper once, serves many requests.

POST /transcribe  {"path": "/abs/file.wav", "lang": "fa"}
  -> {"ok": true, "text": "...", "detail": "...", "duration": 5.3}
GET  /health      -> {"ok": true, "model": ...}   (also accepts POST)

Env: STT_MODEL (name or local dir, default: large-v3-turbo),
     STT_HOST (default 127.0.0.1), STT_PORT (default 11437).
"""
import json
import os
import threading
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

MODEL_NAME = os.environ.get("STT_MODEL", "large-v3-turbo")
HOST = os.environ.get("STT_HOST", "127.0.0.1")
PORT = int(os.environ.get("STT_PORT", "11437"))

_model = None
_lock = threading.Lock()


def get_model():
    global _model
    if _model is None:
        with _lock:
            if _model is None:
                from faster_whisper import WhisperModel
                _model = WhisperModel(MODEL_NAME, device="cpu", compute_type="int8")
    return _model


class Handler(BaseHTTPRequestHandler):
    def log_message(self, *a):
        pass

    def _json(self, obj, code=200):
        body = json.dumps(obj, ensure_ascii=False).encode("utf-8")
        self.send_response(code)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def do_GET(self):
        if self.path.rstrip("/") == "/health":
            return self._json({"ok": True, "model": MODEL_NAME})
        return self._json({"ok": False, "error": "unknown endpoint"}, 404)

    def do_POST(self):
        try:
            length = int(self.headers.get("Content-Length", 0))
            data = json.loads(self.rfile.read(length) or b"{}")
        except Exception:
            return self._json({"ok": False, "error": "bad request"}, 400)
        if self.path.rstrip("/") == "/health":
            return self._json({"ok": True, "model": MODEL_NAME})
        if self.path != "/transcribe":
            return self._json({"ok": False, "error": "unknown endpoint"}, 404)
        path = data.get("path", "")
        lang = data.get("lang", "fa") or None
        if not path or not os.path.isfile(path):
            return self._json({"ok": False, "error": "file not found"})
        try:
            model = get_model()
            segments, info = model.transcribe(path, language=lang, beam_size=1, vad_filter=True)
            text = "".join(s.text for s in segments).strip()
            if not text:
                return self._json({"ok": False, "error": "no speech detected"})
            return self._json({"ok": True, "text": text, "language": info.language,
                               "duration": round(info.duration, 1)})
        except Exception as e:  # noqa: BLE001
            return self._json({"ok": False, "error": str(e)[:300]})


if __name__ == "__main__":
    print(f"loading {MODEL_NAME} ...", flush=True)
    get_model()
    print(f"STT ready on {HOST}:{PORT}", flush=True)
    ThreadingHTTPServer((HOST, PORT), Handler).serve_forever()
