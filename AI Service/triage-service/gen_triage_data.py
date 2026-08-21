"""Generate a deterministic synthetic triage text dataset for the MediQueue SA student MVP.

The dataset is intentionally synthetic and is NOT clinically validated.
It exists only to demonstrate the machine-learning pipeline and API integration.
"""

import csv
import random
from pathlib import Path

random.seed(42)
OUT = Path(__file__).resolve().parent / "kai_triage_training_data.csv"

BASE = {
    "Low": [
        "mild headache", "minor rash", "runny nose", "blocked nose", "mild sore throat",
        "small cough", "mild cough", "itchy eyes", "minor muscle ache", "mild back ache",
        "small cut that has stopped bleeding", "medicine refill request", "repeat prescription request",
        "mild stomach discomfort", "slight nausea", "minor ankle pain", "mild ear discomfort",
        "mild cold symptoms", "slight tiredness", "small bruise"
    ],
    "Moderate": [
        "high fever", "persistent vomiting", "severe pain", "dizziness", "dehydration",
        "suspected infection", "painful urination", "worsening cough with fever", "moderate abdominal pain",
        "fever with body aches", "persistent diarrhoea", "worsening asthma symptoms without severe breathing difficulty",
        "moderate headache with dizziness", "swollen painful joint", "infected wound", "persistent ear pain with fever",
        "repeated vomiting", "moderate shortness of breath", "worsening chronic illness symptoms", "fever lasting several days"
    ],
    "High": [
        "chest pain", "difficulty breathing", "struggling to breathe", "cannot breathe properly", "heavy bleeding",
        "unconscious", "seizure", "possible stroke", "face drooping with weak arm", "sudden inability to speak",
        "very severe chest pressure", "choking and cannot breathe", "collapsed and unresponsive", "coughing blood with breathing difficulty",
        "severe allergic reaction with breathing difficulty", "sudden severe weakness on one side", "continuous seizure",
        "uncontrolled bleeding", "blue lips with breathing difficulty", "severe head injury with loss of consciousness"
    ],
}

PREFIXES = [
    "I have", "Patient reports", "I am experiencing", "Symptoms include", "I have been having",
    "I feel", "The main problem is", "I need help with", "Since yesterday I have", "For the past few days I have"
]
SUFFIXES = [
    "", " since this morning", " for two days", " and I am worried", " that is getting worse",
    " and need advice", " with no improvement", " after waking up", " mostly at night", " during the day"
]

# Generic context is intentionally shared across all classes so the model
# learns symptom language rather than class-specific wording.
CONTEXT = [
    "and I want to know what to do",
    "and it has not improved",
    "and it started recently",
    "and I am looking for medical advice",
    "and I would like to speak to a doctor",
    "and it is bothering me",
]


rows = []
for label, phrases in BASE.items():
    # 6 variants per base phrase = 120 rows per class, 360 total.
    for phrase in phrases:
        for _ in range(6):
            prefix = random.choice(PREFIXES)
            suffix = random.choice(SUFFIXES)
            context = random.choice(CONTEXT)
            text = f"{prefix} {phrase}{suffix} {context}."
            rows.append((text.strip(), label))

random.shuffle(rows)

with OUT.open("w", newline="", encoding="utf-8") as f:
    writer = csv.writer(f)
    writer.writerow(["symptoms", "urgency"])
    writer.writerows(rows)

counts = {label: sum(1 for _, y in rows if y == label) for label in BASE}
print(f"Wrote {len(rows)} rows to {OUT.name}: {counts}")
