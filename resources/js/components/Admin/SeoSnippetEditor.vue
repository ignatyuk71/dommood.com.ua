<script setup>
import InputError from '@/components/InputError.vue';
import { Search, Wand2 } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps({
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    canonicalUrl: { type: String, default: '' },
    seoText: { type: String, default: '' },
    titleFallback: { type: String, default: 'Назва сторінки' },
    descriptionFallback: { type: String, default: 'Короткий опис сторінки зʼявиться тут.' },
    urlFallback: { type: String, default: '' },
    entityName: { type: String, default: 'DomMood' },
    titlePlaceholder: { type: String, default: 'Короткий заголовок для Google' },
    descriptionPlaceholder: { type: String, default: 'Короткий опис для пошуку' },
    canonicalPlaceholder: { type: String, default: '/catalog/page-slug' },
    seoTextPlaceholder: { type: String, default: 'SEO текст для сторінки' },
    titleLimit: { type: Number, default: 60 },
    descriptionLimit: { type: Number, default: 160 },
    fieldIdPrefix: { type: String, default: 'seo' },
    intro: { type: String, default: 'Це превʼю для Google і соцмереж. Якщо поля порожні — система використає fallback-дані сторінки.' },
    showCanonical: { type: Boolean, default: true },
    showSeoText: { type: Boolean, default: true },
    compact: { type: Boolean, default: false },
    errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits([
    'update:title',
    'update:description',
    'update:canonicalUrl',
    'update:seoText',
]);

const publicOrigin = () => (typeof window === 'undefined' ? 'https://dommood.com.ua' : window.location.origin);

const normalizeSeoText = (value) => String(value ?? '')
    .replace(/<[^>]*>/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();

const truncateSeoPreview = (value, limit) => {
    const normalized = normalizeSeoText(value);

    if (normalized.length <= limit) {
        return normalized;
    }

    return `${normalized.slice(0, Math.max(0, limit - 1)).trim()}…`;
};

const titleModel = computed({
    get: () => props.title,
    set: (value) => emit('update:title', value),
});

const descriptionModel = computed({
    get: () => props.description,
    set: (value) => emit('update:description', value),
});

const canonicalModel = computed({
    get: () => props.canonicalUrl,
    set: (value) => emit('update:canonicalUrl', value),
});

const seoTextModel = computed({
    get: () => props.seoText,
    set: (value) => emit('update:seoText', value),
});

const normalizedTitleLength = computed(() => normalizeSeoText(props.title).length);
const normalizedDescriptionLength = computed(() => normalizeSeoText(props.description).length);

const previewTitle = computed(() => truncateSeoPreview(
    props.title || props.titleFallback,
    props.titleLimit,
));

const previewDescription = computed(() => truncateSeoPreview(
    props.description || props.descriptionFallback,
    props.descriptionLimit,
));

const previewUrl = computed(() => {
    const canonicalUrl = normalizeSeoText(props.canonicalUrl);
    const fallbackUrl = normalizeSeoText(props.urlFallback);
    const value = canonicalUrl || fallbackUrl || '/';

    if (/^https?:\/\//i.test(value)) {
        return value;
    }

    return `${publicOrigin()}/${value.replace(/^\/+/, '')}`;
});

const previewDisplayUrl = computed(() => previewUrl.value
    .replace(/^https?:\/\//i, '')
    .replace(/\/$/, ''));

const titleState = computed(() => {
    if (!normalizedTitleLength.value) {
        return 'empty';
    }

    return normalizedTitleLength.value <= props.titleLimit ? 'good' : 'over';
});

const descriptionState = computed(() => {
    if (!normalizedDescriptionLength.value) {
        return 'empty';
    }

    return normalizedDescriptionLength.value <= props.descriptionLimit ? 'good' : 'over';
});

const stateClass = (state) => ({
    empty: 'text-slate-400',
    good: 'text-emerald-600',
    over: 'text-red-500',
}[state] ?? 'text-slate-400');

const fillEmptyFields = () => {
    if (!normalizeSeoText(props.title)) {
        emit('update:title', truncateSeoPreview(props.titleFallback, props.titleLimit));
    }

    if (!normalizeSeoText(props.description)) {
        emit('update:description', truncateSeoPreview(props.descriptionFallback, props.descriptionLimit));
    }
};
</script>

<template>
    <div class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
        <div class="flex flex-col gap-3 border-b border-slate-100 pb-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.18em] text-[#7561f7]">SEO</p>
                <h2 class="mt-1 text-xl font-bold text-[#343241]">Пошуковий сніпет</h2>
                <p class="mt-1 max-w-2xl text-sm font-semibold text-slate-500">{{ intro }}</p>
            </div>
            <button
                type="button"
                class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-[#7561f7]/30 bg-[#f7f5ff] px-4 text-sm font-bold text-[#5b47df] transition hover:border-[#7561f7] hover:bg-[#f0edff]"
                @click="fillEmptyFields"
            >
                <Wand2 class="h-4 w-4" />
                Заповнити порожні
            </button>
        </div>

        <div class="mt-5 grid gap-5" :class="compact ? 'xl:grid-cols-1' : 'xl:grid-cols-[minmax(0,1fr)_minmax(360px,0.86fr)]'">
            <div class="space-y-5">
                <div>
                    <label class="flex items-center justify-between gap-3 text-sm font-bold text-slate-700" :for="`${fieldIdPrefix}_meta_title`">
                        <span>Meta Title, до {{ titleLimit }} символів</span>
                        <span class="text-xs font-black" :class="stateClass(titleState)">
                            {{ normalizedTitleLength }}/{{ titleLimit }}
                        </span>
                    </label>
                    <input
                        :id="`${fieldIdPrefix}_meta_title`"
                        v-model="titleModel"
                        type="text"
                        :maxlength="titleLimit"
                        class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                        :placeholder="titlePlaceholder"
                    />
                    <InputError class="mt-2" :message="errors.meta_title || errors.title" />
                </div>

                <div>
                    <label class="flex items-center justify-between gap-3 text-sm font-bold text-slate-700" :for="`${fieldIdPrefix}_meta_description`">
                        <span>Meta Description, до {{ descriptionLimit }} символів</span>
                        <span class="text-xs font-black" :class="stateClass(descriptionState)">
                            {{ normalizedDescriptionLength }}/{{ descriptionLimit }}
                        </span>
                    </label>
                    <textarea
                        :id="`${fieldIdPrefix}_meta_description`"
                        v-model="descriptionModel"
                        rows="4"
                        :maxlength="descriptionLimit"
                        class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                        :placeholder="descriptionPlaceholder"
                    />
                    <InputError class="mt-2" :message="errors.meta_description || errors.description" />
                </div>

                <div v-if="showCanonical">
                    <label class="text-sm font-bold text-slate-700" :for="`${fieldIdPrefix}_canonical_url`">Canonical URL</label>
                    <input
                        :id="`${fieldIdPrefix}_canonical_url`"
                        v-model="canonicalModel"
                        type="text"
                        class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                        :placeholder="canonicalPlaceholder"
                    />
                    <p class="mt-2 text-xs font-semibold text-slate-500">Залиш порожнім, якщо canonical має збігатися з URL сторінки.</p>
                    <InputError class="mt-2" :message="errors.canonical_url || errors.canonicalUrl" />
                </div>

                <div v-if="showSeoText">
                    <label class="text-sm font-bold text-slate-700" :for="`${fieldIdPrefix}_seo_text`">SEO текст</label>
                    <textarea
                        :id="`${fieldIdPrefix}_seo_text`"
                        v-model="seoTextModel"
                        rows="5"
                        class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                        :placeholder="seoTextPlaceholder"
                    />
                    <InputError class="mt-2" :message="errors.seo_text || errors.seoText" />
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-[0_18px_48px_rgba(61,58,101,0.10)]">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-400">Попередній вигляд</p>
                        <h3 class="mt-1 text-base font-black text-[#343241]">Google snippet</h3>
                    </div>
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-[#f0edff] text-[#7561f7]">
                        <Search class="h-5 w-5" />
                    </span>
                </div>

                <div class="mt-5 rounded-xl border border-slate-100 bg-[#fbfcff] p-4">
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#eef2ff] text-xs font-black text-[#4c51bf]">
                            DM
                        </div>
                        <div class="min-w-0">
                            <div class="truncate text-sm font-semibold text-slate-700">{{ entityName }}</div>
                            <div class="truncate text-xs font-medium text-slate-500">{{ previewDisplayUrl }}</div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <div class="break-words text-xl font-medium leading-6 text-[#1a0dab]">
                            {{ previewTitle }}
                        </div>
                        <div class="mt-1 break-words text-sm font-medium leading-6 text-[#12823b]">
                            {{ previewDisplayUrl }}
                        </div>
                        <p class="mt-2 text-sm font-medium leading-6 text-slate-600">
                            {{ previewDescription }}
                        </p>
                    </div>
                </div>

                <div class="mt-4 grid gap-2 text-xs font-bold text-slate-500 sm:grid-cols-3 xl:grid-cols-1 2xl:grid-cols-3">
                    <div class="rounded-lg bg-slate-50 px-3 py-2">
                        Title:
                        <span :class="stateClass(titleState)">
                            {{ titleState === 'empty' ? 'fallback' : 'ok' }}
                        </span>
                    </div>
                    <div class="rounded-lg bg-slate-50 px-3 py-2">
                        Description:
                        <span :class="stateClass(descriptionState)">
                            {{ descriptionState === 'empty' ? 'fallback' : 'ok' }}
                        </span>
                    </div>
                    <div class="rounded-lg bg-slate-50 px-3 py-2">
                        URL:
                        <span class="text-emerald-600">canonical</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
