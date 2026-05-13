<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const OLD_SLUG = 'bezkoshtovne-povernennia';
    private const NEW_SLUG = 'bezkoshtovne-povernennia-novoiu-poshtoiu';

    public function up(): void
    {
        $now = now();
        $content = <<<'HTML'
<section class="returns-page">
    <div class="returns-block">
        <p>Послуга «Легке повернення» доступна тільки у мобільному додатку або бізнес-кабінеті в електронній накладній посилки.</p>
        <p><strong>Щоб скористатися Легким поверненням, вам потрібно:</strong></p>
        <ol>
            <li>увійти в мобільний додаток Нової пошти або бізнес-кабінет;</li>
            <li>відкрити «Мої відправлення» та обрати отриману посилку, яку бажаєте повернути;</li>
            <li>у розділі «Керувати посилкою» вибрати «Легке повернення»;</li>
            <li>зазначити причину повернення товару із запропонованого переліку;</li>
            <li>створити нову електронну накладну та відправити товар отримувачу у поштомат або на відділення.</li>
        </ol>
    </div>

    <div class="how-it-works-buyer">
        <div class="hiw-inner">
            <div class="hiw-text">
                <h2>Як працює<br>для покупця?</h2>
                <p>Послугу <strong>«Легке повернення»</strong> може замовити покупець у мобільному додатку або в бізнес-кабінеті.</p>
                <div class="hiw-small-image">
                    <img src="/brand/content-pages/returns/easy-return-icon.png" alt="Іконка послуги Легке повернення" width="400" height="249" loading="lazy">
                </div>
            </div>

            <div class="hiw-steps">
                <div class="hiw-step">
                    <img src="/brand/content-pages/returns/easy-return-step-1.jpeg" alt="Крок 1: вибір посилки для повернення" width="476" height="920" loading="lazy">
                    <p>Відкрити «Мої посилки» та обрати отриману посилку, яку бажаєте повернути.</p>
                </div>

                <div class="hiw-step">
                    <img src="/brand/content-pages/returns/easy-return-step-2.png" alt="Крок 2: вибір Легкого повернення" width="476" height="920" loading="lazy">
                    <p>У розділі «Керувати посилкою» вибрати «Легке повернення».</p>
                </div>

                <div class="hiw-step">
                    <img src="/brand/content-pages/returns/easy-return-step-3.png" alt="Крок 3: причина повернення" width="476" height="920" loading="lazy">
                    <p>Зазначити причину повернення товару із запропонованого переліку.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="how-it-works-buyer how-it-works-buyer-second">
        <div class="hiw-inner">
            <div class="hiw-steps">
                <div class="hiw-step">
                    <img src="/brand/content-pages/returns/easy-return-step-4.png" alt="Крок 4: створена електронна накладна" width="476" height="920" loading="lazy">
                    <p>На екрані буде видно, що електронну накладну створено. Там зазначено, протягом скількох годин потрібно передати посилку до Нової пошти, а також номер накладної.</p>
                </div>

                <div class="hiw-step">
                    <img src="/brand/content-pages/returns/easy-return-step-5.png" alt="Крок 5: створені накладні" width="476" height="920" loading="lazy">
                    <p>У розділі «Створені накладні» відображається, по якій накладній замовлено послугу.</p>
                </div>

                <div class="hiw-step">
                    <img src="/brand/content-pages/returns/easy-return-step-6.png" alt="Крок 6: передача посилки у відділенні" width="476" height="920" loading="lazy">
                    <p>Створену накладну покажіть у відділенні Нової пошти або назвіть її номер. Менеджер оформить відправлення, прийме товар і поверне посилку продавцю.</p>
                </div>
            </div>
        </div>

        <div class="hiw-bottom-logo">
            <img src="/brand/content-pages/returns/nova-poshta-easy-return.png" alt="Нова пошта Легке повернення" width="1153" height="314" loading="lazy">
        </div>
    </div>
</section>
HTML;

        $payload = [
            'title' => 'Безкоштовне повернення',
            'slug' => self::NEW_SLUG,
            'content' => $content,
            'status' => 'published',
            'meta_title' => 'Безкоштовне повернення | DomMood',
            'meta_description' => 'Як скористатися безкоштовним поверненням товарів DomMood через послугу Легке повернення Нової пошти.',
            'canonical_url' => null,
            'published_at' => $now,
            'deleted_at' => null,
            'updated_at' => $now,
        ];

        $newPage = DB::table('content_pages')->where('slug', self::NEW_SLUG)->first();
        $oldPage = DB::table('content_pages')->where('slug', self::OLD_SLUG)->first();

        if ($newPage) {
            DB::table('content_pages')->where('id', $newPage->id)->update($payload);
            $pageId = $newPage->id;
        } elseif ($oldPage) {
            DB::table('content_pages')->where('id', $oldPage->id)->update($payload);
            $pageId = $oldPage->id;
        } else {
            $pageId = DB::table('content_pages')->insertGetId([
                ...$payload,
                'created_at' => $now,
            ]);
        }

        if ($oldPage && $oldPage->id !== $pageId) {
            DB::table('menu_items')
                ->where('linkable_type', 'App\\Models\\ContentPage')
                ->where('linkable_id', $oldPage->id)
                ->update([
                    'linkable_id' => $pageId,
                    'title' => 'Безкоштовне повернення',
                    'updated_at' => $now,
                ]);
        }

        DB::table('menu_items')
            ->where('linkable_type', 'App\\Models\\ContentPage')
            ->where('linkable_id', $pageId)
            ->update([
                'title' => 'Безкоштовне повернення',
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        DB::table('content_pages')
            ->where('slug', self::NEW_SLUG)
            ->update([
                'slug' => self::OLD_SLUG,
                'updated_at' => now(),
            ]);
    }
};
