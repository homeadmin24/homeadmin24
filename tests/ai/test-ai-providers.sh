#!/bin/bash

# Test AI Providers for HGA Quality Check
# Usage: ./test-ai-providers.sh [dokument-id]

DOKUMENT_ID=${1:-102}
BASE_URL="http://127.0.0.1:8000"

echo "=========================================="
echo "  HGA Quality Check - AI Provider Test"
echo "=========================================="
echo ""
echo "Testing with Document ID: $DOKUMENT_ID"
echo ""

# Check if running in Docker
if command -v docker &> /dev/null && docker compose ps web &> /dev/null; then
    echo "✓ Docker environment detected"
    EXEC_PREFIX="docker compose exec -T web"
else
    EXEC_PREFIX=""
fi

# 1. Check Ollama status
echo "1. Checking Ollama (local LLM)..."
echo "   Command: curl http://ollama:11434/api/tags"

if [ -n "$EXEC_PREFIX" ]; then
    OLLAMA_STATUS=$($EXEC_PREFIX curl -s --max-time 2 http://ollama:11434/api/tags 2>/dev/null)
    if echo "$OLLAMA_STATUS" | grep -q "models"; then
        echo "   Status: ✓ AVAILABLE"
    else
        echo "   Status: ✗ NOT AVAILABLE"
    fi
else
    echo "   Status: ⚠ Cannot test (not in Docker)"
fi

echo ""

# 2. Check Claude status
echo "2. Checking Claude (Anthropic API)..."

if [ -n "$EXEC_PREFIX" ]; then
    CLAUDE_ENABLED=$($EXEC_PREFIX sh -c 'echo $AI_CLAUDE_ENABLED')
    CLAUDE_KEY=$($EXEC_PREFIX sh -c 'echo $ANTHROPIC_API_KEY')

    echo "   AI_CLAUDE_ENABLED: $CLAUDE_ENABLED"
    if [ -n "$CLAUDE_KEY" ]; then
        echo "   API Key: ✓ SET"
        echo "   Status: ✓ AVAILABLE"
    else
        echo "   API Key: ✗ NOT SET"
        echo "   Status: ✗ NOT AVAILABLE"
        echo ""
        echo "   To enable Claude:"
        echo "   1. Get API key from https://console.anthropic.com/"
        echo "   2. Add to .env: ANTHROPIC_API_KEY=sk-ant-..."
        echo "   3. Set AI_CLAUDE_ENABLED=true"
        echo "   4. Restart: docker compose restart web"
    fi
else
    echo "   Status: ⚠ Cannot test (not in Docker)"
fi

echo ""
echo "=========================================="
echo ""

# 3. Offer to test endpoints
echo "Available test URLs:"
echo ""
echo "View prompt only (instant):"
echo "  $BASE_URL/dokument/$DOKUMENT_ID/quality-check-debug"
echo ""
echo "Test with Ollama (60-90 seconds):"
echo "  $BASE_URL/dokument/$DOKUMENT_ID/quality-check-debug?full=1&provider=ollama"
echo ""
echo "Test with Claude (5-10 seconds):"
echo "  $BASE_URL/dokument/$DOKUMENT_ID/quality-check-debug?full=1&provider=claude"
echo ""

# Ask if user wants to test now
read -p "Test Ollama now? (y/N): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo ""
    echo "Testing Ollama (this will take 60-90 seconds)..."
    echo "Started at: $(date +%H:%M:%S)"

    if [ -n "$EXEC_PREFIX" ]; then
        $EXEC_PREFIX curl -s "$BASE_URL/dokument/$DOKUMENT_ID/quality-check-debug?full=1&provider=ollama" \
            | head -100
    else
        curl -s "$BASE_URL/dokument/$DOKUMENT_ID/quality-check-debug?full=1&provider=ollama" \
            | head -100
    fi

    echo ""
    echo "Completed at: $(date +%H:%M:%S)"
fi

echo ""
echo "Done!"
