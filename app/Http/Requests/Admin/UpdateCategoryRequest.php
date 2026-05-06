<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $category = $this->route('category');

        return [
            'parent_id' => ['nullable', 'integer', 'exists:categories,id', Rule::notIn([$category?->id])],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9-]+$/i',
                Rule::unique('categories', 'slug')->ignore($category?->id),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'delete_image' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'seo_text' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'delete_image' => $this->boolean('delete_image'),
            'parent_id' => $this->input('parent_id') ?: null,
        ]);
    }
}
