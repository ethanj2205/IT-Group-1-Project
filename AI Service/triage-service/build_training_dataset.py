"""Build the symptom classification development dataset."""

import csv
import random
import re
from pathlib import Path

random.seed(42)
OUT = Path(__file__).resolve().parent / "triage_training_data.csv"

SCENARIOS = {
    "Low": [
        "a mild headache",
        "a runny nose with sneezing",
        "a blocked nose",
        "a mild sore throat",
        "a mild dry cough without fever",
        "itchy watery eyes",
        "minor muscle aches after exercise",
        "a mild lower back ache after sitting for a long time",
        "a small cut that has already stopped bleeding",
        "a repeat prescription request with no new symptoms",
        "mild stomach discomfort after eating",
        "slight nausea without vomiting",
        "minor ankle soreness and I can still walk normally",
        "mild ear discomfort without fever",
        "mild cold symptoms",
        "slight tiredness after poor sleep",
        "a small bruise with no major swelling",
        "mild heartburn after eating",
        "occasional constipation without severe pain",
        "mild skin itching with no swelling",
        "mild menstrual cramps that are manageable",
        "a small itchy insect bite",
        "mild tooth sensitivity",
        "mild neck stiffness after sleeping awkwardly",
        "a dry patch of skin",
        "mild sinus pressure with no fever",
        "a mildly hoarse voice",
        "mild knee pain after exercise",
        "mild shoulder soreness after carrying bags",
        "mild indigestion",
        "slight bloating after meals",
        "mild seasonal allergy symptoms",
        "mild eye irritation without vision changes",
        "a minor rash with no fever or swelling",
        "mild arm soreness after normal activity",
        "a small cut with light bleeding that stops with pressure",
        "a mild cough with no breathing difficulty",
        "slight loss of appetite for one day but I can drink normally",
        "a mild tension headache after a stressful day",
        "minor finger soreness after bumping it"
    ],
    "Moderate": [
        "a high fever that has lasted for several days",
        "persistent vomiting and difficulty keeping food down",
        "pain that is strong enough to limit my normal activities",
        "dizziness that keeps coming back",
        "signs of dehydration such as a dry mouth and very little urine",
        "a red painful wound that looks infected",
        "painful urination with fever",
        "a worsening cough together with fever",
        "moderate abdominal pain that is not improving",
        "fever with body aches and weakness",
        "persistent diarrhoea for more than a day",
        "worsening asthma symptoms but I can still speak normally",
        "a strong headache with dizziness but no weakness on one side",
        "a swollen painful joint",
        "an infected cut with increasing redness and swelling",
        "persistent ear pain together with fever",
        "repeated vomiting over several hours",
        "shortness of breath when walking but I can breathe while resting",
        "a chronic condition that has become noticeably worse",
        "fever that has continued for three days",
        "a migraine with nausea and sensitivity to light",
        "repeated heart palpitations without chest pain or fainting",
        "back pain that is making normal movement difficult",
        "a swollen ankle after a fall and walking is painful",
        "ongoing nausea with reduced food and fluid intake",
        "a cough that has persisted for more than a week",
        "a widespread rash with fever but no breathing difficulty",
        "a painful burn over a small area of skin",
        "dental swelling and significant tooth pain",
        "a very red painful eye but I can still see normally",
        "vomiting during pregnancy and struggling to keep fluids down",
        "an allergic rash with swelling but normal breathing",
        "a painful wrist after a fall with significant swelling",
        "a nosebleed that keeps returning but stops with pressure",
        "persistent sinus pain with fever",
        "a sore throat with fever and difficulty swallowing food",
        "moderate wheezing that improves when I rest",
        "recurrent abdominal cramps with vomiting",
        "a painful swollen finger that may be infected",
        "worsening fatigue with fever and poor appetite"
    ],
    "High": [
        "severe chest pain",
        "severe difficulty breathing at rest",
        "I cannot breathe properly",
        "heavy bleeding that will not stop",
        "loss of consciousness and not responding normally",
        "a seizure",
        "possible stroke symptoms",
        "sudden face drooping with weakness in one arm",
        "sudden inability to speak clearly with one-sided weakness",
        "choking and unable to breathe",
        "coughing up blood together with breathing difficulty",
        "a severe allergic reaction with throat swelling and breathing difficulty",
        "blue lips together with severe breathing difficulty",
        "a severe head injury followed by loss of consciousness",
        "strong chest pressure spreading to the arm or jaw",
        "a collapse and the person is not waking properly",
        "a seizure that has continued for several minutes",
        "several seizures occurring close together",
        "severe shortness of breath while sitting still",
        "sudden severe weakness on one side of the body",
        "sudden confusion together with difficulty speaking",
        "a major injury with uncontrolled bleeding",
        "a deep wound with blood spurting or flowing heavily",
        "a suspected medication overdose with severe drowsiness",
        "a serious burn involving the face or airway",
        "heavy bleeding during pregnancy with severe abdominal pain",
        "thoughts of suicide with an immediate plan to act",
        "a very high fever with stiff neck and confusion",
        "severe abdominal pain together with fainting",
        "vomiting blood",
        "black tar-like stool together with severe weakness or faintness",
        "sudden loss of vision in one or both eyes",
        "a chemical injury to the eye with severe pain and vision changes",
        "rapid swelling of the tongue or throat",
        "severe asthma symptoms and unable to speak full sentences",
        "a severe injury to the chest with breathing difficulty",
        "sudden crushing chest pain with sweating and nausea",
        "uncontrolled bleeding after a serious accident",
        "becoming unresponsive after taking an unknown substance",
        "severe breathing difficulty with confusion or extreme drowsiness"
    ],
}

