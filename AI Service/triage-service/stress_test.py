"""Simple stress cases for the HTTP classification behaviour."""

from app import app

CASES = [
    "asdfghjkl qwerty",
    "this assignment is a pain",
    "I am sick of homework",
    "my laptop has a headache",
    "mild headache and runny nose",
    "high fever for two days and feeling weak",
    "possible stroke symptoms",
    "several seizures close together",
    "heavy bleeding that will not stop",
    "I have pain in my left leg after walking",
]

client = app.test_client()
for text in CASES:
    response = client.post("/predict_urgency", json={"symptoms": text})
    print(response.status_code, text, "->", response.get_json())
