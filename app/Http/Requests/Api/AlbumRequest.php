<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AlbumRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Ownership is enforced separately via AlbumPolicy in the controller.
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
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'type' => ['sometimes', 'required', 'in:album,podcast_show'],
            'genre_id' => ['sometimes', 'nullable', 'exists:genres,id'],
            'release_year' => ['sometimes', 'nullable', 'integer', 'min:1900', 'max:2100'],
            'cover' => ['sometimes', 'image', 'max:4096'],
        ];
    }
}
