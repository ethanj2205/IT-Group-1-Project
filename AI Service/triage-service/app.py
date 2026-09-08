"""MediQueue SA symptom urgency classification service."""

from pathlib import Path
import logging
import joblib
from flask import Flask, jsonify, request

BASE_DIR = Path(__file__).resolve().parent
MODEL_PATH = BASE_DIR / "triage_model.joblib"
MIN_CONFIDENCE = 0.45
VALID_URGENCIES = {"Low", "Moderate", "High"}

from safety_rules import critical_red_flag
from validation import validate_symptoms

app = Flask(__name__)
app.config["MAX_CONTENT_LENGTH"] = 16 * 1024
model = joblib.load(MODEL_PATH)
logger = logging.getLogger("triage_service")


@app.get("/health")
def health():
    return jsonify({
        "status": "ok",
        "service": "MediQueue SA Triage Classifier",
        "model": "TF-IDF + Logistic Regression",
        "version": "1.2",
    })


@app.post("/predict_urgency")
def predict_urgency():
    data = request.get_json(silent=True)
    if not isinstance(data, dict):
        return jsonify({"error": "Request body must be JSON", "code": "invalid_json"}), 400

    symptoms = str(data.get("symptoms", "")).strip()
    valid, message = validate_symptoms(symptoms)
    if not valid:
        return jsonify({
            "error": message,
            "code": "invalid_symptom_description",
        }), 422

    try:
        predicted = str(model.predict([symptoms])[0])
        probabilities = model.predict_proba([symptoms])[0]
        confidence = float(max(probabilities))
    except Exception:
        logger.exception("Urgency prediction failed")
        return jsonify({"error": "Prediction service error", "code": "prediction_error"}), 500

    if predicted not in VALID_URGENCIES:
        return jsonify({"error": "Invalid urgency result", "code": "invalid_model_output"}), 500

    safety_override = critical_red_flag(symptoms)
    needs_review = False

    if safety_override:
        urgency = "High"
        needs_review = True
    elif confidence < MIN_CONFIDENCE:
        urgency = "Moderate"
        needs_review = True
    else:
        urgency = predicted

    return jsonify({
        "urgency": urgency,
        "confidence": round(confidence, 4),
        "needs_review": needs_review,
        "safety_override": safety_override,
        "disclaimer": "For triage support only; this result is not a medical diagnosis.",
    })


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5002, debug=False)
