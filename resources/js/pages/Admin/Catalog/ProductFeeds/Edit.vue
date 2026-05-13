<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2, ExternalLink, Save, Settings2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps({
    payload: {
        type: Object,
        required: true,
    },
    channelOptions: {
        type: Object,
        required: true,
    },
    defaultGoogleCategory: {
        type: String,
        required: true,
    },
    googleGenderOptions: {
        type: Array,
        required: true,
    },
    googleAgeGroupOptions: {
        type: Array,
        required: true,
    },
    googleSizeSystemOptions: {
        type: Array,
        required: true,
    },
    googleSizeTypeOptions: {
        type: Array,
        required: true,
    },
});

const channels = Object.entries(props.channelOptions).map(([key, channel]) => ({ key, ...channel }));
const activeChannel = ref(channels[0]?.key ?? 'google_merchant');
const active = computed(() => channels.find((channel) => channel.key === activeChannel.value) ?? channels[0]);
const activeFeedUrl = computed(() => props.payload.feedUrls[activeChannel.value]?.url);

const toLines = (value, formatter) => (value ?? []).map(formatter).join('\n');
const configToForm = (config) => ({
    is_enabled: Boolean(config.is_enabled),
    brand: config.brand ?? 'DomMood',
    google_product_category: config.google_product_category ?? props.defaultGoogleCategory,
    custom_title: config.custom_title ?? '',
    custom_description: config.custom_description ?? '',
    google_gender: config.google_gender ?? '',
    google_age_group: config.google_age_group ?? '',
    google_material: config.google_material ?? '',
    google_pattern: config.google_pattern ?? '',
    google_size_system: config.google_size_system ?? '',
    google_size_types: config.google_size_types ?? [],
    google_is_bundle: Boolean(config.google_is_bundle),
    google_item_group_id: config.google_item_group_id ?? '',
    google_product_highlights: toLines(config.google_product_highlights, (line) => line),
    google_product_details: toLines(config.google_product_details, (detail) => [detail.section_name, detail.attribute_name, detail.attribute_value].filter(Boolean).join(' | ')),
    custom_label_0: config.custom_label_0 ?? '',
    custom_label_1: config.custom_label_1 ?? '',
    custom_label_2: config.custom_label_2 ?? '',
    custom_label_3: config.custom_label_3 ?? '',
    custom_label_4: config.custom_label_4 ?? '',
});

const form = useForm({
    channels: Object.fromEntries(channels.map((channel) => [
        channel.key,
        configToForm(props.payload.channels[channel.key].config),
    ])),
});

const statusClasses = {
    ready: 'bg-emerald-50 text-emerald-700',
    partial: 'bg-amber-50 text-amber-700',
    error: 'bg-rose-50 text-rose-700',
    empty: 'bg-slate-100 text-slate-600',
};

