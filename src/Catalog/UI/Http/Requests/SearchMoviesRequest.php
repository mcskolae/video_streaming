<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\UI\Http\Requests;

use HomeFlix\Catalog\Domain\Model\TitleType;
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
        return [
            'query' => ['nullable', 'string', 'max:200'],
            'type' => ['nullable', 'in:movie,series'],
        ];
    }

    public function titleType(): TitleType
    {
        return $this->validated('type') === 'series' ? TitleType::Series : TitleType::Movie;
    }

    public function searchQuery(): string
    {
        return trim((string) $this->validated('query', ''));
    }
}
