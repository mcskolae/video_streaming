<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\UI\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class SearchMoviesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['query' => ['nullable', 'string', 'max:200']];
    }

    public function searchQuery(): string
    {
        return trim((string) $this->validated('query', ''));
    }
}
