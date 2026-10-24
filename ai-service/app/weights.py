MIN_WEIGHT = 0.0
MAX_WEIGHT = 1.0
NEUTRAL_WEIGHT = 0.5
STEP = 0.1


def update_weights(
    weights: dict[str, float], tags: list[str], action: str
) -> dict[str, float]:
    if action not in ("save", "reject"):
        raise ValueError(f"Unknown action: {action}")

    direction = STEP if action == "save" else -STEP
    updated = dict(weights)

    for tag in set(tags):
        current = updated.get(tag, NEUTRAL_WEIGHT)
        new_value = current + direction
        updated[tag] = round(min(MAX_WEIGHT, max(MIN_WEIGHT, new_value)), 4)

    return updated