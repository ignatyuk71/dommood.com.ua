<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSizeChartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sizeChart = $this->route('size_chart');

        return [
            'title' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[a-z0-9_.-]+$/i',
                Rule::unique('size_charts', 'code')->ignore($sizeChart?->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'content_json' => ['nullable', 'array'],
            'content_json.columns' => ['nullable', 'array', 'max:20'],
            'content_json.columns.*' => ['nullable', 'string', 'max:80'],
            'content_json.rows' => ['nullable', 'array', 'max:100'],
            'content_json.rows.*' => ['array', 'max:20'],
            'content_json.rows.*.*' => ['nullable', 'string', 'max:80'],
            'content_html' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'delete_image' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'delete_image' => $this->boolean('delete_image'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
