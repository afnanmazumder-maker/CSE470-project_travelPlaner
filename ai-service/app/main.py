from fastapi import FastAPI

app = FastAPI(title="Voyenta Tour Planner")


@app.get("/health")
def health_check():
    return {"status": "ok"}