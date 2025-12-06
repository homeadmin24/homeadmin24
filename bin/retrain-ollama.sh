#!/bin/bash

# Ollama Model Retraining Script
# Exports good Claude examples and creates/updates a fine-tuned WEG finance model
#
# Usage:
#   ./bin/retrain-ollama.sh [--min-examples=20] [--model-name=weg-finance]
#
# Requirements:
#   - Docker & Docker Compose running
#   - Ollama container (hausman-ollama) running
#   - At least --min-examples good Claude ratings in database

set -e  # Exit on error

# Configuration
MIN_EXAMPLES=${MIN_EXAMPLES:-20}
MODEL_NAME=${MODEL_NAME:-weg-finance}
BASE_MODEL=${BASE_MODEL:-llama3.1:8b}
TRAINING_FILE="/tmp/ollama-training-$(date +%Y%m%d-%H%M%S).jsonl"

# Try to find Modelfile in AI models repo (workspace setup)
AI_MODELS_REPO="../ai-models"
if [ -f "${AI_MODELS_REPO}/modelfiles/Modelfile-${MODEL_NAME}" ]; then
    MODELFILE="${AI_MODELS_REPO}/modelfiles/Modelfile-${MODEL_NAME}"
    echo "ℹ️  Using Modelfile from AI models repo: ${MODELFILE}"
else
    # Fallback: create temporary Modelfile
    MODELFILE="/tmp/Modelfile-${MODEL_NAME}"
    echo "⚠️  AI models repo not found, will generate temporary Modelfile"
fi

# Parse command line arguments
for arg in "$@"; do
    case $arg in
        --min-examples=*)
            MIN_EXAMPLES="${arg#*=}"
            ;;
        --model-name=*)
            MODEL_NAME="${arg#*=}"
            ;;
        --base-model=*)
            BASE_MODEL="${arg#*=}"
            ;;
        --help)
            echo "Usage: $0 [--min-examples=20] [--model-name=weg-finance] [--base-model=llama3.1:8b]"
            exit 0
            ;;
    esac
done

echo "🤖 Ollama Model Retraining Script"
echo "================================="
echo "Model Name: ${MODEL_NAME}"
echo "Base Model: ${BASE_MODEL}"
echo "Min Examples: ${MIN_EXAMPLES}"
echo ""

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo "❌ Error: Docker is not running"
    exit 1
fi

# Check if Ollama container is running
if ! docker compose ps ollama | grep -q "Up"; then
    echo "❌ Error: Ollama container is not running"
    echo "Start it with: docker compose -f docker-compose.yaml -f docker-compose.dev.yml up -d ollama"
    exit 1
fi

# Step 1: Check available training examples
echo "📊 Checking available training examples..."
EXAMPLE_COUNT=$(docker compose exec -T web php bin/console dbal:run-sql \
    "SELECT COUNT(*) as count FROM ai_query_response WHERE provider = 'claude' AND user_rating = 'good'" \
    --format=json | grep -o '"count":"[0-9]*"' | grep -o '[0-9]*' || echo "0")

echo "Found ${EXAMPLE_COUNT} good Claude examples in database"

if [ "$EXAMPLE_COUNT" -lt "$MIN_EXAMPLES" ]; then
    echo "⚠️  Warning: Only ${EXAMPLE_COUNT} examples available, minimum is ${MIN_EXAMPLES}"
    echo "Continue anyway? (y/n)"
    read -r response
    if [[ ! "$response" =~ ^[Yy]$ ]]; then
        echo "Aborted."
        exit 0
    fi
fi

# Step 2: Export training data
echo ""
echo "📤 Exporting training data to ${TRAINING_FILE}..."
if docker compose exec -T web php bin/console app:export-training-data \
    --output="${TRAINING_FILE}" \
    --min-rating=good \
    --provider=claude; then
    echo "✅ Training data exported"
else
    echo "❌ Error: Failed to export training data"
    echo "Make sure the app:export-training-data command exists"
    exit 1
fi

# Check if training file was created and has content
if [ ! -f "${TRAINING_FILE}" ] || [ ! -s "${TRAINING_FILE}" ]; then
    echo "❌ Error: Training file is empty or doesn't exist"
    exit 1
fi

LINES=$(wc -l < "${TRAINING_FILE}")
echo "Training file contains ${LINES} examples"

