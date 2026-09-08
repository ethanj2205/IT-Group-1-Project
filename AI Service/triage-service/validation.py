"""Input validation for symptom descriptions."""

import re
from difflib import get_close_matches

MAX_SYMPTOM_LENGTH = 1200

STRONG_SYMPTOM_TERMS = {
    "ache", "aches", "allergy", "allergic", "anxiety", "anxious", "asthma",
    "bleed", "bleeding", "blood", "blurred", "blurry", "breath", "breathing",
    "bruise", "burn", "chills", "choking", "collapse", "collapsed", "constipation",
    "cough", "cramp", "cramps", "dehydration", "depressed", "depression", "diarrhea",
    "diarrhoea", "dizzy", "dizziness", "faint", "fainting", "fatigue", "fever",
    "feverish", "headache", "hurt", "hurts", "injury", "infection", "itch", "itchy",
    "medication", "migraine", "nausea", "nauseous", "numb", "numbness", "overdose",
    "pain", "palpitations", "phlegm", "pregnancy", "pregnant", "prescription", "rash",
    "refill", "seizure", "shortness", "sick", "sore", "stroke", "swelling", "swollen",
    "tired", "toothache", "unconscious", "unresponsive", "urination", "vomit", "vomiting", "weak",
    "weakness", "wheeze", "wheezing", "wound"
}

CONTEXT_TERMS = {
    "arm", "back", "chest", "ear", "eye", "eyes", "face", "finger", "foot", "head",
    "heart", "joint", "knee", "leg", "lips", "neck", "nose", "shoulder", "skin", "sleep",
    "stomach", "throat", "tooth", "tongue", "urine", "vision", "wrist"
}

SYMPTOM_PHRASES = {
    "can't breathe", "cannot breathe", "difficulty breathing", "shortness of breath",
    "chest pain", "high fever", "severe bleeding", "heavy bleeding", "body aches",
    "loss of consciousness", "face drooping", "weak arm", "painful urination",
    "severe pain", "sore throat", "runny nose", "have a cold", "common cold",
    "cannot sleep", "can't sleep", "red eye", "tight chest", "chest tightness",
    "nose is running", "cant breathe",
    "blurred vision", "loss of vision", "vomiting blood", "coughing up blood", "tooth pain"
}

VAGUE_TERMS = {"pain", "ache", "hurt", "hurts", "sick", "weak", "tired"}
UNRELATED_TERMS = {
    "assignment", "homework", "computer", "laptop", "keyboard", "software", "database",
    "github", "project", "printer", "wifi", "internet", "phone", "football", "game",
    "movie", "pizza", "car", "exam", "lecturer"
}


def _tokens(text: str) -> list[str]:
    return re.findall(r"[a-zA-Z']+", text.lower())


def has_symptom_signal(text: str) -> bool:
    lowered = text.lower()
    if any(phrase in lowered for phrase in SYMPTOM_PHRASES):
        return True

    tokens = _tokens(text)
    strong_matches = [token for token in tokens if token in STRONG_SYMPTOM_TERMS]
    if strong_matches:
        return True

    for token in tokens:
        if len(token) >= 5 and get_close_matches(token, STRONG_SYMPTOM_TERMS, n=1, cutoff=0.86):
            return True

    context_matches = [token for token in tokens if token in CONTEXT_TERMS]
    if context_matches and any(word in tokens for word in {"bad", "severe", "sharp", "sudden", "worse", "worsening"}):
        return True

    return False


def validate_symptoms(text: str) -> tuple[bool, str | None]:
    if not text:
        return False, "Symptoms are required."

    if len(text) > MAX_SYMPTOM_LENGTH:
        return False, "Symptom description is too long. Please keep it under 1200 characters."

    letters = re.findall(r"[A-Za-z]", text)
    if len(letters) < 3:
        return False, "Please enter a clear symptom description."

    visible_chars = [char for char in text if not char.isspace()]
    if visible_chars and len(letters) / len(visible_chars) < 0.45:
        return False, "Please enter symptoms using normal words rather than symbols or numbers."

    if re.search(r"(.)\1{6,}", text.lower()):
        return False, "Please enter a clear symptom description."

    tokens = _tokens(text)
    lowered = text.lower()
    strong_matches = {token for token in tokens if token in STRONG_SYMPTOM_TERMS}
    context_matches = {token for token in tokens if token in CONTEXT_TERMS}
    unrelated_matches = {token for token in tokens if token in UNRELATED_TERMS}
    phrase_match = any(phrase in lowered for phrase in SYMPTOM_PHRASES)

    unrelated_subject = re.search(r"\b(?:my|the)\s+(?:computer|laptop|keyboard|software|database|printer|wifi|internet|phone|car|assignment|homework|project|game)\b", lowered)
    if unrelated_subject and strong_matches:
        return False, "Please describe symptoms affecting a person rather than an object or unrelated topic."

    if unrelated_matches and strong_matches and strong_matches.issubset(VAGUE_TERMS) and not context_matches:
        return False, "Please describe an actual health symptom and include where or how you are affected."

    if strong_matches and strong_matches.issubset(VAGUE_TERMS) and len(tokens) <= 5 and not phrase_match:
        return False, "Please provide more detail about the symptom, including where you feel it or what else you are experiencing."

    if not has_symptom_signal(text):
        return False, (
            "The symptom description could not be recognised. Please describe a physical or mental "
            "health symptom, such as pain, fever, coughing, breathing difficulty, nausea, dizziness, "
            "bleeding, weakness, or another symptom."
        )

    return True, None
