from pydantic import BaseModel, Field


class Attraction(BaseModel):
    id: int
    name: str
    tags: list[str]
    popularity: float = Field(ge=0, le=1)


class RecommendationRequest(BaseModel):
    interest_weights: dict[str, float]
    attractions: list[Attraction]
    limit: int = Field(default=10, ge=1, le=50)


class Recommendation(BaseModel):
    attraction_id: int
    name: str
    score: float
    reason: str


class RecommendationResponse(BaseModel):
    recommendations: list[Recommendation]
    fallback_used: bool