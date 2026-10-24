import math

from app.schemas import Attraction, Recommendation


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

def build_reason(weights: dict[str, float], tags: list[str]) -> str:
    matched = [tag for tag in set(tags) if weights.get(tag, 0.0) > 0]
    matched.sort(key=lambda tag: weights[tag], reverse=True)
    return "Matches your interests: " + ", ".join(matched)


def recommend(
    weights: dict[str, float], attractions: list[Attraction], limit: int
) -> tuple[list[Recommendation], bool]:
    has_interests = any(w > 0 for w in weights.values())

    if has_interests:
        scored = [
            (attraction, score)
            for attraction, score in score_attractions(weights, attractions)
            if score > 0
        ]
        if scored:
            results = [
                Recommendation(
                    attraction_id=attraction.id,
                    name=attraction.name,
                    score=round(score, 4),
                    reason=build_reason(weights, attraction.tags),
                )
                for attraction, score in scored[:limit]
            ]
            return results, False

    popular = sorted(attractions, key=lambda a: a.popularity, reverse=True)[:limit]
    results = [
        Recommendation(
            attraction_id=a.id,
            name=a.name,
            score=a.popularity,
            reason="Popular with travelers",
        )
        for a in popular
    ]
    return results, True