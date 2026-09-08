"""Regression checks for the saved urgency classifier."""

from pathlib import Path
import joblib

BASE_DIR = Path(__file__).resolve().parent
model = joblib.load(BASE_DIR / "triage_model.joblib")

TEST_CASES = [
    ("mild headache and runny nose", "Low"),
    ("high fever for 2 days", "Moderate"),
    ("chest pain and struggling to breathe", "High"),
    ("mild sinus pressure and no fever", "Low"),
    ("painful urination with fever", "Moderate"),
]

failed = 0
for text, expected in TEST_CASES:
    predicted = str(model.predict([text])[0])
    passed = predicted == expected
    print(f"{'PASS' if passed else 'FAIL'} | expected={expected:<8} predicted={predicted:<8} | {text}")
    failed += 0 if passed else 1

raise SystemExit(1 if failed else 0)
