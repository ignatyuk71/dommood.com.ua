<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { AlertCircle, CheckCircle2, Copy, ExternalLink, FileSpreadsheet, Pencil, Rss, SearchX } from 'lucide-vue-next';

const props = defineProps({
    products: {
        type: Object,
        required: true,
    },
    channelOptions: {
        type: Object,
        required: true,
    },
    feedUrls: {
        type: Object,
        required: true,
    },
});

const channels = Object.entries(props.channelOptions).map(([key, channel]) => ({ key, ...channel }));
const feeds = Object.entries(props.feedUrls).map(([key, feed]) => ({ key, ...feed }));

const statusClasses = {
    ready: 'bg-emerald-50 text-emerald-700',
    partial: 'bg-amber-50 text-amber-700',
    error: 'bg-rose-50 text-rose-700',
    empty: 'bg-slate-100 text-slate-600',
};

const notify = (message, type = 'success') => {
    window.dispatchEvent(new CustomEvent('admin-toast', {
        detail: { message, type },
    }));
};

const copy = async (value) => {
    await navigator.clipboard.writeText(value);
    notify('Посилання скопійовано');
};
</script>

<template>
    <Head title="Product Feeds" />

    <AuthenticatedLayout>
        <section class="mb-5 rounded-lg bg-white px-5 py-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-start gap-4">
                    <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-[#7561f7] text-white shadow-lg shadow-indigo-500/20">
                        <Rss class="h-7 w-7" />
                    </span>
                    <div>
                        <p class="text-sm font-extrabold text-slate-500">Каталог</p>
                        <h1 class="mt-1 text-3xl font-black tracking-tight text-[#343241]">Product Feeds</h1>
                        <p class="mt-2 max-w-3xl text-sm font-semibold text-slate-500">
                            Фіди для Google Merchant, Meta Catalog і TikTok Catalog. Google XML повторює структуру Dream V Doma.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section class="mb-5 grid gap-4 lg:grid-cols-3">
            <article v-for="feed in feeds" :key="feed.key" class="rounded-lg border border-slate-100 bg-white p-4 shadow-[0_14px_36px_rgba(61,58,101,0.07)]">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-black uppercase text-slate-400">{{ feed.format }}</p>
                        <h2 class="mt-1 text-lg font-black text-[#343241]">{{ feed.label }}</h2>
                    </div>
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-slate-50 text-[#7561f7]">
                        <FileSpreadsheet class="h-5 w-5" />
                    </span>
                </div>
                <div class="mt-4 flex items-center gap-2 rounded-lg bg-slate-50 px-3 py-2 text-xs font-bold text-slate-600">
                    <span class="truncate">{{ feed.url }}</span>
                    <button class="ml-auto rounded-md p-1 text-slate-500 hover:bg-white hover:text-[#7561f7]" type="button" @click="copy(feed.url)">
                        <Copy class="h-4 w-4" />
                    </button>
                    <a class="rounded-md p-1 text-slate-500 hover:bg-white hover:text-[#7561f7]" :href="feed.url" target="_blank" rel="noopener">
                        <ExternalLink class="h-4 w-4" />
                    </a>
                </div>
            </article>
        </section>

        <section class="overflow-hidden rounded-lg bg-white shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="text-xl font-black text-[#343241]">Товари у фідах</h2>
                <p class="mt-1 text-sm font-semibold text-slate-500">Увімкни потрібний канал у товарі й перевір готовність item.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-black uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Товар</th>
                            <th class="px-5 py-3">SKU</th>
                            <th v-for="channel in channels" :key="channel.key" class="px-5 py-3">{{ channel.label }}</th>
                            <th class="px-5 py-3 text-right">Дії</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="product in products.data" :key="product.id" class="align-middle">
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <img
                                        v-if="product.main_image_url"
                                        :src="product.main_image_url"
                                        :alt="product.name"
                                        class="h-14 w-14 rounded-lg border border-slate-100 object-cover"
                                    >
                                    <div v-else class="flex h-14 w-14 items-center justify-center rounded-lg bg-slate-100 text-slate-300">
                                        <SearchX class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <div class="max-w-[340px] font-black text-[#343241]">{{ product.name }}</div>
                                        <div class="mt-1 font-mono text-xs font-bold text-slate-500">/{{ product.slug }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4 font-mono font-bold text-slate-600">{{ product.sku || '—' }}</td>
                            <td v-for="channel in channels" :key="channel.key" class="px-5 py-4">
                                <div class="flex flex-col gap-1">
                                    <span
                                        class="inline-flex w-fit items-center gap-1 rounded-full px-2.5 py-1 text-xs font-black"
                                        :class="statusClasses[product.feed_statuses[channel.key]?.state] ?? statusClasses.empty"
                                    >
                                        <CheckCircle2 v-if="product.feed_statuses[channel.key]?.state === 'ready'" class="h-3.5 w-3.5" />
                                        <AlertCircle v-else class="h-3.5 w-3.5" />
                                        {{ product.feed_statuses[channel.key]?.label ?? '—' }}
                                    </span>
                                    <span v-if="product.feed_statuses[channel.key]?.count" class="text-xs font-bold text-slate-400">
                                        {{ product.feed_statuses[channel.key].count }} item
                                    </span>
                                </div>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <Link
                                    :href="route('admin.product-feeds.edit', product.id)"
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:border-[#7561f7] hover:text-[#7561f7]"
                                >
                                    <Pencil class="h-5 w-5" />
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="products.data.length === 0">
                            <td :colspan="3 + channels.length" class="px-5 py-12 text-center text-sm font-bold text-slate-500">Товарів ще немає</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
