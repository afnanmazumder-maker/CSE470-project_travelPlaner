from fastapi import FastAPI

from app.recommender import recommend
from app.schemas import RecommendationRequest, RecommendationResponse

app = FastAPI(title="Voyenta AI Service")


@app.get("/health")
def health_check():
    return {"status": "ok"}


@app.post("/recommendations", response_model=RecommendationResponse)
def get_recommendations(request: RecommendationRequest):
    results, fallback_used = recommend(
        request.interest_weights, request.attractions, request.limit
    )
    return RecommendationResponse(recommendations=results, fallback_used=fallback_used)