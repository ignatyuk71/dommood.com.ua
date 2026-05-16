<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'primary_category_id' => ['required', 'integer', 'exists:categories,id'],
            'category_ids' => ['nullable', 'array', 'max:20'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'color_group_id' => ['nullable', 'integer', 'exists:product_color_groups,id'],
            'size_chart_id' => ['nullable', 'integer', 'exists:size_charts,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/i', Rule::unique('products', 'slug')],
            'sku' => ['nullable', 'string', 'max:255', Rule::unique('products', 'sku')],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', Rule::in(Product::STATUSES)],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'old_price' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'cost_price' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'currency' => ['required', 'string', 'size:3'],
            'stock_status' => ['required', 'string', Rule::in(Product::STOCK_STATUSES)],
            'is_featured' => ['boolean'],
            'is_new' => ['boolean'],
            'is_bestseller' => ['boolean'],
            'color_sort_order' => ['nullable', 'integer', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'seo_text' => ['nullable', 'string'],
            'canonical_url' => ['nullable', 'string', 'max:255'],
            'published_at' => ['nullable', 'date'],
            'attribute_value_ids' => ['nullable', 'array', 'max:100'],
            'attribute_value_ids.*' => ['integer', 'exists:attribute_values,id'],
            'variants' => ['nullable', 'array', 'max:100'],
            'variants.*.id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'variants.*.sku' => ['nullable', 'string', 'max:255'],
            'variants.*.size' => ['nullable', 'string', 'max:255'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'variants.*.stock_quantity' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'variants.*.is_active' => ['boolean'],
            'images' => ['nullable', 'array', 'max:20'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:15360'],
            'new_image_keys' => ['nullable', 'array', 'max:20'],
            'new_image_keys.*' => ['string', 'max:80'],
            'image_order' => ['nullable', 'array', 'max:50'],
            'image_order.*' => ['string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'primary_category_id' => $this->input('primary_category_id') ?: null,
            'color_group_id' => $this->input('color_group_id') ?: null,
            'size_chart_id' => $this->input('size_chart_id') ?: null,
            'is_featured' => $this->boolean('is_featured'),
            'is_new' => $this->boolean('is_new'),
            'is_bestseller' => $this->boolean('is_bestseller'),
            'status' => $this->input('status') ?: Product::STATUS_DRAFT,
            'stock_status' => $this->input('stock_status') ?: Product::STOCK_IN_STOCK,
            'currency' => $this->input('currency') ?: 'UAH',
        ]);
    }

    public function messages(): array
    {
        return [
            'images.max' => 'У галереї можна додати не більше 20 фото.',
            'images.*.image' => 'Кожен файл у галереї має бути зображенням.',
            'images.*.mimes' => 'Галерея приймає лише JPG, PNG або WEBP.',
            'images.*.max' => 'Розмір одного фото в галереї не може перевищувати 15 MB.',
        ];
    }
}
