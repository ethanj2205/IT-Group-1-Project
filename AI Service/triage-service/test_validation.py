"""Regression checks for symptom input validation."""

from validation import validate_symptoms

VALID_CASES = [
    "mild headache and runny nose",
    "high fever for 2 days",
    "chest pain and struggling to breathe",
    "blurred vision",
    "my finger is swollen",
    "I have pain in my left leg",
]

INVALID_CASES = [
    "asdfghjkl qwerty",
    "I like pizza and football",
    "123456789",
    "zzzzzzzzzzzzzz",
    "hello how are you",
    "I like cold pizza",
    "my computer has a virus",
    "my arm is holding a football",
    "this assignment is a pain",
    "I am sick of homework",
    "pain",
]

for text in VALID_CASES:
    valid, message = validate_symptoms(text)
    assert valid, (text, message)
    print(f"PASS | accepted: {text}")

for text in INVALID_CASES:
    valid, message = validate_symptoms(text)
    assert not valid, (text, message)
    print(f"PASS | rejected: {text}")
