# Voyenta AI Service

A small, stateless FastAPI service that powers the AI-backed features of Voyenta (CSE470 project): personalized recommendations (R3), interest-weight updates (R2) and itinerary generation (R5).

The service **never touches the database**. Laravel sends the data it needs in each request and stores whatever comes back. This keeps the service easy to test and easy to run alongside the Laravel app.

## Running it

From the `ai-service` folder:

```
python -m venv venv
venv\Scripts\activate          # Mac/Linux: source venv/bin/activate
pip install -r requirements.txt
uvicorn app.main:app --reload
```

- Service: http://127.0.0.1:8000
- Interactive docs (try every endpoint in the browser): http://127.0.0.1:8000/docs

## Running the tests

```
python -m pytest -v
```

## Project layout

```
ai-service/
  app/
    main.py          HTTP endpoints only (receive request, call logic, return response)
    schemas.py       Pydantic models: the request/response contract with Laravel
    recommender.py   Cosine-similarity scoring, reasons, popular fallback (R3)
    weights.py       Interest-weight update rule (R2)
    itinerary.py     K-means day clustering, stop ordering, time blocks (R5)
  data/              Seed data used for tests and manual trials
  tests/             pytest tests
  requirements.txt
```

## Data contract

These rules are enforced automatically. A request that breaks them gets a `422` response describing what is wrong.

- **Interest weights:** numbers from `0` to `1`, keyed by tag, for example `{"history": 0.9, "museum": 0.8}`.
- **Tags:** lowercase words from one shared vocabulary: `museum`, `history`, `architecture`, `beach`, `nature`, `adventure`, `food`, `shopping`, `nightlife`, `art`. Quiz answers and attraction tags must use exactly these words, or scoring will not match them.
- **Popularity:** a number from `0` to `1`.
- **Coordinates:** latitude from `-90` to `90`, longitude from `-180` to `180`.
- **Trip length (`num_days`):** an integer from `1` to `30`.

## Endpoints

### `GET /health`

Returns `{"status": "ok"}`. Use it to check the service is up.

### `POST /recommendations` (R3)

Ranks attractions for a traveler by matching the attraction tags to the traveler's interest weights (cosine similarity).

Request:

```json
{
  "interest_weights": { "history": 0.9, "museum": 0.8, "architecture": 0.6 },
  "attractions": [
    {
      "id": 1,
      "name": "National Museum of History",
      "tags": ["museum", "history"],
      "popularity": 0.8
    },
    {
      "id": 3,
      "name": "Sunset Beach",
      "tags": ["beach", "nature"],
      "popularity": 0.9
    },
    {
      "id": 8,
      "name": "Cathedral of the Old City",
      "tags": ["architecture", "history"],
      "popularity": 0.75
    }
  ],
  "limit": 10
}
```

Response:

```json
{
  "recommendations": [
    {
      "attraction_id": 1,
      "name": "National Museum of History",
      "score": 0.8935,
      "reason": "Matches your interests: history, museum"
    },
    {
      "attraction_id": 8,
      "name": "Cathedral of the Old City",
      "score": 0.7884,
      "reason": "Matches your interests: history, architecture"
    }
  ],
  "fallback_used": false
}
```

Behaviour to know about:

- Attractions with no matching tag (score 0) are left out. Sunset Beach is missing above for that reason.
- **Fallback:** if the traveler has no positive weights (a new user), or nothing matched, the most popular attractions are returned with `"fallback_used": true` and the reason `"Popular with travelers"`.
- `limit` defaults to `10` and must be between `1` and `50`.
- Results are sorted from best to worst. The `reason` lists the matching tags, strongest interest first.

### `POST /weights/update` (R2)

Applies the learning rule when a traveler saves or rejects a suggestion. Laravel stores the returned weights.

Request:

```json
{
  "interest_weights": { "history": 0.9, "museum": 0.5 },
  "tags": ["history", "architecture"],
  "action": "save"
}
```

Response:

```json
{ "interest_weights": { "history": 1.0, "museum": 0.5, "architecture": 0.6 } }
```

The rule:

- `"save"` raises the weight of each of the attraction's tags by `0.1`. `"reject"` lowers each by `0.1`.
- A tag the traveler has no weight for starts from `0.5` before the change.
- Weights are clamped between `0` and `1`.
- Tags not listed in `tags` are unchanged, and the weights you send in are never modified in place.
- `action` must be exactly `"save"` or `"reject"`.

The step size and starting weight are constants at the top of `app/weights.py`.

### `POST /itinerary` (R5)

Builds a day-by-day plan: groups nearby attractions into days (k-means), orders each day's stops to shorten travel (nearest neighbour, straight-line distances), and splits each day into morning, afternoon and evening.

Request (toy coordinates on the equator, to keep the numbers easy to check):

```json
{
  "attractions": [
    { "id": 1, "name": "A", "latitude": 0.0, "longitude": 2.0 },
    { "id": 2, "name": "B", "latitude": 0.0, "longitude": 0.0 },
    { "id": 3, "name": "C", "latitude": 0.0, "longitude": 4.0 },
    { "id": 4, "name": "D", "latitude": 0.0, "longitude": 1.0 }
  ],
  "num_days": 1
}
```

Response (each place is returned with its `id`, `name`, `latitude` and `longitude`; shortened here to names):

```
day 1
  morning:   C, A
  afternoon: D
  evening:   B
  distance_km: 444.78
```

Behaviour to know about:

- Days are numbered from 1 and ordered by the smallest attraction id they contain, so results are repeatable.
- If there are fewer attractions than `num_days`, you get one day per attraction instead of an error.
- Stops are split across the three blocks as evenly as possible, with any extra stop going to the earlier blocks (4 stops become 2 / 1 / 1).
- `distance_km` is the total straight-line travel distance for that day.

## Error format

Invalid input returns HTTP `422` with a JSON body that names the field and the problem (for example, a popularity of `5` is rejected because it must be at most `1`).

## Known limitations and planned improvements

- **Collaborative filtering** ("travelers like you") is not implemented yet. It needs review data from the reviews feature (R18). Recommendations currently use tag matching only.
- **Opening hours** are not considered by the itinerary generator yet.
- **Pace and budget** are not used by the itinerary generator yet.
- Distances are straight-line (haversine), not road distances.
- K-means groups by location only, so days can come out uneven in size.
- Tags are not validated against the shared vocabulary yet.
