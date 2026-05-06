<?php

namespace App\Http\Requests\Admin;

use App\Models\Review;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'author_name' => ['required', 'string', 'max:255'],
            'author_email' => ['nullable', 'email', 'max:255'],
            'author_phone' => ['nullable', 'string', 'max:32'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'status' => ['required', Rule::in(Review::STATUSES)],
            'is_verified_buyer' => ['boolean'],
            'source' => ['nullable', 'string', 'max:64'],
            'moderation_note' => ['nullable', 'string', 'max:1000'],
            'admin_reply' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_verified_buyer' => $this->boolean('is_verified_buyer'),
        ]);
    }
}
