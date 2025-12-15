#!/bin/bash
echo "=========================================="
echo "Testing ML Setup"
echo "=========================================="

# Test 1: Model exists
echo ""
echo "Test 1: Checking model..."
if [ -f "ml-service/models/prediction2_model_latest.pkl" ]; then
    echo "✅ Model found: ml-service/models/prediction2_model_latest.pkl"
else
    echo "❌ Model not found"
    echo "   Run: cd ml-service && python train_model.py"
fi

# Test 2: ML API running
echo ""
echo "Test 2: Checking ML API..."
if curl -s http://localhost:8000/health > /dev/null 2>&1; then
    HEALTH=$(curl -s http://localhost:8000/health)
    echo "✅ ML API is running"
    echo "   Response: $HEALTH"
else
    echo "❌ ML API not running"
    echo "   Run: cd ml-service && python ml_api.py"
fi

# Test 3: Laravel config
echo ""
echo "Test 3: Checking Laravel config..."
if [ -f "backend/.env" ]; then
    if grep -q "ML_API_ENABLED=true" backend/.env; then
        echo "✅ ML_API_ENABLED=true"
    else
        echo "⚠️  ML_API_ENABLED not set to true"
        echo "   Add to backend/.env: ML_API_ENABLED=true"
    fi
    
    if grep -q "ML_API_URL" backend/.env; then
        ML_URL=$(grep "ML_API_URL" backend/.env | cut -d '=' -f2)
        echo "✅ ML_API_URL=$ML_URL"
    else
        echo "⚠️  ML_API_URL not configured"
        echo "   Add to backend/.env: ML_API_URL=http://localhost:8000"
    fi
else
    echo "⚠️  backend/.env not found"
fi

echo ""
echo "=========================================="
