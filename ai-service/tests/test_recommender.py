import json
from pathlib import Path

import pytest
from fastapi.testclient import TestClient

from app.main import app
from app.recommender import cosine_similarity, recommend
from app.schemas import Attraction

DATA_DIR = Path(__file__).resolve().parent.parent / "data"


def load_attractions():
    with open(DATA_DIR / "attractions.json") as f:
        return [Attraction(**item) for item in json.load(f)]


def load_users():
    with open(DATA_DIR / "users.json") as f:
        return {u["user"]: u["interest_weights"] for u in json.load(f)}


def test_cosine_known_value():
    weights = {"history": 0.9, "museum": 0.8, "architecture": 0.6}
    assert cosine_similarity(weights, ["museum", "history"]) == pytest.approx(0.8935, abs=1e-4)


def test_cosine_no_overlap_is_zero():
    weights = {"history": 0.9}
    assert cosine_similarity(weights, ["beach", "nature"]) == 0.0


def test_cosine_empty_weights_is_zero():
    assert cosine_similarity({}, ["museum"]) == 0.0


def test_history_buff_gets_history_first():
    users = load_users()
    results, fallback = recommend(users["history_buff"], load_attractions(), 10)
    assert fallback is False
    assert results[0].attraction_id == 1
    assert all(r.score > 0 for r in results)


def test_beach_foodie_gets_beach_first():
    users = load_users()
    results, fallback = recommend(users["beach_foodie"], load_attractions(), 10)
    assert fallback is False
    assert results[0].attraction_id == 3


def test_new_user_gets_popular_fallback():
    users = load_users()
    results, fallback = recommend(users["new_user"], load_attractions(), 10)
    assert fallback is True
    assert results[0].attraction_id == 3
    scores = [r.score for r in results]
    assert scores == sorted(scores, reverse=True)


def test_limit_is_respected():
    users = load_users()
    results, _ = recommend(users["history_buff"], load_attractions(), 2)
    assert len(results) == 2


def test_api_rejects_invalid_popularity():
    client = TestClient(app)
    body = {
        "interest_weights": {"history": 0.9},
        "attractions": [{"id": 1, "name": "X", "tags": ["history"], "popularity": 5}],
        "limit": 10,
    }
    response = client.post("/recommendations", json=body)
    assert response.status_code == 422


def test_api_returns_ranked_results():
    client = TestClient(app)
    body = {
        "interest_weights": {"history": 0.9, "museum": 0.8},
        "attractions": [
            {"id": 1, "name": "Museum", "tags": ["museum", "history"], "popularity": 0.8},
            {"id": 3, "name": "Beach", "tags": ["beach"], "popularity": 0.9},
        ],
        "limit": 10,
    }
    response = client.post("/recommendations", json=body)
    assert response.status_code == 200
    data = response.json()
    assert data["fallback_used"] is False
    assert [r["attraction_id"] for r in data["recommendations"]] == [1]