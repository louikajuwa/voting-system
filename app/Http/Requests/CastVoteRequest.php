<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CastVoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // public ballot
    }

    public function rules(): array
    {
        return [
            'candidate_id' => ['required', 'integer'],
            'email' => ['required', 'email:rfc', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'candidate_id.required' => 'Please select a candidate.',
            'email.email' => 'Enter a valid email address.',
        ];
    }
}
