<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChartAnalysis;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ChartAnalysisController extends Controller
{
    /**
     * Get all chart analyses for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $analyses = ChartAnalysis::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['analyses' => $analyses]);
    }

    /**
     * Get chart analyses for a specific symbol and interval.
     */
    public function getBySymbol(Request $request, string $symbol, string $interval): JsonResponse
    {
        $analyses = ChartAnalysis::where('user_id', $request->user()->id)
            ->where('symbol', $symbol)
            ->where('interval', $interval)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['analyses' => $analyses]);
    }

    /**
     * Get a specific chart analysis.
     */
    public function show(Request $request, ChartAnalysis $analysis): JsonResponse
    {
        if ($analysis->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json(['analysis' => $analysis]);
    }

    /**
     * Save a new chart analysis.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'symbol' => 'required|string|max:100',
            'interval' => 'required|string|max:10',
            'title' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'drawings' => 'nullable|array',
            'tradingview_state' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $analysis = ChartAnalysis::create([
            'user_id' => $request->user()->id,
            'symbol' => $request->symbol,
            'interval' => $request->interval,
            'title' => $request->title,
            'notes' => $request->notes,
            'drawings' => $request->drawings,
            'tradingview_state' => $request->tradingview_state,
        ]);

        return response()->json(['analysis' => $analysis], 201);
    }

    /**
     * Update an existing chart analysis.
     */
    public function update(Request $request, ChartAnalysis $analysis): JsonResponse
    {
        if ($analysis->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'drawings' => 'nullable|array',
            'tradingview_state' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $analysis->update($request->only(['title', 'notes', 'drawings', 'tradingview_state']));

        return response()->json(['analysis' => $analysis]);
    }

    /**
     * Delete a chart analysis.
     */
    public function destroy(Request $request, ChartAnalysis $analysis): JsonResponse
    {
        if ($analysis->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $analysis->delete();

        return response()->json(['message' => 'Analysis deleted successfully']);
    }
}

