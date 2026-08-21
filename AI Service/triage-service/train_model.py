"""Train the MediQueue SA symptom-text urgency classifier.

Input:  kai_triage_training_data.csv
Output: triage_model.joblib and metrics.json

Academic prototype only: synthetic data, not clinically validated.
"""

from pathlib import Path
import json
import joblib
import pandas as pd
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.linear_model import LogisticRegression
from sklearn.metrics import accuracy_score, classification_report, confusion_matrix
from sklearn.model_selection import train_test_split
from sklearn.pipeline import Pipeline, FeatureUnion

BASE_DIR = Path(__file__).resolve().parent
DATA_PATH = BASE_DIR / "kai_triage_training_data.csv"
MODEL_PATH = BASE_DIR / "triage_model.joblib"
METRICS_PATH = BASE_DIR / "metrics.json"


def main():
    df = pd.read_csv(DATA_PATH)

    required = {"symptoms", "urgency"}
    missing = required - set(df.columns)
    if missing:
        raise ValueError(f"Dataset missing columns: {sorted(missing)}")

    X_train, X_test, y_train, y_test = train_test_split(
        df["symptoms"].astype(str),
        df["urgency"].astype(str),
        test_size=0.25,
        random_state=42,
        stratify=df["urgency"],
    )

    model = Pipeline([
        ("features", FeatureUnion([
            ("word_tfidf", TfidfVectorizer(
                lowercase=True,
                analyzer="word",
                ngram_range=(1, 2),
                min_df=1,
                max_features=6000,
                sublinear_tf=True,
            )),
            ("char_tfidf", TfidfVectorizer(
                lowercase=True,
                analyzer="char_wb",
                ngram_range=(3, 5),
                min_df=1,
                max_features=8000,
                sublinear_tf=True,
            )),
        ])),
        ("classifier", LogisticRegression(
            max_iter=1500,
            class_weight="balanced",
            random_state=42,
        )),
    ])

    model.fit(X_train, y_train)
    preds = model.predict(X_test)

    accuracy = float(accuracy_score(y_test, preds))
    labels = ["Low", "Moderate", "High"]
    report = classification_report(y_test, preds, labels=labels, output_dict=True, zero_division=0)
    matrix = confusion_matrix(y_test, preds, labels=labels).tolist()

    metrics = {
        "dataset_rows": int(len(df)),
        "training_rows": int(len(X_train)),
        "test_rows": int(len(X_test)),
        "accuracy": round(accuracy, 4),
        "labels": labels,
        "confusion_matrix": matrix,
        "classification_report": report,
        "note": "Synthetic student-project data; results are not clinical validation.",
    }

    joblib.dump(model, MODEL_PATH)
    METRICS_PATH.write_text(json.dumps(metrics, indent=2), encoding="utf-8")

    print(f"Dataset rows: {len(df)}")
    print(f"Test accuracy: {accuracy:.3%}")
    print(classification_report(y_test, preds, labels=labels, zero_division=0))
    print(f"Model saved to: {MODEL_PATH.name}")
    print(f"Metrics saved to: {METRICS_PATH.name}")


if __name__ == "__main__":
    main()
