<?php

namespace App\Services;

use App\Models\AdminActivityLog;
use App\Models\AttributeValue;
use App\Models\DeliveryTariff;
use App\Models\FilterSeoPage;
use App\Models\MarketingIntegration;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductFeedConfig;
use App\Models\Review;
use App\Models\SeoRedirect;
use App\Models\SeoSetting;
use App\Models\SeoTemplate;
use App\Models\ShippingProviderSetting;
use App\Models\SiteSetting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Throwable;

class AdminActivityLogger
{
    public function log(
        Request $request,
        string $event,
        ?Model $subject = null,
        array $oldValues = [],
        array $newValues = [],
        array $metadata = [],
        ?string $description = null,
    ): void {
        try {
            AdminActivityLog::query()->create([
                'user_id' => $request->user()?->id,
                'event' => $event,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'subject_label' => $subject ? $this->subjectLabel($subject) : null,
                'description' => $description,
                'old_values' => $oldValues !== [] ? $this->cleanValues($oldValues) : null,
                'new_values' => $newValues !== [] ? $this->cleanValues($newValues) : null,
                'metadata' => $metadata !== [] ? $this->cleanValues($metadata) : null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function subjectLabel(Model $subject): string
    {
        if ($subject instanceof Order) {
            return 'Замовлення #'.$subject->order_number;
        }

        if ($subject instanceof Product) {
            return $subject->name;
        }

        if ($subject instanceof Menu) {
            return 'Меню: '.$subject->name;
        }

        if ($subject instanceof MenuItem) {
            return 'Пункт меню: '.$subject->title;
        }

        if ($subject instanceof AttributeValue) {
            return 'Значення характеристики: '.$subject->value;
        }

        if ($subject instanceof DeliveryTariff) {
            return 'Тариф: '.$subject->name;
        }

        if ($subject instanceof SeoRedirect) {
            return 'Редірект: '.$subject->source_path;
        }

        if ($subject instanceof SeoSetting) {
            return 'SEO: '.$subject->section.' / '.$subject->key;
        }

        if ($subject instanceof SeoTemplate) {
            return 'SEO шаблон: '.$subject->entity_type.' / '.$subject->field;
        }

        if ($subject instanceof FilterSeoPage) {
            return 'Filter SEO: '.$subject->slug;
        }

        if ($subject instanceof SiteSetting) {
            return 'Налаштування сайту: '.$subject->section;
        }

        if ($subject instanceof ShippingProviderSetting) {
            return 'Перевізник: '.$subject->name;
        }

        if ($subject instanceof MarketingIntegration) {
            return 'Tracking: '.$subject->provider;
        }

        if ($subject instanceof ProductFeedConfig) {
            return 'Product Feed: '.$subject->channel.' / товар #'.$subject->product_id;
        }

        if ($subject instanceof Review) {
            return 'Відгук: '.$subject->author_name;
        }

        if ($subject instanceof Role) {
            return 'Роль: '.$subject->name;
        }

        foreach (['name', 'title', 'value', 'email', 'code', 'slug', 'section', 'provider'] as $field) {
            $value = $subject->getAttribute($field);

            if (filled($value)) {
                return class_basename($subject).': '.$value;
            }
        }

        return class_basename($subject).' #'.$subject->getKey();
    }

    private function cleanValues(array $values): array
    {
        return collect($values)
            ->mapWithKeys(fn ($value, $key): array => [
                $key => $this->isSensitiveKey($key) ? $this->hiddenValue($value) : $this->normalizeValue($value),
            ])
            ->all();
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_array($value)) {
            return $this->cleanValues($value);
        }

        return $value;
    }

    private function isSensitiveKey(string|int $key): bool
    {
        $normalized = strtolower((string) $key);

        foreach (['password', 'secret', 'token', 'private_key', 'api_key', 'service_account', 'webhook', 'remember_token'] as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function hiddenValue(mixed $value): mixed
    {
        if ($value === null || $value === '' || $value === []) {
            return $value;
        }

        return '[приховано]';
    }
}
