<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSiteSettingsRequest;
use App\Services\AdminActivityLogger;
use App\Services\SiteSettingsService;
use App\Support\AdminPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SiteSettingsController extends Controller
{
    public function __construct(private readonly SiteSettingsService $settings) {}

    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('admin.settings.site.show', $this->firstAllowedSection($request));
    }

    public function show(Request $request, string $section): Response
    {
        abort_unless(in_array($section, $this->settings->allowedSections(), true), 404);
        abort_unless($request->user()?->can($this->sectionPermission($section)), 403);

        return Inertia::render('Admin/Settings/SiteSettings', [
            'section' => $section,
            'tabs' => $this->tabs($request, $section),
            'meta' => $this->sectionMeta($section),
            'settings' => $this->publicSettings($section),
            'schema' => $this->schema($section),
        ]);
    }

    public function update(UpdateSiteSettingsRequest $request, string $section): RedirectResponse
    {
        abort_unless(in_array($section, $this->settings->allowedSections(), true), 404);

        $oldSettings = $this->settings->get($section);
        $newSettings = $this->settings->set($section, $request->validatedSettings($this->settings));
        $changedFields = collect(array_keys(array_replace($oldSettings, $newSettings)))
            ->filter(fn (string $key): bool => ($oldSettings[$key] ?? null) !== ($newSettings[$key] ?? null))
            ->values()
            ->all();

        app(AdminActivityLogger::class)->log(
            $request,
            'settings.site_updated',
            oldValues: ['section' => $this->sectionMeta($section)['title'] ?? $section],
            newValues: [
                'section' => $this->sectionMeta($section)['title'] ?? $section,
                'changed_fields' => $changedFields ?: ['без змін'],
            ],
            description: 'Менеджер оновив налаштування сайту',
        );

        return redirect()
            ->route('admin.settings.site.show', $section)
            ->with('success', 'Налаштування збережено');
    }

    private function tabs(Request $request, string $activeSection): array
    {
        return collect($this->settings->sections())
            ->filter(fn (array $section, string $key): bool => (bool) $request->user()?->can($this->sectionPermission($key)))
            ->map(fn (array $section, string $key): array => [
                'key' => $key,
                'label' => $section['label'],
                'description' => $section['description'],
                'active' => $key === $activeSection,
                'route' => route('admin.settings.site.show', $key),
            ])
            ->values()
            ->all();
    }

    private function firstAllowedSection(Request $request): string
    {
        foreach ($this->settings->allowedSections() as $section) {
            if ($request->user()?->can($this->sectionPermission($section))) {
                return $section;
            }
        }

        abort(403);
    }

    private function sectionPermission(string $section): string
    {
        return match ($section) {
            'store' => AdminPermissions::SETTINGS_STORE_MANAGE,
            'checkout' => AdminPermissions::SETTINGS_CHECKOUT_MANAGE,
            'integrations' => AdminPermissions::SETTINGS_INTEGRATIONS_MANAGE,
            'payments' => AdminPermissions::SETTINGS_PAYMENTS_MANAGE,
            'security' => AdminPermissions::SETTINGS_SECURITY_MANAGE,
            'system' => AdminPermissions::SETTINGS_SYSTEM_MANAGE,
            default => AdminPermissions::SETTINGS_STORE_MANAGE,
        };
    }

    private function sectionMeta(string $section): array
    {
        return match ($section) {
            'store' => [
                'title' => 'Магазин',
                'eyebrow' => 'Налаштування',
                'description' => 'Основні дані бренду, контактів, валюти і режиму роботи.',
                'accent' => '#7561f7',
            ],
            'checkout' => [
                'title' => 'Checkout',
                'eyebrow' => 'Налаштування',
                'description' => 'Правила оформлення замовлення, обовʼязкові поля і мінімальна сума.',
                'accent' => '#0ea5e9',
            ],
            'integrations' => [
                'title' => 'Інтеграції',
                'eyebrow' => 'Налаштування',
                'description' => 'Поштові й операційні підключення без SEO, tracking і Нової пошти.',
                'accent' => '#ec4899',
            ],
            'payments' => [
                'title' => 'Платежі',
                'eyebrow' => 'Налаштування',
                'description' => 'Підключення провайдерів оплати. Методи для checkout вмикаються окремо в “Оплата та доставка”.',
                'accent' => '#16a34a',
            ],
            'security' => [
                'title' => 'Безпека',
                'eyebrow' => 'Налаштування',
                'description' => 'Контроль персоналу, сесій, allowlist і повідомлень про входи.',
                'accent' => '#10b981',
            ],
            'system' => [
                'title' => 'Система',
                'eyebrow' => 'Налаштування',
                'description' => 'Кеш, retention, sitemap, feeds і технічні прапорці.',
                'accent' => '#334155',
            ],
            default => [],
        };
    }

    private function publicSettings(string $section): array
    {
        $settings = $this->settings->get($section);

        if (! in_array($section, ['integrations', 'payments'], true)) {
            return $settings;
        }

        foreach (['smtp_password', 'telegram_bot_token', 'liqpay_private_key', 'monobank_token'] as $secret) {
            if (array_key_exists($secret, $settings)) {
                $settings[$secret] = '';
            }
        }

        return $settings;
    }

    private function schema(string $section): array
    {
        $secretMeta = fn (string $key, ?string $secretSection = null): array => [
            'exists' => $this->settings->hasSecret($secretSection ?? $section, $key),
            'masked' => $this->settings->maskedSecret($secretSection ?? $section, $key),
        ];

        return match ($section) {
            'store' => [
                'summary' => [
                    ['label' => 'Валюта', 'value' => $this->settings->get($section)['currency']],
                    ['label' => 'Домен', 'value' => $this->settings->get($section)['domain'] ?: 'Не задано'],
                    ['label' => 'Режим', 'value' => $this->settings->get($section)['maintenance_mode'] ? 'Технічні роботи' : 'Активний'],
                ],
                'groups' => [
                    [
                        'title' => 'Бренд і контакти',
                        'fields' => [
                            ['name' => 'store_name', 'label' => 'Назва магазину', 'type' => 'text', 'placeholder' => 'DomMood', 'span' => 1],
                            ['name' => 'legal_name', 'label' => 'Юридична назва', 'type' => 'text', 'placeholder' => 'ФОП / ТОВ', 'span' => 1],
                            ['name' => 'domain', 'label' => 'Домен', 'type' => 'url', 'placeholder' => 'https://dommood.com.ua', 'span' => 2],
                            ['name' => 'support_email', 'label' => 'Email підтримки', 'type' => 'email', 'placeholder' => 'support@dommood.com.ua', 'span' => 1],
                            ['name' => 'support_phone', 'label' => 'Телефон', 'type' => 'text', 'placeholder' => '+380...', 'span' => 1],
                        ],
                    ],
                    [
                        'title' => 'Операційні параметри',
                        'fields' => [
                            ['name' => 'currency', 'label' => 'Валюта', 'type' => 'select', 'options' => $this->options(['UAH', 'PLN', 'USD', 'EUR']), 'span' => 1],
                            ['name' => 'timezone', 'label' => 'Timezone', 'type' => 'text', 'placeholder' => 'Europe/Kyiv', 'span' => 1],
                            ['name' => 'maintenance_mode', 'label' => 'Технічні роботи', 'type' => 'toggle', 'span' => 2],
                            ['name' => 'maintenance_message', 'label' => 'Повідомлення для клієнтів', 'type' => 'textarea', 'placeholder' => 'Короткий текст на період робіт', 'span' => 2],
                        ],
                    ],
                ],
            ],
            'checkout' => [
                'summary' => [
                    ['label' => 'Гостьовий checkout', 'value' => $this->settings->get($section)['guest_checkout'] ? 'Так' : 'Ні'],
                    ['label' => 'Мін. сума', 'value' => $this->settings->get($section)['min_order_amount'].' грн'],
                    ['label' => '1 клік', 'value' => $this->settings->get($section)['one_click_enabled'] ? 'Увімкнено' : 'Вимкнено'],
                ],
                'groups' => [
                    [
                        'title' => 'Правила checkout',
                        'fields' => [
                            ['name' => 'guest_checkout', 'label' => 'Дозволити замовлення без реєстрації', 'type' => 'toggle', 'span' => 2],
                            ['name' => 'account_creation_mode', 'label' => 'Створення акаунта', 'type' => 'select', 'options' => [
                                ['value' => 'optional', 'label' => 'Опційно'],
                                ['value' => 'after_order', 'label' => 'Після замовлення'],
                                ['value' => 'required', 'label' => 'Обовʼязково'],
                            ], 'span' => 1],
                            ['name' => 'default_order_status', 'label' => 'Статус нового замовлення', 'type' => 'select', 'options' => [
                                ['value' => 'awaiting_confirmation', 'label' => 'Очікує підтвердження'],
                                ['value' => 'pending_payment', 'label' => 'Очікує оплату'],
                                ['value' => 'processing', 'label' => 'В обробці'],
                                ['value' => 'new', 'label' => 'Нове'],
                            ], 'span' => 1],
                            ['name' => 'min_order_amount', 'label' => 'Мінімальна сума, грн', 'type' => 'number', 'placeholder' => '0.00', 'span' => 1],
                            ['name' => 'one_click_enabled', 'label' => 'Замовлення в 1 клік', 'type' => 'toggle', 'span' => 1],
                        ],
                    ],
                    [
                        'title' => 'Обовʼязкові поля і документи',
                        'fields' => [
                            ['name' => 'require_phone', 'label' => 'Телефон обовʼязковий', 'type' => 'toggle', 'span' => 1],
                            ['name' => 'require_email', 'label' => 'Email обовʼязковий', 'type' => 'toggle', 'span' => 1],
                            ['name' => 'require_last_name', 'label' => 'Прізвище обовʼязкове', 'type' => 'toggle', 'span' => 1],
                            ['name' => 'terms_url', 'label' => 'URL умов', 'type' => 'text', 'placeholder' => '/terms', 'span' => 1],
                            ['name' => 'privacy_url', 'label' => 'URL політики', 'type' => 'text', 'placeholder' => '/privacy-policy', 'span' => 2],
                        ],
                    ],
                ],
            ],
            'integrations' => [
                'summary' => [
                    ['label' => 'SMTP', 'value' => $this->settings->get($section)['smtp_host'] ? 'Налаштовано' : 'Не задано'],
                    ['label' => 'Telegram', 'value' => $this->settings->hasSecret('integrations', 'telegram_bot_token') ? 'Ключ збережено' : 'Не задано'],
                    ['label' => 'Sheets', 'value' => $this->settings->get($section)['google_sheets_webhook'] ? 'Webhook є' : 'Не задано'],
                ],
                'groups' => [
                    [
                        'title' => 'Email SMTP',
                        'description' => 'Підключення поштової скриньки для системних листів: реєстрація, відновлення пароля, статуси замовлень.',
                        'fields' => [
                            ['name' => 'smtp_host', 'label' => 'SMTP host', 'type' => 'text', 'placeholder' => 'smtp.example.com', 'hint' => 'Адреса SMTP-сервера пошти. Наприклад: smtp.gmail.com, smtp.sendgrid.net або сервер від хостингу.', 'span' => 1],
                            ['name' => 'smtp_port', 'label' => 'Порт', 'type' => 'number', 'placeholder' => '587', 'hint' => 'Зазвичай 587 для TLS або 465 для SSL. Значення бере поштовий сервіс.', 'span' => 1],
                            ['name' => 'smtp_username', 'label' => 'Username', 'type' => 'text', 'placeholder' => 'mailer@example.com', 'hint' => 'Логін SMTP. Найчастіше це email-адреса або окремий username з поштового сервісу.', 'span' => 1],
                            ['name' => 'smtp_password', 'label' => 'Password', 'type' => 'secret', 'placeholder' => 'Залиши порожнім, щоб не змінювати', 'hint' => 'Пароль або app password для SMTP. Якщо ключ уже збережено, поле можна лишити порожнім.', 'secret' => $secretMeta('smtp_password'), 'span' => 1],
                            ['name' => 'smtp_encryption', 'label' => 'Encryption', 'type' => 'select', 'options' => [
                                ['value' => 'none', 'label' => 'Без шифрування'],
                                ['value' => 'tls', 'label' => 'TLS'],
                                ['value' => 'ssl', 'label' => 'SSL'],
                            ], 'hint' => 'Тип шифрування має відповідати порту: TLS зазвичай 587, SSL зазвичай 465.', 'span' => 1],
                            ['name' => 'mail_from_email', 'label' => 'From email', 'type' => 'email', 'placeholder' => 'hello@dommood.com.ua', 'hint' => 'Email, який клієнт бачить у полі “від кого”. Має бути дозволений у SMTP-сервісі.', 'span' => 1],
                            ['name' => 'mail_from_name', 'label' => 'From name', 'type' => 'text', 'placeholder' => 'DomMood', 'hint' => 'Назва відправника у листах. Наприклад: DomMood або DomMood Support.', 'span' => 2],
                        ],
                    ],
                    [
                        'title' => 'Операційні webhook',
                        'description' => 'Службові інтеграції для повідомлень команді та передачі операційних даних у зовнішні сервіси.',
                        'fields' => [
                            ['name' => 'telegram_bot_token', 'label' => 'Telegram bot token', 'type' => 'secret', 'placeholder' => 'Залиши порожнім, щоб не змінювати', 'hint' => 'Токен бота з BotFather. Потрібен, щоб сайт міг відправляти повідомлення в Telegram.', 'secret' => $secretMeta('telegram_bot_token'), 'span' => 1],
                            ['name' => 'telegram_chat_id', 'label' => 'Telegram chat ID', 'type' => 'text', 'placeholder' => '-100...', 'hint' => 'ID чату або каналу, куди надсилати службові повідомлення. Для груп часто починається з -100.', 'span' => 1],
                            ['name' => 'google_sheets_webhook', 'label' => 'Google Sheets webhook', 'type' => 'url', 'placeholder' => 'https://...', 'hint' => 'URL webhook/Apps Script, якщо треба передавати замовлення або звіти в Google Sheets. Можна залишити порожнім.', 'span' => 2],
                        ],
                    ],
                ],
            ],
            'payments' => [
                'summary' => [
                    ['label' => 'LiqPay', 'value' => $this->settings->get($section)['liqpay_enabled'] ? ($this->settings->hasSecret($section, 'liqpay_private_key') ? 'Підключено' : 'Потрібен private key') : 'Вимкнено'],
                    ['label' => 'Monobank', 'value' => $this->settings->get($section)['monobank_enabled'] ? ($this->settings->hasSecret($section, 'monobank_token') ? 'Підключено' : 'Потрібен token') : 'Вимкнено'],
                    ['label' => 'Логіка checkout', 'value' => 'Провайдер + активний метод оплати'],
                ],
                'groups' => [
                    [
                        'title' => 'LiqPay',
                        'description' => 'Тут зберігаємо API-ключі LiqPay. У “Методи оплати” тільки вмикаємо, чи показувати LiqPay клієнту.',
                        'fields' => [
                            ['name' => 'liqpay_enabled', 'label' => 'Провайдер підключений', 'type' => 'toggle', 'span' => 2],
                            ['name' => 'liqpay_mode', 'label' => 'Режим', 'type' => 'select', 'options' => [
                                ['value' => 'test', 'label' => 'Тестовий режим'],
                                ['value' => 'live', 'label' => 'Бойовий режим'],
                            ], 'span' => 1],
                            ['name' => 'liqpay_language', 'label' => 'Мова checkout', 'type' => 'select', 'options' => [
                                ['value' => 'uk', 'label' => 'Українська'],
                                ['value' => 'en', 'label' => 'English'],
                            ], 'span' => 1],
                            ['name' => 'liqpay_public_key', 'label' => 'Public key', 'type' => 'text', 'placeholder' => 'i00000000', 'hint' => 'Публічний ключ компанії LiqPay.', 'span' => 1],
                            ['name' => 'liqpay_private_key', 'label' => 'Private key', 'type' => 'secret', 'placeholder' => 'Залиши порожнім, щоб не змінювати', 'hint' => 'Приватний ключ LiqPay. Не показуємо його після збереження.', 'secret' => $secretMeta('liqpay_private_key'), 'span' => 1],
                            ['name' => 'liqpay_server_url', 'label' => 'Callback URL', 'type' => 'url', 'placeholder' => route('payments.liqpay.callback'), 'hint' => 'LiqPay надсилає сюди server callback зі статусом платежу.', 'span' => 1],
                            ['name' => 'liqpay_result_url', 'label' => 'Result URL', 'type' => 'url', 'placeholder' => route('payments.liqpay.result'), 'hint' => 'Сюди повертається клієнт після оплати.', 'span' => 1],
                        ],
                    ],
                    [
                        'title' => 'Monobank',
                        'description' => 'Підключення залишене окремим провайдером, щоб пізніше додати invoice/webhook без зміни checkout-логіки.',
                        'fields' => [
                            ['name' => 'monobank_enabled', 'label' => 'Провайдер підключений', 'type' => 'toggle', 'span' => 2],
                            ['name' => 'monobank_mode', 'label' => 'Режим', 'type' => 'select', 'options' => [
                                ['value' => 'test', 'label' => 'Тестовий режим'],
                                ['value' => 'live', 'label' => 'Бойовий режим'],
                            ], 'span' => 1],
                            ['name' => 'monobank_merchant_id', 'label' => 'Merchant ID', 'type' => 'text', 'placeholder' => 'merchant...', 'hint' => 'Ідентифікатор мерчанта, якщо потрібен для конкретного Monobank сценарію.', 'span' => 1],
                            ['name' => 'monobank_token', 'label' => 'API token', 'type' => 'secret', 'placeholder' => 'Залиши порожнім, щоб не змінювати', 'hint' => 'Токен Monobank для створення оплат/invoice. Не показуємо після збереження.', 'secret' => $secretMeta('monobank_token'), 'span' => 2],
                            ['name' => 'monobank_webhook_url', 'label' => 'Webhook URL', 'type' => 'url', 'placeholder' => url('/payments/monobank/callback'), 'hint' => 'URL для майбутнього callback/webhook Monobank.', 'span' => 1],
                            ['name' => 'monobank_result_url', 'label' => 'Result URL', 'type' => 'url', 'placeholder' => url('/payments/monobank/result'), 'hint' => 'Сторінка повернення клієнта після оплати Monobank.', 'span' => 1],
                        ],
                    ],
                ],
            ],
            'security' => [
                'summary' => [
                    ['label' => '2FA', 'value' => $this->settings->get($section)['admin_2fa_required'] ? 'Обовʼязкова' : 'Вимкнена'],
                    ['label' => 'Сесія', 'value' => $this->settings->get($section)['session_lifetime_minutes'].' хв'],
                    ['label' => 'Alerts', 'value' => $this->settings->get($section)['login_alerts'] ? 'Увімкнено' : 'Вимкнено'],
                ],
                'groups' => [
                    [
                        'title' => 'Доступ персоналу',
                        'fields' => [
                            ['name' => 'admin_2fa_required', 'label' => 'Вимагати 2FA для адмінів', 'type' => 'toggle', 'span' => 1],
                            ['name' => 'staff_invites_enabled', 'label' => 'Дозволити інвайти персоналу', 'type' => 'toggle', 'span' => 1],
                            ['name' => 'manager_ip_allowlist', 'label' => 'IP allowlist менеджерів', 'type' => 'textarea', 'placeholder' => "1.1.1.1\n2.2.2.2", 'span' => 2],
                        ],
                    ],
                    [
                        'title' => 'Сесії',
                        'fields' => [
                            ['name' => 'session_lifetime_minutes', 'label' => 'Тривалість сесії, хв', 'type' => 'number', 'span' => 1],
                            ['name' => 'password_rotation_days', 'label' => 'Ротація пароля, днів', 'type' => 'number', 'placeholder' => '0 = не вимагати', 'span' => 1],
                            ['name' => 'login_alerts', 'label' => 'Повідомлення про нові входи', 'type' => 'toggle', 'span' => 2],
                        ],
                    ],
                ],
            ],
            'system' => [
                'summary' => [
                    ['label' => 'Cache TTL', 'value' => $this->settings->get($section)['cache_ttl_minutes'].' хв'],
                    ['label' => 'Logs', 'value' => $this->settings->get($section)['log_retention_days'].' днів'],
                    ['label' => 'Feeds', 'value' => $this->settings->get($section)['feed_auto_refresh'] ? 'Авто' : 'Вручну'],
                ],
                'groups' => [
                    [
                        'title' => 'Автоматизація',
                        'fields' => [
                            ['name' => 'cache_ttl_minutes', 'label' => 'Cache TTL, хв', 'type' => 'number', 'span' => 1],
                            ['name' => 'log_retention_days', 'label' => 'Зберігати логи, днів', 'type' => 'number', 'span' => 1],
                            ['name' => 'sitemap_auto_refresh', 'label' => 'Автооновлення sitemap', 'type' => 'toggle', 'span' => 1],
                            ['name' => 'feed_auto_refresh', 'label' => 'Автооновлення feeds', 'type' => 'toggle', 'span' => 1],
                            ['name' => 'image_cleanup_enabled', 'label' => 'Чистити невикористані зображення', 'type' => 'toggle', 'span' => 1],
                            ['name' => 'maintenance_contact', 'label' => 'Контакт для технічних питань', 'type' => 'text', 'placeholder' => 'admin@dommood.com.ua', 'span' => 1],
                        ],
                    ],
                ],
            ],
            default => ['summary' => [], 'groups' => []],
        };
    }

    private function options(array $values): array
    {
        return collect($values)
            ->map(fn (string $value): array => ['value' => $value, 'label' => $value])
            ->all();
    }
}