const submit = () => {
    form.put(route('admin.product-feeds.update', props.payload.product.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`Product Feeds: ${payload.product.name}`" />

    <AuthenticatedLayout>
        <section class="mb-5 rounded-lg bg-white px-5 py-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-start gap-4">
                    <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-[#7561f7] text-white shadow-lg shadow-indigo-500/20">
                        <Settings2 class="h-7 w-7" />
                    </span>
                    <div>
                        <p class="text-sm font-extrabold text-slate-500">Product Feeds</p>
                        <h1 class="mt-1 text-3xl font-black tracking-tight text-[#343241]">{{ payload.product.name }}</h1>
                        <p class="mt-2 max-w-3xl text-sm font-semibold text-slate-500">
                            Налаштування експорту цього товару для Google, Meta і TikTok.
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="route('admin.product-feeds.index')" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-4 py-2 text-sm font-black text-slate-600 hover:border-[#7561f7] hover:text-[#7561f7]">
                        <ArrowLeft class="h-4 w-4" />
                        Назад
                    </Link>
                    <button class="inline-flex items-center gap-2 rounded-lg bg-[#343241] px-4 py-2 text-sm font-black text-white shadow-lg shadow-slate-900/10 disabled:opacity-60" type="button" :disabled="form.processing" @click="submit">
                        <Save class="h-4 w-4" />
                        Зберегти
                    </button>
                </div>
            </div>
        </section>

        <section class="grid gap-5 xl:grid-cols-[280px_minmax(0,1fr)]">
            <aside class="rounded-lg bg-white p-3 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                <button
                    v-for="channel in channels"
                    :key="channel.key"
                    type="button"
                    class="mb-2 flex w-full items-center justify-between rounded-lg px-4 py-3 text-left text-sm font-black transition"
                    :class="activeChannel === channel.key ? 'bg-[#7561f7] text-white shadow-lg shadow-indigo-500/20' : 'text-slate-600 hover:bg-slate-50'"
                    @click="activeChannel = channel.key"
                >
                    <span>{{ channel.label }}</span>
                    <span
                        class="rounded-full px-2 py-0.5 text-xs"
                        :class="activeChannel === channel.key ? 'bg-white/20 text-white' : statusClasses[payload.channels[channel.key].status.state]"
                    >
                        {{ payload.channels[channel.key].status.count }}
                    </span>
                </button>
                <a v-if="activeFeedUrl" :href="activeFeedUrl" target="_blank" rel="noopener" class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-lg border border-slate-200 px-4 py-2 text-sm font-black text-slate-600 hover:border-[#7561f7] hover:text-[#7561f7]">
                    <ExternalLink class="h-4 w-4" />
                    Відкрити фід
                </a>
            </aside>

            <form class="rounded-lg bg-white shadow-[0_16px_45px_rgba(61,58,101,0.08)]" @submit.prevent="submit">
                <div class="border-b border-slate-100 px-5 py-4">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h2 class="text-xl font-black text-[#343241]">{{ active.label }}</h2>
                            <p class="mt-1 text-sm font-semibold text-slate-500">Формат: {{ active.format }}</p>
                        </div>
                        <span
                            class="inline-flex w-fit items-center gap-2 rounded-full px-3 py-1 text-xs font-black"
                            :class="statusClasses[payload.channels[activeChannel].status.state]"
                        >
                            <CheckCircle2 class="h-4 w-4" />
                            {{ payload.channels[activeChannel].status.label }}
                        </span>
                    </div>
                </div>

                <div class="grid gap-5 p-5 xl:grid-cols-[minmax(0,1fr)_340px]">
                    <div class="space-y-5">
                        <label class="flex items-center gap-3 rounded-lg bg-slate-50 px-4 py-3 text-sm font-black text-[#343241]">
                            <input v-model="form.channels[activeChannel].is_enabled" type="checkbox" class="rounded border-slate-300 text-[#7561f7] focus:ring-[#7561f7]">
                            Експортувати товар у цей канал
                        </label>

                        <div class="grid gap-4 lg:grid-cols-2">
                            <label class="block">
                                <span class="text-xs font-black uppercase text-slate-500">Brand</span>
                                <input v-model="form.channels[activeChannel].brand" class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]" type="text">
                                <InputError class="mt-2" :message="form.errors[`channels.${activeChannel}.brand`]" />
                            </label>
                            <label class="block">
                                <span class="text-xs font-black uppercase text-slate-500">Google category</span>
                                <input v-model="form.channels[activeChannel].google_product_category" class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]" type="text">
                            </label>
                        </div>

                        <label class="block">
                            <span class="text-xs font-black uppercase text-slate-500">Custom title</span>
                            <input v-model="form.channels[activeChannel].custom_title" class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]" type="text" :placeholder="payload.product.name">
                        </label>

                        <label class="block">
                            <span class="text-xs font-black uppercase text-slate-500">Custom description</span>
                            <textarea v-model="form.channels[activeChannel].custom_description" class="mt-2 min-h-28 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]" />
                        </label>

                        <div class="grid gap-4 lg:grid-cols-3">
                            <label class="block">
                                <span class="text-xs font-black uppercase text-slate-500">Gender</span>
                                <select v-model="form.channels[activeChannel].google_gender" class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]">
                                    <option value="">Авто</option>
                                    <option v-for="option in googleGenderOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                            </label>
                            <label class="block">
                                <span class="text-xs font-black uppercase text-slate-500">Age group</span>
                                <select v-model="form.channels[activeChannel].google_age_group" class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]">
                                    <option value="">Авто</option>
                                    <option v-for="option in googleAgeGroupOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                            </label>
                            <label class="block">
                                <span class="text-xs font-black uppercase text-slate-500">Size system</span>
                                <select v-model="form.channels[activeChannel].google_size_system" class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]">
                                    <option value="">—</option>
                                    <option v-for="option in googleSizeSystemOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                            </label>
                        </div>

                        <div class="grid gap-4 lg:grid-cols-2">
                            <label class="block">
                                <span class="text-xs font-black uppercase text-slate-500">Material</span>
                                <input v-model="form.channels[activeChannel].google_material" class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]" type="text">
                            </label>
                            <label class="block">
                                <span class="text-xs font-black uppercase text-slate-500">Pattern</span>
                                <input v-model="form.channels[activeChannel].google_pattern" class="mt-2 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]" type="text">
                            </label>
                        </div>

                        <div class="grid gap-4 lg:grid-cols-2">
                            <label class="block">
                                <span class="text-xs font-black uppercase text-slate-500">Product highlights</span>
                                <textarea v-model="form.channels[activeChannel].google_product_highlights" class="mt-2 min-h-28 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]" placeholder="Один пункт в одному рядку" />
                            </label>
                            <label class="block">
                                <span class="text-xs font-black uppercase text-slate-500">Product details</span>
                                <textarea v-model="form.channels[activeChannel].google_product_details" class="mt-2 min-h-28 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]" placeholder="Назва | Значення" />
                            </label>
                        </div>
                    </div>

                    <aside class="space-y-4">
                        <div class="rounded-lg border border-slate-100 bg-slate-50 p-4">
                            <h3 class="text-sm font-black text-[#343241]">Item preview</h3>
                            <div class="mt-3 space-y-3">
                                <div v-for="item in payload.channels[activeChannel].items.slice(0, 5)" :key="`${item.id}-${item.variant_id}`" class="rounded-lg bg-white p-3 text-xs shadow-sm">
                                    <div class="font-black text-[#343241]">{{ item.title }}</div>
                                    <div class="mt-1 font-mono font-bold text-slate-500">{{ item.id }}</div>
                                    <div class="mt-2 flex flex-wrap gap-1">
                                        <span class="rounded-full bg-slate-100 px-2 py-1 font-bold text-slate-500">{{ item.price }} {{ item.currency }}</span>
                                        <span class="rounded-full bg-slate-100 px-2 py-1 font-bold text-slate-500">{{ item.availability }}</span>
                                    </div>
                                    <ul v-if="item.issues.length" class="mt-2 list-disc pl-4 text-rose-600">
                                        <li v-for="issue in item.issues" :key="issue">{{ issue }}</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-lg border border-slate-100 bg-white p-4">
                            <h3 class="text-sm font-black text-[#343241]">Custom labels</h3>
                            <div class="mt-3 space-y-2">
                                <input v-for="index in [0, 1, 2, 3, 4]" :key="index" v-model="form.channels[activeChannel][`custom_label_${index}`]" class="w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]" type="text" :placeholder="`custom_label_${index}`">
                            </div>
                        </div>
                    </aside>
                </div>
            </form>
        </section>
    </AuthenticatedLayout>
</template>
