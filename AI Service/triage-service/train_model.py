"""Train and evaluate the MediQueue SA symptom-text urgency classifier."""

from pathlib import Path
import json

import joblib
import pandas as pd
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.linear_model import LogisticRegression
from sklearn.metrics import accuracy_score, classification_report, confusion_matrix
from sklearn.model_selection import train_test_split
from sklearn.pipeline import Pipeline, FeatureUnion

from safety_rules import critical_red_flag

BASE_DIR = Path(__file__).resolve().parent
DATA_PATH = BASE_DIR / "triage_training_data.csv"
MODEL_PATH = BASE_DIR / "triage_model.joblib"
METRICS_PATH = BASE_DIR / "metrics.json"
MIN_CONFIDENCE = 0.45


def build_pipeline() -> Pipeline:
    return Pipeline([
        ("features", FeatureUnion([
            ("word_tfidf", TfidfVectorizer(
                lowercase=True,
                analyzer="word",
                ngram_range=(1, 2),
                min_df=2,
                max_features=9000,
                sublinear_tf=True,
                strip_accents="unicode",
            )),
            ("char_tfidf", TfidfVectorizer(
                lowercase=True,
                analyzer="char_wb",
                ngram_range=(3, 5),
                min_df=2,
                max_features=12000,
                sublinear_tf=True,
                strip_accents="unicode",
            )),
        ])),
        ("classifier", LogisticRegression(
            max_iter=2000,
            class_weight="balanced",
            random_state=42,
            C=2.0,
        )),
    ])


def apply_service_policy(texts, predictions, probabilities):
    results = []
    for text, predicted, probability_row in zip(texts, predictions, probabilities):
        confidence = float(max(probability_row))
        if critical_red_flag(str(text)):
            results.append("High")
        elif confidence < MIN_CONFIDENCE:
            results.append("Moderate")
        else:
            results.append(str(predicted))
    return results


def main():
    df = pd.read_csv(DATA_PATH)

    required = {"symptoms", "urgency", "scenario_id"}
    missing = required - set(df.columns)
    if missing:
        raise ValueError(f"Dataset missing columns: {sorted(missing)}")

    scenario_table = df[["scenario_id", "urgency"]].drop_duplicates()
    train_scenarios, test_scenarios = train_test_split(
        scenario_table,
        test_size=0.25,
        random_state=42,
        stratify=scenario_table["urgency"],
    )

    train_ids = set(train_scenarios["scenario_id"])
    test_ids = set(test_scenarios["scenario_id"])

    train_df = df[df["scenario_id"].isin(train_ids)].copy()
    test_df = df[df["scenario_id"].isin(test_ids)].copy()

    evaluation_model = build_pipeline()
    evaluation_model.fit(train_df["symptoms"].astype(str), train_df["urgency"].astype(str))

    X_test = test_df["symptoms"].astype(str)
    y_test = test_df["urgency"].astype(str)
    predictions = evaluation_model.predict(X_test)
    probabilities = evaluation_model.predict_proba(X_test)

    accuracy = float(accuracy_score(y_test, predictions))
    service_predictions = apply_service_policy(X_test, predictions, probabilities)
    service_accuracy = float(accuracy_score(y_test, service_predictions))

    labels = ["Low", "Moderate", "High"]
    report = classification_report(
        y_test,
        predictions,
        labels=labels,
        output_dict=True,
        zero_division=0,
    )
    matrix = confusion_matrix(y_test, predictions, labels=labels).tolist()
    service_matrix = confusion_matrix(y_test, service_predictions, labels=labels).tolist()

    metrics = {
        "dataset_rows": int(len(df)),
        "training_rows_for_evaluation": int(len(train_df)),
        "test_rows": int(len(test_df)),
        "training_scenarios": int(len(train_ids)),
        "test_scenarios": int(len(test_ids)),
        "model_only_accuracy": round(accuracy, 4),
        "service_policy_accuracy": round(service_accuracy, 4),
        "labels": labels,
        "model_confusion_matrix": matrix,
        "service_policy_confusion_matrix": service_matrix,
        "classification_report": report,
        "validation_scope": (
            "Evaluation uses a scenario-grouped holdout. All wording variants of each held-out "
            "symptom scenario are kept out of training to reduce near-duplicate leakage."
        ),
        "service_policy_note": (
            "Service-policy evaluation includes conservative red-flag handling and low-confidence "
            "escalation. These results are development-set evaluation, not clinical validation."
        ),
        "final_model_training_rows": int(len(df)),
    }

    final_model = build_pipeline()
    final_model.fit(df["symptoms"].astype(str), df["urgency"].astype(str))

    joblib.dump(final_model, MODEL_PATH)
    METRICS_PATH.write_text(json.dumps(metrics, indent=2), encoding="utf-8")

    print(f"Dataset rows: {len(df)}")
    print(f"Evaluation training rows: {len(train_df)} from {len(train_ids)} scenarios")
    print(f"Evaluation test rows: {len(test_df)} from {len(test_ids)} unseen scenarios")
    print(f"Model-only grouped-holdout accuracy: {accuracy:.3%}")
    print(f"Service-policy grouped-holdout accuracy: {service_accuracy:.3%}")
    print(classification_report(y_test, predictions, labels=labels, zero_division=0))
    print(f"Final saved model trained on all {len(df)} rows")
    print(f"Model saved to: {MODEL_PATH.name}")
    print(f"Metrics saved to: {METRICS_PATH.name}")


if __name__ == "__main__":
    main()
