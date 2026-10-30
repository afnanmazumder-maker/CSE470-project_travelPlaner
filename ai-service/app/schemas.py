from pydantic import BaseModel, Field
from typing import Literal
from pydantic import BaseModel, Field

BudgetLevel = Literal["budget", "moderate", "luxury"]

class Attraction(BaseModel):
    id: int
    name: str
    tags: list[str]
    popularity: float = Field(ge=0, le=1)
    budget_level: BudgetLevel | None = None


class RecommendationRequest(BaseModel):
    interest_weights: dict[str, float]
    attractions: list[Attraction]
    limit: int = Field(default=10, ge=1, le=50)
    budget_band: BudgetLevel | None = None


class Recommendation(BaseModel):
    attraction_id: int
    name: str
    score: float
    reason: str


class RecommendationResponse(BaseModel):
    recommendations: list[Recommendation]
    fallback_used: bool

class WeightUpdateRequest(BaseModel):
    interest_weights: dict[str, float]
    tags: list[str]
    action: Literal["save", "reject"]


class WeightUpdateResponse(BaseModel):
    interest_weights: dict[str, float]

class ItineraryAttraction(BaseModel):
    id: int
    name: str
    latitude: float = Field(ge=-90, le=90)
    longitude: float = Field(ge=-180, le=180)

class ItineraryRequest(BaseModel):
    attractions: list[ItineraryAttraction]
    num_days: int = Field(ge=1, le=30)


class ItineraryDay(BaseModel):
    day: int
    morning: list[ItineraryAttraction]
    afternoon: list[ItineraryAttraction]
    evening: list[ItineraryAttraction]
    distance_km: float


class ItineraryResponse(BaseModel):
    days: list[ItineraryDay]