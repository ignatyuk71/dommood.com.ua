<?php

namespace App\Http\Requests\Admin;

use App\Models\ProductAttribute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductAttributeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $attribute = $this->route('attribute');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9-]+$/i',
                Rule::unique('attributes', 'slug')->ignore($attribute?->id),
            ],
            'type' => ['required', 'string', Rule::in(ProductAttribute::TYPES)],
            'is_filterable' => ['boolean'],
            'is_variant_option' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'values' => ['nullable', 'array', 'max:100'],
            'values.*.id' => ['nullable', 'integer', 'exists:attribute_values,id'],
            'values.*.value' => ['nullable', 'string', 'max:255'],
            'values.*.slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/i'],
            'values.*.color_hex' => ['nullable', 'string', 'max:16', 'regex:/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/'],
            'values.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_filterable' => $this->boolean('is_filterable'),
            'is_variant_option' => $this->boolean('is_variant_option'),
            'type' => $this->input('type') ?: ProductAttribute::TYPE_SELECT,
        ]);
    }
}
