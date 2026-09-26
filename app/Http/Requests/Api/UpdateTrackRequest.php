<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTrackRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Ownership is enforced separately via TrackPolicy in the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'type' => ['sometimes', 'in:music,podcast,sermon'],
            'genre_id' => ['sometimes', 'nullable', 'exists:genres,id'],
            'album_id' => ['sometimes', 'nullable', 'exists:albums,id'],
            'language' => ['sometimes', 'string', 'max:50'],
            'release_date' => ['sometimes', 'nullable', 'date'],
            'is_explicit' => ['sometimes', 'boolean'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:50'],
            'cover' => ['sometimes', 'image', 'max:4096'],
        ];
    }
}
