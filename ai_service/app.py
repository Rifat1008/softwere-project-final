"""Upohar gift AI: a small local web service the PHP site talks to.
   Run:  python app.py        (or double-click start_ai.bat on Windows)
   It listens on 127.0.0.1 only, so nobody outside your computer / server can reach it."""
import sys
import time

from flask import Flask, jsonify, request

import db
from recommender import Recommender

TOKEN = str(db.setting("AI_TOKEN", "upohar-local-ai"))
PORT = int(float(db.setting("AI_PORT", 5055)))

app = Flask(__name__)
rec = Recommender()


def authorized():
    return request.headers.get("X-AI-Token", "") == TOKEN


@app.get("/health")
def health():
    return jsonify({"ok": True, "ready": rec.ready})


@app.get("/status")
def status():
    if not authorized():
        return jsonify({"error": "forbidden"}), 403
    return jsonify(rec.status())


@app.post("/suggest")
def suggest():
    if not authorized():
        return jsonify({"error": "forbidden"}), 403
    body = request.get_json(silent=True) or {}
    msgs = body.get("messages") or []
    texts = [str(m.get("text", ""))[:500] for m in msgs[-8:] if isinstance(m, dict) and m.get("role", "user") == "user"]
    if not any(t.strip() for t in texts):
        return jsonify({"error": "Write something first."}), 400
    shown = [int(i) for i in (body.get("shown") or [])[-60:] if str(i).isdigit()]
    try:
        return jsonify(rec.suggest(texts, shown, body.get("limit", 3)))
    except Exception as exc:                       # never leak a stack trace to the browser
        app.logger.exception("suggest failed")
        return jsonify({"error": "The AI had a problem: " + str(exc)[:120]}), 500


@app.post("/retrain")
def retrain():
    if not authorized():
        return jsonify({"error": "forbidden"}), 403
    try:
        return jsonify({"ok": True, "model": rec.retrain(), "status": rec.status()})
    except Exception as exc:
        app.logger.exception("retrain failed")
        return jsonify({"error": str(exc)[:200]}), 500


if __name__ == "__main__":
    print("Upohar gift AI: loading the catalog and the model ...")
    try:
        t0 = time.time()
        rec.ensure_ready()
    except Exception as exc:
        print("\nCould not start: " + str(exc))
        print("Check that MySQL is running, that the database is imported (upohar_admin.sql) and the login in config/config.php is right.")
        sys.exit(1)
    st = rec.status()
    print(f"Ready in {time.time() - t0:.1f}s: {st['products']} products, model trained {st['model'].get('trained_at')} (f1 {st['model'].get('f1')}).")
    print(f"Listening on http://127.0.0.1:{PORT}  - keep this window open while the website is used.")
    app.run(host="127.0.0.1", port=PORT, threaded=True)
