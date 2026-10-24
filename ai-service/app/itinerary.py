import math

import numpy as np
from sklearn.cluster import KMeans

from app.schemas import ItineraryAttraction


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