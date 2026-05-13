<?php

namespace App\Http\Requests\Storefront;

use App\Services\SiteSettingsService;
use Illuminate\Foundation\Http\FormRequest;

class StoreCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $settings = app(SiteSettingsService::class)->get('checkout');
        $lastNameRule = ($settings['require_last_name'] ?? false) ? 'required' : 'nullable';
        $phoneRule = ($settings['require_phone'] ?? true) ? 'required' : 'nullable';
        $emailRule = ($settings['require_email'] ?? false) ? 'required' : 'nullable';

        return [
            'customer_first_name' => ['required', 'string', 'max:80'],
            'customer_last_name' => [$lastNameRule, 'string', 'max:80'],
            'customer_phone' => [$phoneRule, 'string', 'max:30', 'regex:/^[0-9+()\\s-]{7,30}$/'],
            'customer_email' => [$emailRule, 'email', 'max:120'],
            'delivery_method' => ['required', 'string', 'max:80'],
            'delivery_city' => ['required', 'string', 'max:120'],
            'delivery_branch' => ['nullable', 'string', 'max:180'],
            'delivery_address' => ['nullable', 'string', 'max:220'],
            'payment_method' => ['required', 'string', 'max:80'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'terms_accepted' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_first_name.required' => 'Вкажіть імʼя отримувача.',
            'customer_phone.required' => 'Вкажіть номер телефону для підтвердження.',
            'customer_phone.regex' => 'Вкажіть коректний номер телефону.',
            'customer_email.email' => 'Вкажіть коректний email.',
            'delivery_method.required' => 'Оберіть спосіб доставки.',
            'delivery_city.required' => 'Вкажіть місто доставки.',
            'payment_method.required' => 'Оберіть спосіб оплати.',
            'terms_accepted.accepted' => 'Підтвердьте згоду з умовами покупки.',
        ];
    }
}
