"""
Embedding microservice for Guised Up.

POST /embed  → 384-dim unit vector for post text
GET  /health → liveness check
"""

from __future__ import annotations

import hashlib
from typing import List

import numpy as np
from fastapi import FastAPI
from pydantic import BaseModel, Field

# ---------------------------------------------------------------------------
# SWAP-IN POINT: production embedding backend
# ---------------------------------------------------------------------------
# In production, replace `mock_embed()` with a real model call, e.g.:
#   - sentence-transformers (`all-MiniLM-L6-v2` → 384 dims)
#   - OpenAI embeddings API (`text-embedding-3-small` truncated/padded to 384)
#
# Example real implementation (install sentence-transformers, then uncomment):
#
#   from sentence_transformers import SentenceTransformer
#
#   _model = SentenceTransformer("all-MiniLM-L6-v2")
#
#   def real_embed(text: str) -> List[float]:
#       vector = _model.encode(text, normalize_embeddings=True)
#       return vector.astype(float).tolist()
#
# Then change `generate_embedding` below to call `real_embed(text)` instead
# of `mock_embed(text)`.
# ---------------------------------------------------------------------------

EMBEDDING_DIM = 384

app = FastAPI(title="Guised Up Embedding Service", version="0.1.0")


class EmbedRequest(BaseModel):
    text: str = Field(..., min_length=1)


class EmbedResponse(BaseModel):
    embedding: List[float]


class HealthResponse(BaseModel):
    status: str


def mock_embed(text: str) -> List[float]:
    """
    Deterministic hash-based mock embedding.

    Seeds a PRNG from SHA-256 of the input so identical text always yields the
    same 384-d unit vector. Suitable for local/dev without API credits.
    """
    digest = hashlib.sha256(text.encode("utf-8")).digest()
    seed = int.from_bytes(digest[:8], byteorder="big", signed=False)
    rng = np.random.default_rng(seed)

    vector = rng.standard_normal(EMBEDDING_DIM).astype(np.float64)
    norm = np.linalg.norm(vector)
    if norm == 0:
        vector[0] = 1.0
    else:
        vector = vector / norm

    return vector.tolist()


def generate_embedding(text: str) -> List[float]:
    """Single call site for embedding generation — swap mock ↔ real here."""
    return mock_embed(text)
    # return real_embed(text)


@app.get("/health", response_model=HealthResponse)
def health() -> HealthResponse:
    return HealthResponse(status="ok")


@app.post("/embed", response_model=EmbedResponse)
def embed(payload: EmbedRequest) -> EmbedResponse:
    return EmbedResponse(embedding=generate_embedding(payload.text))
