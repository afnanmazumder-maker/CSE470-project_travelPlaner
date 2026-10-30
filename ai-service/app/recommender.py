import math

from app.schemas import Attraction, Recommendation

BUDGET_ORDER = {"budget": 0, "moderate": 1, "luxury": 2}
OVER_BUDGET_FACTOR = {0: 1.0, 1: 0.75, 2: 0.5}

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


def budget_factor(budget_band: str | None, attraction_level: str | None) -> float:
    if budget_band is None or attraction_level is None:
        return 1.0

    gap = BUDGET_ORDER[attraction_level] - BUDGET_ORDER[budget_band]
    return OVER_BUDGET_FACTOR[max(gap, 0)]

def build_reason(
    weights: dict[str, float], tags: list[str], over_budget: bool = False
) -> str:
    matched = [tag for tag in set(tags) if weights.get(tag, 0.0) > 0]
    matched.sort(key=lambda tag: weights[tag], reverse=True)

    reason = "Matches your interests: " + ", ".join(matched)
    if over_budget:
        reason += ". Above your usual budget."
    return reason


def recommend(
    weights: dict[str, float],
    attractions: list[Attraction],
    limit: int,
    budget_band: str | None = None,
) -> tuple[list[Recommendation], bool]:
    has_interests = any(w > 0 for w in weights.values())

    if has_interests:
        scored = []
        for attraction, cosine in score_attractions(weights, attractions):
            if cosine <= 0:
                continue
            factor = budget_factor(budget_band, attraction.budget_level)
            scored.append((attraction, cosine * factor, factor < 1.0))

        scored.sort(key=lambda item: item[1], reverse=True)

        if scored:
            results = [
                Recommendation(
                    attraction_id=attraction.id,
                    name=attraction.name,
                    score=round(score, 4),
                    reason=build_reason(weights, attraction.tags, over_budget),
                )
                for attraction, score, over_budget in scored[:limit]
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