"""Optional local embedding sidecar.

Lets the Laravel API compute embeddings locally (no Voyage key / no external
network) by setting EMBEDDINGS_DRIVER=sidecar. The model's output dimension must
match the API's EMBEDDINGS_DIM (and the pgvector column), so the default model is
1024-dim to line up with the Voyage default.
"""
import os
from fastapi import FastAPI
from pydantic import BaseModel
from sentence_transformers import SentenceTransformer

MODEL_NAME = os.getenv("EMBED_MODEL", "BAAI/bge-large-en-v1.5")  # 1024-dim

app = FastAPI(title="MDM Embeddings Sidecar")
model = SentenceTransformer(MODEL_NAME)


class EmbedRequest(BaseModel):
    texts: list[str]


@app.get("/health")
def health():
    return {"ok": True, "model": MODEL_NAME, "dim": model.get_sentence_embedding_dimension()}


@app.post("/embed")
def embed(req: EmbedRequest):
    vectors = model.encode(req.texts, normalize_embeddings=True).tolist()
    return {"embeddings": vectors}
