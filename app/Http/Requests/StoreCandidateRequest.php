<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is gated by the role:Admin|Manager middleware
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'party' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:500'],
            'manifesto' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['approved', 'pending', 'withdrawn'])],
            'photo' => ['nullable', 'image', 'max:2048'], // 2 MB; shown on the ballot
        ];
    }
}