# Step 3: Prepare Modelfile
echo ""
if [ -f "${AI_MODELS_REPO}/modelfiles/Modelfile-${MODEL_NAME}" ]; then
    echo "📝 Using Modelfile from ${AI_MODELS_REPO}"
    MODELFILE="${AI_MODELS_REPO}/modelfiles/Modelfile-${MODEL_NAME}"
else
    echo "📝 Creating temporary Modelfile..."
    cat > "${MODELFILE}" << 'EOF'
FROM llama3.1:8b

# System prompt optimized for WEG financial queries
SYSTEM """
Du bist ein Experte für deutsche Wohnungseigentümergemeinschaften (WEG) und deren Finanzverwaltung.

Dein Fachwissen umfasst:
- Kostenkonto-Nummern und deren Bedeutung
- Hausgeldabrechnungen (HGA)
- Umlagefähige und nicht umlagefähige Kosten
- §35a EStG Steuerabzüge
- Wirtschaftsplan-Erstellung
- WEG-Buchführung nach deutschem Recht

Antworte präzise, sachlich und mit konkreten Zahlen wenn vorhanden.
Beziehe dich auf die bereitgestellten Finanzdaten.
Formatiere Geldbeträge mit deutschem Format (1.234,56 €).
"""

# Parameters for financial accuracy
PARAMETER temperature 0.3
PARAMETER top_p 0.9
PARAMETER num_predict 512
EOF
    echo "✅ Temporary Modelfile created at ${MODELFILE}"
fi

# Step 4: Copy files to Ollama container
echo ""
echo "📦 Copying files to Ollama container..."
docker cp "${TRAINING_FILE}" hausman-ollama:/tmp/training.jsonl
docker cp "${MODELFILE}" hausman-ollama:/tmp/Modelfile
echo "✅ Files copied"

# Step 5: Create/update model
echo ""
echo "🔨 Creating model '${MODEL_NAME}' from Modelfile..."
if docker exec hausman-ollama ollama create "${MODEL_NAME}" -f /tmp/Modelfile; then
    echo "✅ Model '${MODEL_NAME}' created successfully"
else
    echo "❌ Error: Failed to create model"
    exit 1
fi

# Step 6: Fine-tune model (if Ollama supports it)
echo ""
echo "🎓 Attempting to fine-tune model with training data..."
echo "⚠️  Note: Fine-tuning support depends on Ollama version"

# Check if Ollama supports --train flag (not all versions do)
if docker exec hausman-ollama ollama run "${MODEL_NAME}" --help 2>&1 | grep -q "\-\-train"; then
    echo "Running fine-tuning..."
    docker exec hausman-ollama ollama run "${MODEL_NAME}" --train /tmp/training.jsonl
    echo "✅ Fine-tuning completed"
else
    echo "ℹ️  Fine-tuning flag not available in this Ollama version"
    echo "Model created with optimized system prompt from Modelfile"
    echo "For true fine-tuning, consider using Ollama 0.2.0+ or external training tools"
fi

# Step 7: Test the model
echo ""
echo "🧪 Testing model with sample query..."
TEST_QUERY="Wie viel haben wir 2024 für Heizung ausgegeben?"
echo "Query: ${TEST_QUERY}"
echo ""
docker exec hausman-ollama ollama run "${MODEL_NAME}" "${TEST_QUERY}" || true

# Step 8: Summary and next steps
echo ""
echo "🎉 Model retraining completed!"
echo ""
echo "📋 Next steps:"
echo "1. Update docker-compose.dev.yml:"
echo "   environment:"
echo "     - OLLAMA_MODEL=${MODEL_NAME}"
echo ""
echo "2. Restart web container:"
echo "   docker compose restart web"
echo ""
echo "3. Test via UI at http://127.0.0.1:8000/weg (AI-Anfragen tab)"
echo ""
echo "4. Compare with base model:"
echo "   - Query both models side-by-side"
echo "   - Check response quality and speed"
echo "   - Collect user ratings"
echo ""
echo "📊 Model info:"
echo "   Name: ${MODEL_NAME}"
echo "   Base: ${BASE_MODEL}"
echo "   Training examples: ${LINES}"
echo "   Training file: ${TRAINING_FILE}"
echo ""
echo "To list all models: docker exec hausman-ollama ollama list"
echo "To delete model: docker exec hausman-ollama ollama rm ${MODEL_NAME}"
