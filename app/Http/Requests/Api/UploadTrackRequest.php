<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UploadTrackRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', 'in:music,podcast,sermon'],
            'genre_id' => ['nullable', 'exists:genres,id'],
            'album_id' => ['nullable', 'exists:albums,id'],
            'language' => ['nullable', 'string', 'max:50'],
            'release_date' => ['nullable', 'date'],
            'is_explicit' => ['nullable', 'boolean'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            // 200MB — long-form sermons/podcasts can comfortably run past an
            // hour at a reasonable bitrate; matches docker/nginx.conf and
            // docker/php.ini's upload limits.
            'audio' => ['required', 'file', 'mimes:mp3,wav,m4a,ogg,aac', 'max:204800'],
            'cover' => ['nullable', 'image', 'max:4096'],
        ];
    }
}
