import json
from pathlib import Path

from app.itinerary import cluster_into_days
from app.schemas import ItineraryAttraction

DATA_DIR = Path(__file__).resolve().parent.parent / "data"


def load_places():
    with open(DATA_DIR / "itinerary_attractions.json") as f:
        return [ItineraryAttraction(**item) for item in json.load(f)]


def ids(groups):
    return {frozenset(a.id for a in group) for group in groups}


def test_three_days_recovers_the_neighbourhoods():
    groups = cluster_into_days(load_places(), 3)
    assert ids(groups) == {
        frozenset({1, 2, 3, 4}),
        frozenset({5, 6, 7, 8}),
        frozenset({9, 10, 11, 12}),
    }


def test_every_attraction_appears_exactly_once():
    places = load_places()
    groups = cluster_into_days(places, 4)
    all_ids = [a.id for group in groups for a in group]
    assert sorted(all_ids) == sorted(p.id for p in places)


def test_one_day_puts_everything_together():
    groups = cluster_into_days(load_places(), 1)
    assert len(groups) == 1
    assert len(groups[0]) == 12


def test_more_days_than_places_does_not_crash():
    places = load_places()[:2]
    groups = cluster_into_days(places, 5)
    assert len(groups) == 2


def test_empty_input_returns_no_days():
    assert cluster_into_days([], 3) == []


def test_day_order_is_stable():
    first = cluster_into_days(load_places(), 3)
    second = cluster_into_days(load_places(), 3)
    assert ids(first) == ids(second)
    assert [min(a.id for a in g) for g in first] == [1, 5, 9]