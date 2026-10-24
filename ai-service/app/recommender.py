import math

from app.schemas import Attraction


def cosine_similarity(weights: dict[str, float], tags: list[str]) -> float:
    unique_tags = set(tags)

    dot_product = sum(weights.get(tag, 0.0) for tag in unique_tags)

    user_length = math.sqrt(sum(w ** 2 for w in weights.values()))
    attraction_length = math.sqrt(len(unique_tags))

    if user_length == 0 or attraction_length == 0:
        return 0.0

    return dot_product / (user_length * attraction_length)


def score_attractions(
    weights: dict[str, float], attractions: list[Attraction]
) -> list[tuple[Attraction, float]]:
    scored = [(a, cosine_similarity(weights, a.tags)) for a in attractions]
    return sorted(scored, key=lambda pair: pair[1], reverse=True)