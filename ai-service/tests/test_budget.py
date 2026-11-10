import pytest
from fastapi.testclient import TestClient

from app.main import app
from app.recommender import budget_factor, recommend
from app.schemas import Attraction


def place(id, tags, level, popularity=0.5):
    return Attraction(
        id=id, name=f"Place {id}", tags=tags, popularity=popularity, budget_level=level
    )


@pytest.mark.parametrize(
    "band, level, expected",
    [
        ("moderate", "moderate", 1.0),
        ("moderate", "luxury", 0.75),
        ("budget", "luxury", 0.5),
        ("budget", "moderate", 0.75),
        ("luxury", "budget", 1.0),
        (None, "luxury", 1.0),
        ("budget", None, 1.0),
    ],
)
def test_budget_factor(band, level, expected):
    assert budget_factor(band, level) == expected


def test_over_budget_places_score_lower_and_say_so():
    weights = {"architecture": 0.9}
    places = [place(1, ["architecture"], "luxury"), place(2, ["architecture"], "budget")]

    results, fallback = recommend(weights, places, 10, "budget")

    assert fallback is False
    assert [r.attraction_id for r in results] == [2, 1]
    assert results[0].score == pytest.approx(1.0)
    assert results[1].score == pytest.approx(0.5)
    assert "Above your usual budget" in results[1].reason
    assert "Above your usual budget" not in results[0].reason


def test_luxury_traveler_is_not_penalised():
    weights = {"architecture": 0.9}
    places = [place(1, ["architecture"], "luxury"), place(2, ["architecture"], "budget")]

    results, _ = recommend(weights, places, 10, "luxury")

    assert all(r.score == pytest.approx(1.0) for r in results)
    assert all("Above your usual budget" not in r.reason for r in results)


def test_budget_never_creates_a_match():
    weights = {"architecture": 0.9}
    places = [place(1, ["beach"], "budget"), place(2, ["architecture"], "luxury")]

    results, _ = recommend(weights, places, 10, "budget")

    assert [r.attraction_id for r in results] == [2]


def test_missing_budget_info_changes_nothing():
    weights = {"architecture": 0.9}
    places = [place(1, ["architecture"], None)]

    for band in (None, "budget"):
        results, _ = recommend(weights, places, 10, band)
        assert results[0].score == pytest.approx(1.0)
        assert "Above your usual budget" not in results[0].reason


def test_api_applies_budget_band():
    client = TestClient(app)
    body = {
        "interest_weights": {"architecture": 0.9},
        "attractions": [
            {"id": 1, "name": "Tower", "tags": ["architecture"], "popularity": 0.9, "budget_level": "luxury"}
        ],
        "budget_band": "moderate",
        "limit": 10,
    }

    response = client.post("/recommendations", json=body)

    assert response.status_code == 200
    assert response.json()["recommendations"][0]["score"] == pytest.approx(0.75)


def test_api_rejects_unknown_budget_band():
    client = TestClient(app)
    body = {
        "interest_weights": {"architecture": 0.9},
        "attractions": [{"id": 1, "name": "Tower", "tags": ["architecture"], "popularity": 0.9}],
        "budget_band": "cheap",
    }

    assert client.post("/recommendations", json=body).status_code == 422