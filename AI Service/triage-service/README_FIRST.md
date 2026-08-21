# MediQueue SA — Kai's Triage AI Component

This package contains Kai's new project component: a **working symptom text urgency classifier** and the PHP integration needed to replace the existing keyword only triage logic.

## What was found in the supplied project ZIP

The current MediQueue SA implementation is a **web application** with:

- PHP API/backend in `Final Project/api.php`
- MySQL database defined in `Final Project/schema.sql`
- Existing Python/Flask wait-time service on **port 5001**
- Existing `POST api.php?route=triage` route that currently classifies Low/Moderate/High using PHP `strpos()` keyword matching

Kai's component fits cleanly beside the wait-time service and does **not** change the queue/wait time service.

## What this package adds

### `triage-service/`
A standalone Flask service on **port 5002**.

- `kai_triage_training_data.csv` — 360 synthetic rows, balanced 120/120/120 across Low, Moderate and High
- `gen_triage_data.py` — reproducibly creates the synthetic dataset
- `train_model.py` — trains TF-IDF + Logistic Regression
- `triage_model.joblib` — saved trained model
- `metrics.json` — held-out synthetic test metrics
- `app.py` — Flask API exposing `POST /predict_urgency`
- `test_model.py` — small smoke test
- `requirements.txt` — Python dependencies
- `run.txt` — short Windows run instructions

### `integration/api.php`
A full replacement copy of the supplied `Final Project/api.php` with only the triage integration changed.

It:
1. Sends symptom text to `http://localhost:5002/predict_urgency`.
2. Uses the returned `Low`, `Moderate`, or `High` value when inserting into `triage_records.urgency`.
3. Falls back to the original keyword rules if the Python service is unavailable.
4. Returns `triage_source` and `confidence` in the triage response so you can demonstrate whether the AI service was actually used.

## API contract

### Request

`POST http://localhost:5002/predict_urgency`

```json
{
  "symptoms": "high fever for 2 days"
}
```

### Response

```json
{
  "urgency": "Moderate",
  "confidence": 0.90,
  "disclaimer": "Student prototype using synthetic data; not a medical diagnosis."
}
```

The PHP application only needs the `urgency` field. The confidence value is included for demonstration and documentation.

## Run on Windows

Open PowerShell in `triage-service`:

```powershell
py -m venv .venv
.\.venv\Scripts\Activate.ps1
py -m pip install -r requirements.txt
py train_model.py
py app.py
```

Keep that terminal open. The service will run at:

```text
http://localhost:5002
```

Verify it:

```powershell
curl.exe "http://localhost:5002/health"
```

Direct prediction test:

```powershell
$body = @{ symptoms = "high fever for 2 days" } | ConvertTo-Json -Compress
Invoke-RestMethod `
  -Uri "http://localhost:5002/predict_urgency" `
  -Method POST `
  -ContentType "application/json" `
  -Body $body
```

Expected urgency: `Moderate`.

## Integrate with the PHP project

Back up the group's current `Final Project/api.php`, then replace it with:

```text
integration/api.php
```

The modified API keeps the existing queue and Nickson wait-time code unchanged.

Start the services in separate terminals:

1. MySQL/XAMPP database
2. Triage AI service on port 5002
3. Wait-time service on port 5001
4. PHP app/API

For the current project setup, the PHP API is typically started from the `Final Project` folder with:

```powershell
C:\x\php\php.exe -S localhost:8000
```

Adjust the PHP path if your laptop uses a different XAMPP/PHP installation.

## End-to-end test through the real `/triage` route

```powershell
$body = @{
    patient_id = 1
    symptoms = "high fever for 2 days"
} | ConvertTo-Json -Compress

$response = Invoke-RestMethod `
    -Uri "http://localhost:8000/api.php?route=triage" `
    -Method POST `
    -ContentType "application/json" `
    -Body $body

$response | ConvertTo-Json -Depth 10
```

With the AI service running, look for:

```json
{
  "urgency": "Moderate",
  "triage_source": "ai_service"
}
```

Then stop the triage-service and submit another triage request. It should still work, but return:

```json
{
  "triage_source": "keyword_fallback"
}
```

That demonstrates graceful degradation.

## Model summary for the report

The classifier uses **TF-IDF text features and Logistic Regression**. TF-IDF converts symptom descriptions into numerical features based on important words and short word/character sequences. Logistic Regression then classifies the description into one of three urgency classes: Low, Moderate, or High.

The provided training dataset contains **360 synthetic symptom descriptions**, balanced across the three classes. A stratified 75/25 train-test split is used. The generated dataset currently produces 100% accuracy on its held out synthetic test split. This score must **not** be presented as evidence of real clinical accuracy because the examples were generated from a controlled synthetic process and are easier than real patient language.

## Important limitation

This is an academic prototype and **not a clinically validated triage system**. The data is synthetic, the labels are simulated and the classifier must not be used for real world medical diagnosis or emergency decisions. The existing keyword fallback is retained for system reliability during the student demo, not as clinical validation.
