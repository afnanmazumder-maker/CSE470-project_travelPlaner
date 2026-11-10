import pytest
from fastapi.testclient import TestClient

from app.main import app
from app.recommender import pace_factor, recommend
from app.schemas import Attraction


def place(id, tags, intensity, budget_level=None):
    return Attraction(
        id=id,
        name=f"Place {id}",
        tags=tags,
        popularity=0.5,
        intensity=intensity,
        budget_level=budget_level,
    )


@pytest.mark.parametrize(
    "pace, intensity, expected",
    [
        ("relaxed", "easy", 1.0),
        ("relaxed", "moderate", 0.75),
        ("relaxed", "demanding", 0.5),
        ("moderate", "demanding", 0.75),
        ("packed", "demanding", 1.0),
        ("packed", "easy", 1.0),
        (None, "demanding", 1.0),
        ("relaxed", None, 1.0),
    ],
)
def test_pace_factor(pace, intensity, expected):
    assert pace_factor(pace, intensity) == expected


def test_demanding_places_rank_lower_for_a_relaxed_traveler():
    weights = {"architecture": 0.9}
    places = [place(1, ["architecture"], "demanding"), place(2, ["architecture"], "easy")]

    results, fallback = recommend(weights, places, 10, None, "relaxed")

    assert fallback is False
    assert [r.attraction_id for r in results] == [2, 1]
    assert results[0].score == pytest.approx(1.0)
    assert results[1].score == pytest.approx(0.5)
    assert "More demanding than your preferred pace" in results[1].reason
    assert "More demanding" not in results[0].reason


def test_packed_traveler_is_never_penalised():
    weights = {"architecture": 0.9}
    places = [place(1, ["architecture"], "demanding"), place(2, ["architecture"], "easy")]

    results, _ = recommend(weights, places, 10, None, "packed")

    assert all(r.score == pytest.approx(1.0) for r in results)


def test_pace_never_creates_a_match():
    weights = {"architecture": 0.9}
    places = [place(1, ["beach"], "easy"), place(2, ["architecture"], "demanding")]

    results, _ = recommend(weights, places, 10, None, "relaxed")

    assert [r.attraction_id for r in results] == [2]


def test_budget_and_pace_penalties_stack():
    weights = {"architecture": 0.9}
    places = [place(1, ["architecture"], "demanding", "luxury")]

    results, _ = recommend(weights, places, 10, "budget", "relaxed")

    assert results[0].score == pytest.approx(0.25)
    assert "Above your usual budget" in results[0].reason
    assert "More demanding than your preferred pace" in results[0].reason


def test_missing_pace_information_changes_nothing():
    weights = {"architecture": 0.9}
    places = [place(1, ["architecture"], None)]

    for pace in (None, "relaxed"):
        results, _ = recommend(weights, places, 10, None, pace)
        assert results[0].score == pytest.approx(1.0)
        assert "More demanding" not in results[0].reason


def test_api_applies_pace():
    client = TestClient(app)
    body = {
        "interest_weights": {"architecture": 0.9},
        "attractions": [
            {"id": 1, "name": "Climb", "tags": ["architecture"], "popularity": 0.9, "intensity": "demanding"}
        ],
        "pace": "relaxed",
    }

    response = client.post("/recommendations", json=body)

    assert response.status_code == 200
    assert response.json()["recommendations"][0]["score"] == pytest.approx(0.5)


def test_api_rejects_unknown_pace():
    client = TestClient(app)
    body = {
        "interest_weights": {"architecture": 0.9},
        "attractions": [{"id": 1, "name": "Tower", "tags": ["architecture"], "popularity": 0.9}],
        "pace": "slow",
    }

    assert client.post("/recommendations", json=body).status_code == 422


def test_api_rejects_unknown_intensity():
    client = TestClient(app)
    body = {
        "interest_weights": {"architecture": 0.9},
        "attractions": [
            {"id": 1, "name": "Tower", "tags": ["architecture"], "popularity": 0.9, "intensity": "extreme"}
        ],
    }

    assert client.post("/recommendations", json=body).status_code == 422