OPENERS = [
    "I have", "I've got", "I am dealing with", "I am experiencing", "My main problem is",
    "I need help with", "I have been having", "I'm worried about", "The problem is", "Lately I have had"
]
DURATIONS = [
    "since this morning", "since yesterday", "for two days", "for the past three days",
    "since last night", "for several hours", "over the last few days"
]
ENDINGS = [
    "and it has not improved", "and it is bothering me", "and I would like medical advice",
    "and I would like to speak to a clinician", "and I am not sure what to do",
    "and it seems to be getting worse", "and I am concerned about it"
]

COMMON_TYPOS = {
    "breathing": "brething", "headache": "headach", "fever": "feveer", "vomiting": "vomitting",
    "dizziness": "dizzyness", "diarrhoea": "diarhoea", "swollen": "swolen", "allergic": "alergic",
    "stomach": "stomch", "severe": "sevre", "pressure": "presure", "weakness": "weaknes",
    "prescription": "presciption", "difficulty": "dificulty", "infection": "infecton"
}


def typo_variant(text: str) -> str:
    words = list(COMMON_TYPOS)
    random.shuffle(words)
    for word in words:
        if re.search(rf"\b{re.escape(word)}\b", text, flags=re.IGNORECASE):
            return re.sub(rf"\b{re.escape(word)}\b", COMMON_TYPOS[word], text, count=1, flags=re.IGNORECASE)
    return text


def variants(phrase: str) -> list[str]:
    base = phrase.rstrip(".")
    rows = [
        f"{random.choice(OPENERS)} {base}.",
        f"{random.choice(OPENERS)} {base} {random.choice(DURATIONS)}.",
        f"For the past couple of days, I have had {base}.",
        f"{base.capitalize()}, {random.choice(ENDINGS)}.",
        f"Can someone help me? I have {base}.",
        f"Symptoms: {base}.",
        f"I would like to get checked because I have {base}.",
        f"{random.choice(OPENERS)} {base}; {random.choice(ENDINGS)}.",
        f"Been having {base} {random.choice(DURATIONS)}.",
        typo_variant(f"I have {base} and need advice.")
    ]
    return [re.sub(r"\s+", " ", text).strip() for text in rows]


rows = []
for label, scenarios in SCENARIOS.items():
    for index, phrase in enumerate(scenarios, start=1):
        scenario_id = f"{label[0]}{index:02d}"
        for text in variants(phrase):
            rows.append((text, label, scenario_id))

random.shuffle(rows)

with OUT.open("w", newline="", encoding="utf-8") as file:
    writer = csv.writer(file)
    writer.writerow(["symptoms", "urgency", "scenario_id"])
    writer.writerows(rows)

counts = {label: sum(1 for _, urgency, _ in rows if urgency == label) for label in SCENARIOS}
print(f"Wrote {len(rows)} rows to {OUT.name}: {counts}")
