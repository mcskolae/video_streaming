<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\UI\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class ImportMovieRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['tmdb_id' => ['required', 'integer', 'min:1', 'max:2147483647']];
    }

    public function tmdbId(): int
    {
        return (int) $this->validated('tmdb_id');
    }
}
