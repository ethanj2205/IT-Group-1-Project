"""MediQueue SA - Symptom Triage Classification Service.

Student-project prototype only. The model is trained on synthetic data and is
not clinically validated. It must not be used as a real medical diagnosis or
emergency decision system.

Run:
    py app.py

Listens on:
    http://localhost:5002

Endpoint:
    POST /predict_urgency
    {"symptoms": "high fever for 2 days"}

Response:
    {"urgency": "Moderate", "confidence": 0.91}
"""

from pathlib import Path
import joblib
from flask import Flask, jsonify, request

BASE_DIR = Path(__file__).resolve().parent
MODEL_PATH = BASE_DIR / "triage_model.joblib"

app = Flask(__name__)
model = joblib.load(MODEL_PATH)
VALID_URGENCIES = {"Low", "Moderate", "High"}


@app.get("/health")
def health():
    return jsonify({
        "status": "ok",
        "service": "MediQueue SA Triage Classifier",
        "model": "TF-IDF + Logistic Regression",
        "training_data": "synthetic",
    })


@app.post("/predict_urgency")
def predict_urgency():
    data = request.get_json(silent=True)
    if not isinstance(data, dict):
        return jsonify({"error": "Request body must be JSON"}), 400

    symptoms = str(data.get("symptoms", "")).strip()
    if not symptoms:
        return jsonify({"error": "symptoms is required"}), 422

    try:
        urgency = str(model.predict([symptoms])[0])
        probabilities = model.predict_proba([symptoms])[0]
        confidence = float(max(probabilities))
    except Exception as exc:
        return jsonify({"error": f"Prediction failed: {exc}"}), 500

    if urgency not in VALID_URGENCIES:
        return jsonify({"error": "Model returned an invalid urgency label"}), 500

    return jsonify({
        "urgency": urgency,
        "confidence": round(confidence, 4),
        "disclaimer": "Student prototype using synthetic data; not a medical diagnosis.",
    })


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5002, debug=False)
