<?php

/**
 * Quick test script for Groq service
 * Run: php test_groq.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Testing Groq Service ===\n\n";

$groqService = app(\App\Services\GroqService::class);

echo "1. Groq Service Available: " . ($groqService->isAvailable() ? "✅ YES" : "❌ NO") . "\n";
echo "   Config enabled: " . (config('services.groq.enabled') ? 'true' : 'false') . "\n";
echo "   API Key set: " . (!empty(config('services.groq.api_key')) ? 'true' : 'false') . "\n";
echo "   API Key (first 20 chars): " . substr(config('services.groq.api_key', ''), 0, 20) . "...\n\n";

if (!$groqService->isAvailable()) {
    echo "❌ Groq service is not available. Please check:\n";
    echo "   - GROQ_ENABLED=true in .env\n";
    echo "   - GROQ_API_KEY is set in .env\n";
    echo "   - Run: php artisan config:clear\n";
    exit(1);
}

echo "2. Testing News Impact Analysis...\n";
$testNews = [
    ['title' => 'FOMC Meeting Minutes', 'currency' => 'USD', 'impact' => 'high', 'time' => '14:00', 'ea_status' => null],
    ['title' => 'CPI Data Release', 'currency' => 'USD', 'impact' => 'high', 'time' => '08:30', 'ea_status' => 'danger'],
    ['title' => 'Manufacturing PMI', 'currency' => 'EUR', 'impact' => 'medium', 'time' => '09:00', 'ea_status' => 'caution'],
];
$testDate = '2025-12-10';

try {
    $analysis = $groqService->analyzeNewsImpact($testNews, $testDate);
    echo "   ✅ Analysis completed\n";
    echo "   Status: " . ($analysis['status'] ?? 'N/A') . "\n";
    echo "   Score: " . ($analysis['score'] ?? 'N/A') . "\n";
    echo "   Confidence: " . ($analysis['confidence'] ?? 'N/A') . "\n";
    echo "   Recommendation: " . substr($analysis['recommendation'] ?? 'N/A', 0, 50) . "...\n";
    echo "   Method: " . ($analysis['method'] ?? 'unknown') . "\n\n";

    if (($analysis['confidence'] ?? 0) < 60) {
        echo "⚠️  Confidence < 60%, AI will NOT be used (fallback to rule-based)\n";
    } else {
        echo "✅ Confidence >= 60%, AI will be used!\n";
    }
} catch (\Exception $e) {
    echo "   ❌ Analysis failed: " . $e->getMessage() . "\n";
    echo "   Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";

