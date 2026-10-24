import pytest
from fastapi.testclient import TestClient

from app.main import app
from app.weights import update_weights


def test_save_raises_weight():
    result = update_weights({"history": 0.5}, ["history"], "save")
    assert result["history"] == pytest.approx(0.6)


def test_reject_lowers_weight():
    result = update_weights({"history": 0.5}, ["history"], "reject")
    assert result["history"] == pytest.approx(0.4)


def test_weight_is_capped_at_one():
    result = update_weights({"history": 0.95}, ["history"], "save")
    assert result["history"] == 1.0


def test_weight_is_floored_at_zero():
    result = update_weights({"history": 0.05}, ["history"], "reject")
    assert result["history"] == 0.0


def test_new_tag_starts_from_neutral():
    result = update_weights({}, ["beach"], "save")
    assert result["beach"] == pytest.approx(0.6)


def test_untouched_tags_stay_the_same():
    result = update_weights({"history": 0.9, "museum": 0.3}, ["history"], "save")
    assert result["museum"] == 0.3


def test_input_is_not_modified():
    original = {"history": 0.5}
    update_weights(original, ["history"], "save")
    assert original == {"history": 0.5}


def test_duplicate_tags_only_count_once():
    result = update_weights({"history": 0.5}, ["history", "history"], "save")
    assert result["history"] == pytest.approx(0.6)


def test_unknown_action_raises():
    with pytest.raises(ValueError):
        update_weights({}, ["history"], "like")


def test_api_updates_weights():
    client = TestClient(app)
    body = {
        "interest_weights": {"history": 0.9, "museum": 0.5},
        "tags": ["history", "architecture"],
        "action": "save",
    }
    response = client.post("/weights/update", json=body)
    assert response.status_code == 200
    weights = response.json()["interest_weights"]
    assert weights["history"] == 1.0
    assert weights["architecture"] == pytest.approx(0.6)
    assert weights["museum"] == 0.5


def test_api_rejects_unknown_action():
    client = TestClient(app)
    body = {"interest_weights": {}, "tags": ["history"], "action": "like"}
    response = client.post("/weights/update", json=body)
    assert response.status_code == 422