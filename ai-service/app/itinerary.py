import math

import numpy as np
from sklearn.cluster import KMeans

from app.schemas import ItineraryAttraction
import math

import numpy as np
from sklearn.cluster import KMeans

from app.schemas import ItineraryAttraction, ItineraryDay, ItineraryResponse

EARTH_RADIUS_KM = 6371.0

def cluster_into_days(
    attractions: list[ItineraryAttraction], num_days: int
) -> list[list[ItineraryAttraction]]:
    if not attractions:
        return []

    k = min(num_days, len(attractions))

    mean_lat = sum(a.latitude for a in attractions) / len(attractions)
    lon_scale = math.cos(math.radians(mean_lat))
    points = np.array([[a.latitude, a.longitude * lon_scale] for a in attractions])

    model = KMeans(n_clusters=k, n_init=10, random_state=0).fit(points)

    groups: list[list[ItineraryAttraction]] = [[] for _ in range(k)]
    for attraction, label in zip(attractions, model.labels_):
        groups[label].append(attraction)

    groups.sort(key=lambda group: min(a.id for a in group))
    return groups

def distance_km(lat1: float, lon1: float, lat2: float, lon2: float) -> float:
    phi1, phi2 = math.radians(lat1), math.radians(lat2)
    d_phi = phi2 - phi1
    d_lambda = math.radians(lon2 - lon1)

    h = math.sin(d_phi / 2) ** 2 + math.cos(phi1) * math.cos(phi2) * math.sin(d_lambda / 2) ** 2
    return 2 * EARTH_RADIUS_KM * math.asin(math.sqrt(h))


def route_length_km(stops: list[ItineraryAttraction]) -> float:
    return sum(
        distance_km(a.latitude, a.longitude, b.latitude, b.longitude)
        for a, b in zip(stops, stops[1:])
    )


def order_stops(stops: list[ItineraryAttraction]) -> list[ItineraryAttraction]:
    if len(stops) <= 1:
        return list(stops)

    mean_lat = sum(s.latitude for s in stops) / len(stops)
    mean_lon = sum(s.longitude for s in stops) / len(stops)

    remaining = sorted(stops, key=lambda s: s.id)
    start = max(
        remaining,
        key=lambda s: distance_km(s.latitude, s.longitude, mean_lat, mean_lon),
    )

    route = [start]
    remaining.remove(start)
    while remaining:
        last = route[-1]
        nearest = min(
            remaining,
            key=lambda s: distance_km(last.latitude, last.longitude, s.latitude, s.longitude),
        )
        route.append(nearest)
        remaining.remove(nearest)

    return route

def split_into_blocks(
    stops: list[ItineraryAttraction],
) -> tuple[list[ItineraryAttraction], list[ItineraryAttraction], list[ItineraryAttraction]]:
    base, extra = divmod(len(stops), 3)
    sizes = [base + (1 if i < extra else 0) for i in range(3)]

    end_morning = sizes[0]
    end_afternoon = sizes[0] + sizes[1]
    return stops[:end_morning], stops[end_morning:end_afternoon], stops[end_afternoon:]


def build_itinerary(
    attractions: list[ItineraryAttraction], num_days: int
) -> ItineraryResponse:
    days = []
    for number, group in enumerate(cluster_into_days(attractions, num_days), start=1):
        ordered = order_stops(group)
        morning, afternoon, evening = split_into_blocks(ordered)
        days.append(
            ItineraryDay(
                day=number,
                morning=morning,
                afternoon=afternoon,
                evening=evening,
                distance_km=round(route_length_km(ordered), 2),
            )
        )
    return ItineraryResponse(days=days)