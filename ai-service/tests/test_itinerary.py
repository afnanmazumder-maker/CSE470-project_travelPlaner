import json
import pytest
from pathlib import Path
from app.itinerary import cluster_into_days, distance_km, order_stops, route_length_km
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

def line_stops():
    # All on the equator, so distance depends only on longitude.
    return [
        ItineraryAttraction(id=1, name="A", latitude=0.0, longitude=2.0),
        ItineraryAttraction(id=2, name="B", latitude=0.0, longitude=0.0),
        ItineraryAttraction(id=3, name="C", latitude=0.0, longitude=4.0),
        ItineraryAttraction(id=4, name="D", latitude=0.0, longitude=1.0),
    ]


def test_distance_one_degree_of_latitude():
    assert distance_km(0, 0, 1, 0) == pytest.approx(111.19, abs=0.1)


def test_distance_same_point_is_zero():
    assert distance_km(48.85, 2.35, 48.85, 2.35) == 0.0


def test_order_empty_and_single():
    assert order_stops([]) == []
    single = line_stops()[:1]
    assert order_stops(single) == single


def test_order_follows_the_line():
    ordered = order_stops(line_stops())
    assert [s.id for s in ordered] == [3, 1, 4, 2]


def test_ordering_shortens_the_route():
    stops = line_stops()
    assert route_length_km(order_stops(stops)) < route_length_km(stops)


def test_order_keeps_every_stop_once_in_each_day():
    for group in cluster_into_days(load_places(), 3):
        ordered = order_stops(group)
        assert sorted(s.id for s in ordered) == sorted(s.id for s in group)


def test_order_is_deterministic():
    group = cluster_into_days(load_places(), 3)[0]
    first = [s.id for s in order_stops(group)]
    second = [s.id for s in order_stops(group)]
    assert first == second