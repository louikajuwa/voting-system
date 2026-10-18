<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

abstract class Controller
{
    /**
     * Uniform success envelope: { result, message, data }.
     * `data` is always an object so the frontend reads it consistently.
     * (Validation errors are the one exception — Laravel returns native 422
     * { message, errors } for AJAX, which the forms map to inline field errors.)
     */
    protected function ok(string $message = '', array $data = []): JsonResponse
    {
        return response()->json([
            'result' => 'success',
            'message' => $message ?: null,
            'data' => (object) $data,
        ]);
    }

    /** Uniform error envelope: { result, message } with an HTTP status. */
    protected function fail(string $message, int $status = 400): JsonResponse
    {
        return response()->json(['result' => 'error', 'message' => $message], $status);
    }

    /** Uniform paginated list envelope: { result, data: { rows, page, last_page, total, from, to } }. */
    protected function paginated(\Illuminate\Contracts\Pagination\LengthAwarePaginator $p, callable $map): JsonResponse
    {
        return $this->ok('', [
            'rows' => collect($p->items())->map($map)->values(),
            'page' => $p->currentPage(),
            'last_page' => $p->lastPage(),
            'total' => $p->total(),
            'from' => $p->firstItem(),
            'to' => $p->lastItem(),
        ]);
    }

    /**
     * Respond to a mutation: JSON envelope for AJAX callers, redirect for a
     * normal form post (keeps progressive-enhancement + existing tests working).
     */
    protected function respond(string $redirectTo, string $message, array $data = []): JsonResponse|RedirectResponse
    {
        if (request()->expectsJson()) {
            return $this->ok($message, [...$data, 'redirect' => $redirectTo]);
        }

        return redirect($redirectTo)->with('status', $message);
    }
}
