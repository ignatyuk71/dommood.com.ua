<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/components/InputError.vue';
import SeoSnippetEditor from '@/components/Admin/SeoSnippetEditor.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ExternalLink, Loader2, Save } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps({
    mode: {
        type: String,
        required: true,
    },
    page: {
        type: Object,
        required: true,
    },
    statusOptions: {
        type: Array,
        required: true,
    },
});

const isEdit = computed(() => props.mode === 'edit');

const form = useForm({
    title: props.page.title ?? '',
    slug: props.page.slug ?? '',
    content: props.page.content ?? '',
    status: props.page.status ?? 'draft',
    meta_title: props.page.meta_title ?? '',
    meta_description: props.page.meta_description ?? '',
    canonical_url: props.page.canonical_url ?? '',
    published_at: props.page.published_at ?? '',
});

const pageSlugPreview = computed(() => form.slug || 'page-slug');
const pageUrlPreview = computed(() => `/${String(pageSlugPreview.value).replace(/^\/+/, '')}`);
const pageTitleFallback = computed(() => form.title ? `${form.title} | DomMood` : 'Сторінка | DomMood');
const pageDescriptionFallback = computed(() => form.content || `${form.title || 'Сторінка'} на сайті DomMood.`);

const submit = () => {
    if (isEdit.value) {
        form.put(route('admin.pages.update', props.page.id), {
            preserveScroll: true,
        });

        return;
    }

    form.post(route('admin.pages.store'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="isEdit ? `Редагування: ${page.title}` : 'Нова сторінка'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <Link :href="route('admin.pages.index')" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 hover:text-[#7561f7]">
                        <ArrowLeft class="h-4 w-4" />
                        Назад до сторінок
                    </Link>
                    <h1 class="mt-2 text-2xl font-bold tracking-tight text-[#343241]">
                        {{ isEdit ? 'Редагувати сторінку' : 'Нова сторінка' }}
                    </h1>
                </div>

                <a
                    v-if="isEdit && page.url"
                    :href="page.url"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 text-sm font-bold text-slate-600 transition hover:border-[#7561f7] hover:text-[#7561f7]"
                >
                    <ExternalLink class="h-4 w-4" />
                    Відкрити
                </a>
            </div>
        </template>

        <form class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px]" @submit.prevent="submit">
            <section class="rounded-lg bg-white p-4 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                <div class="grid gap-4">
                    <div>
                        <label class="text-sm font-bold text-slate-700" for="title">Назва</label>
                        <input
                            id="title"
                            v-model="form.title"
                            type="text"
                            class="mt-1 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                            placeholder="Напр., Оплата і доставка"
                        />
                        <InputError class="mt-1" :message="form.errors.title" />
                    </div>

                    <div>
                        <label class="text-sm font-bold text-slate-700" for="content">Контент</label>
                        <textarea
                            id="content"
                            v-model="form.content"
                            rows="18"
                            class="mt-1 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                            placeholder="<h2>Доставка</h2><p>Текст сторінки...</p>"
                        />
                        <p class="mt-1 text-xs font-medium text-slate-500">Можна вводити HTML для заголовків, списків і посилань.</p>
                        <InputError class="mt-1" :message="form.errors.content" />
                    </div>
                </div>
            </section>

            <aside class="space-y-4">
                <section class="rounded-lg bg-white p-4 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <h2 class="text-base font-bold text-[#343241]">Публікація</h2>

                    <div class="mt-4 space-y-3">
                        <div>
                            <label class="text-sm font-bold text-slate-700" for="slug">URL</label>
                            <input
                                id="slug"
                                v-model="form.slug"
                                type="text"
                                class="mt-1 w-full rounded-lg border-slate-200 font-mono text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                placeholder="oplata-i-dostavka"
                            />
                            <InputError class="mt-1" :message="form.errors.slug" />
                        </div>

                        <div>
                            <label class="text-sm font-bold text-slate-700" for="status">Статус</label>
                            <select
                                id="status"
                                v-model="form.status"
                                class="mt-1 w-full rounded-lg border-slate-200 py-2 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                            >
                                <option v-for="status in statusOptions" :key="status.value" :value="status.value">
                                    {{ status.label }}
                                </option>
                            </select>
                            <InputError class="mt-1" :message="form.errors.status" />
                        </div>

                        <div>
                            <label class="text-sm font-bold text-slate-700" for="published_at">Дата публікації</label>
                            <input
                                id="published_at"
                                v-model="form.published_at"
                                type="datetime-local"
                                class="mt-1 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                            />
                            <InputError class="mt-1" :message="form.errors.published_at" />
                        </div>

                        <button
                            type="submit"
                            class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-[#7561f7] px-4 text-sm font-bold text-white shadow-[0_10px_24px_rgba(117,97,247,0.22)] transition hover:bg-[#6552e8] disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="form.processing"
                        >
                            <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" />
                            <Save v-else class="h-4 w-4" />
                            {{ isEdit ? 'Зберегти' : 'Створити' }}
                        </button>
                    </div>
                </section>

                <SeoSnippetEditor
                    v-model:title="form.meta_title"
                    v-model:description="form.meta_description"
                    v-model:canonical-url="form.canonical_url"
                    :show-seo-text="false"
                    :title-fallback="pageTitleFallback"
                    :description-fallback="pageDescriptionFallback"
                    :url-fallback="pageUrlPreview"
                    field-id-prefix="content_page_seo"
                    title-placeholder="Оплата і доставка | DomMood"
                    description-placeholder="Короткий опис сторінки для Google"
                    canonical-placeholder="/oplata-i-dostavka"
                    intro="SEO для контентних сторінок допомагає Google, рекламі й користувачам правильно бачити службові сторінки магазину."
                    compact
                    :errors="form.errors"
                />
            </aside>
        </form>
    </AuthenticatedLayout>
</template>
