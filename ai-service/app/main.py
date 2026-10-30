from fastapi import FastAPI

from app.itinerary import build_itinerary
from app.recommender import recommend
from app.schemas import (
    ItineraryRequest,
    ItineraryResponse,
    RecommendationRequest,
    RecommendationResponse,
    WeightUpdateRequest,
    WeightUpdateResponse,
)
from app.weights import update_weights

app = FastAPI(title="Voyenta Tour Planner")


@app.get("/health")
def health_check():
    return {"status": "ok"}


@app.post("/recommendations", response_model=RecommendationResponse)
def get_recommendations(request: RecommendationRequest):
    results, fallback_used = recommend(
        request.interest_weights,
        request.attractions,
        request.limit,
        request.budget_band,
    )
    return RecommendationResponse(recommendations=results, fallback_used=fallback_used)

@app.post("/weights/update", response_model=WeightUpdateResponse)
def update_interest_weights(request: WeightUpdateRequest):
    new_weights = update_weights(
        request.interest_weights, request.tags, request.action
    )
    return WeightUpdateResponse(interest_weights=new_weights)

@app.post("/itinerary", response_model=ItineraryResponse)
def generate_itinerary(request: ItineraryRequest):
    return build_itinerary(request.attractions, request.num_days)