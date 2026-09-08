"""Endpoint checks for classification, safety handling, and input validation."""

from app import app


def main():
    client = app.test_client()

    valid_cases = [
        ("mild headache and runny nose", "Low"),
        ("high fever for 2 days", "Moderate"),
        ("chest pain and struggling to breathe", "High"),
    ]

    for symptoms, expected in valid_cases:
        response = client.post("/predict_urgency", json={"symptoms": symptoms})
        data = response.get_json()
        assert response.status_code == 200, (symptoms, response.status_code, data)
        assert data["urgency"] == expected, (symptoms, expected, data)
        print(f"PASS | {symptoms} -> {data['urgency']} ({data['confidence']})")

    red_flag_cases = [
        "possible stroke symptoms",
        "several seizures close together",
        "becoming unresponsive after taking an unknown substance",
        "heavy bleeding that will not stop",
    ]

    for symptoms in red_flag_cases:
        response = client.post("/predict_urgency", json={"symptoms": symptoms})
        data = response.get_json()
        assert response.status_code == 200, (symptoms, data)
        assert data["urgency"] == "High", (symptoms, data)
        assert data["safety_override"] is True, (symptoms, data)
        print(f"PASS | red flag retained as High: {symptoms}")

    invalid_cases = [
        "asdfghjkl qwerty",
        "I like pizza and football",
        "123456789",
        "zzzzzzzzzzzzzz",
        "I like cold pizza",
        "my computer has a virus",
        "this assignment is a pain",
        "I am sick of homework",
    ]

    for symptoms in invalid_cases:
        response = client.post("/predict_urgency", json={"symptoms": symptoms})
        data = response.get_json()
        assert response.status_code == 422, (symptoms, response.status_code, data)
        assert data["code"] == "invalid_symptom_description", (symptoms, data)
        print(f"PASS | rejected invalid input: {symptoms}")

    vague_case = client.post("/predict_urgency", json={"symptoms": "I have headache and nausea"})
    vague_data = vague_case.get_json()
    assert vague_case.status_code == 200, vague_data
    assert vague_data["needs_review"] is True, vague_data
    assert vague_data["urgency"] == "Moderate", vague_data
    print("PASS | low-confidence valid symptom escalated for review")


if __name__ == "__main__":
    main()
