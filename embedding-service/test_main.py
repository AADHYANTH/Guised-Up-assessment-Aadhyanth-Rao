from fastapi.testclient import TestClient

from main import EMBEDDING_DIM, app

client = TestClient(app)


def test_health():
    response = client.get("/health")
    assert response.status_code == 200
    assert response.json() == {"status": "ok"}


def test_embed_returns_384_floats():
    response = client.post("/embed", json={"text": "Guised Up sample post"})
    assert response.status_code == 200

    embedding = response.json()["embedding"]
    assert isinstance(embedding, list)
    assert len(embedding) == EMBEDDING_DIM
    assert all(isinstance(value, float) for value in embedding)


def test_embed_is_deterministic():
    payload = {"text": "same text always same vector"}

    first = client.post("/embed", json=payload).json()["embedding"]
    second = client.post("/embed", json=payload).json()["embedding"]

    assert first == second


def test_different_text_produces_different_vector():
    first = client.post("/embed", json={"text": "alpha post"}).json()["embedding"]
    second = client.post("/embed", json={"text": "beta post"}).json()["embedding"]

    assert first != second
