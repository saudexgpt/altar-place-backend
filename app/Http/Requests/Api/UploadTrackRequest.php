<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Validator;

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
     * The 'file' rule's "failed to upload" message hides *why* — it fails
     * identically whether PHP rejected a real upload (size/temp-dir/disk) or
     * no file arrived under this field name at all (a request-construction
     * bug). Logs enough to tell those apart from the app logs alone, without
     * shell access to the production container.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $validator->errors()->has('audio')) {
                return;
            }

            $audio = $this->file('audio');

            // error, not warning: production's LOG_LEVEL=error (see
            // .env.production.example) would otherwise drop this below the
            // configured threshold and silently discard it.
            Log::error('Track upload rejected: audio field failed validation', [
                'has_file' => $this->hasFile('audio'),
                'file_value_type' => get_debug_type($audio),
                'is_uploaded_file_instance' => $audio instanceof UploadedFile,
                'php_error_code' => $audio instanceof UploadedFile ? $audio->getError() : null,
                'php_error_message' => $audio instanceof UploadedFile ? $audio->getErrorMessage() : null,
                'content_type_header' => $this->header('Content-Type'),
                'content_length_header' => $this->header('Content-Length'),
                'non_file_input_keys' => array_keys($this->except(['audio', 'cover'])),
                'user_id' => $this->user()?->id,
            ]);
        });
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
