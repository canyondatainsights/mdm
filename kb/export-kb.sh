#!/usr/bin/env bash
# Export the knowledge base as a portable, self-contained Markdown archive.
# The KB travels as plain text — drop the archive into any RAG system, wiki, or AI project.
# See MANIFEST.md ("Format and portability").
set -euo pipefail

KB_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
STAMP="$(date +%Y-%m-%d)"
OUT_DIR="${KB_DIR}/output/exports"
ARCHIVE="${OUT_DIR}/mdm-kb-${STAMP}.tar.gz"

mkdir -p "${OUT_DIR}"

# Include the curated wiki + the operating docs; exclude generated output and the Q&A log.
tar -czf "${ARCHIVE}" \
  -C "${KB_DIR}" \
  --exclude='output' \
  MANIFEST.md README.md _INDEX.md _CHANGELOG.md wiki raw

echo "Wrote ${ARCHIVE}"
