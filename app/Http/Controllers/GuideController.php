<?php

namespace App\Http\Controllers;

use App\Services\PlatformGuide;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class GuideController extends Controller
{
    public function chat(Request $request, PlatformGuide $guide): JsonResponse
    {
        abort_unless($guide->enabled(), 404);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'history' => ['sometimes', 'array', 'max:12'],
            'history.*.role' => ['required_with:history', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:4000'],
        ]);

        $history = collect($data['history'] ?? [])
            ->map(fn (array $row) => [
                'role' => $row['role'],
                'content' => trim($row['content']),
            ])
            ->filter(fn (array $row) => $row['content'] !== '')
            ->values()
            ->all();

        $messages = [
            ...$history,
            ['role' => 'user', 'content' => trim($data['message'])],
        ];

        try {
            $reply = $guide->reply($messages);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['reply' => $reply]);
    }
}
