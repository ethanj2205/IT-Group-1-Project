# MediQueue SA — Symptom Triage Classification Component

This package contains the symptom-text urgency classifier and the PHP integration used by MediQueue SA.

## Component overview

The service accepts a patient's symptom description and returns one of three urgency levels:

- Low
- Moderate
- High

It runs independently on port `5002` and uses TF-IDF text features with a Logistic Regression classifier. The current development dataset contains **1,200 synthetic symptom descriptions**, balanced across the three urgency levels (400 per class).

The component is designed for triage prioritisation. It does not provide a diagnosis and requires clinical validation before any real-world healthcare deployment.

## Package contents

### `triage-service/`

- `app.py` — Flask API exposing `POST /predict_urgency`
- `triage_training_data.csv` — 1,200 labelled development examples
- `build_training_dataset.py` — reproducibly builds the development dataset
- `train_model.py` — trains and saves the classifier
- `triage_model.joblib` — trained classifier pipeline
- `metrics.json` — grouped-holdout evaluation metrics
- `validation.py` — input validation and nonsensical-text rejection
- `safety_rules.py` — conservative red-flag checks for critical symptom wording
- `test_model.py` — classifier regression checks
- `test_validation.py` — input-validation checks
- `test_service.py` — endpoint behaviour checks
- `stress_test.py` — mixed valid/invalid presentation-style cases
- `requirements.txt` — Python dependencies
- `run.txt` — Windows run instructions

### `integration/`

- `api.php` — PHP API version with the classifier-service integration
- `api_original.php` — original supplied API copy
- `INTEGRATION_NOTES.md` — integration summary

## API contract

### Request

`POST http://localhost:5002/predict_urgency`

```json
{
  "symptoms": "high fever for 2 days"
}
```

### Standard response

```json
{
  "urgency": "Moderate",
  "confidence": 0.9023,
  "needs_review": false,
  "safety_override": false,
  "disclaimer": "For triage support only; this result is not a medical diagnosis."
}
```

### Invalid or unrelated input

The service rejects requests that do not resemble a meaningful symptom description instead of forcing them into an urgency category.

```json
{
  "error": "The symptom description could not be recognised. Please describe a physical or mental health symptom...",
  "code": "invalid_symptom_description"
}
```

HTTP status: `422`.

### Low-confidence input

If the text appears medically relevant but the classifier confidence is below the configured threshold, the service conservatively returns `Moderate` and marks the result for review.

```json
{
  "urgency": "Moderate",
  "confidence": 0.41,
  "needs_review": true,
  "safety_override": false
}
```

### Critical red-flag wording

A conservative safety layer checks for a small set of clearly critical phrases such as severe breathing difficulty, stroke signs, prolonged seizures, uncontrolled bleeding, or loss of consciousness. If detected, the returned urgency is kept at `High` and marked for review even when the statistical classifier is uncertain.

## Run on Windows

Open PowerShell in `triage-service`:

```powershell
py -m venv .venv
.\.venv\Scripts\Activate.ps1
py -m pip install -r requirements.txt
py train_model.py
py test_model.py
py test_validation.py
py test_service.py
py app.py
```

The service runs at:

```text
http://localhost:5002
```

Health check:

```powershell
curl.exe "http://localhost:5002/health"
```

## PHP integration behaviour

The PHP backend sends symptom text to `http://localhost:5002/predict_urgency`.

- A valid classification is stored in `triage_records.urgency`.
- Explicitly invalid/unrelated text is returned as a validation error and is not saved as a triage record.
- If the service is unavailable, the existing keyword-based logic remains available as a fallback.
- The wait-time service on port `5001` is unchanged.

## Model and evaluation summary

The dataset contains 1,200 synthetic examples: 400 Low, 400 Moderate and 400 High. Each urgency class contains multiple symptom scenarios and varied wording, including shorter descriptions, longer descriptions and selected spelling mistakes.

Evaluation uses a **scenario-grouped holdout** rather than allowing paraphrases of the same symptom scenario to appear in both training and testing. The saved `metrics.json` file records the model-only result on 300 examples from 30 held-out symptom scenarios. This is a stricter development check than a random row split and is intended to expose weaknesses in generalisation.

The service also adds input validation, low-confidence escalation and conservative critical red-flag handling around the classifier. These controls are intended to reduce false confidence and unsafe down-prioritisation during the prototype demonstration.

The dataset is synthetic. Results from this development dataset must not be described as clinical validation or as evidence of real-world medical accuracy.

## Current safeguards

- request-format validation;
- rejection of clearly unrelated or nonsensical text;
- rejection of very vague inputs that need more detail;
- maximum input length;
- low-confidence escalation to Moderate with `needs_review: true`;
- conservative High-urgency red-flag handling;
- controlled server errors;
- backend fallback if the classifier service is unavailable.

For a real healthcare deployment, further work would include clinician-reviewed data, multilingual evaluation, formal clinical validation, security assessment, audit logging, model monitoring and governance controls.
