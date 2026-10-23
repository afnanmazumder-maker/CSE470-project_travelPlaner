from fastapi import FastAPI

from app.schemas import RecommendationRequest, RecommendationResponse

app = FastAPI(title="Voyenta Tour Planner")


@app.get("/health")
def health_check():
    return {"status": "ok"}


@app.post("/recommendations", response_model=RecommendationResponse)
def recommend(request: RecommendationRequest):
    # Placeholder
    return RecommendationResponse(recommendations=[], fallback_used=True)