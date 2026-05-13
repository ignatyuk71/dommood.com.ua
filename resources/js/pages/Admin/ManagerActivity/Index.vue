<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Activity,
    CalendarDays,
    ChevronDown,
    Clock3,
    CreditCard,
    Eye,
    Filter,
    ListTree,
    LogIn,
    PackageSearch,
    RotateCcw,
    Search,
    SearchCheck,
    Settings,
    ShieldCheck,
    ShoppingCart,
    Tags,
    UserRoundCheck,
    UsersRound,
    Wrench,
} from 'lucide-vue-next';
import { reactive, ref } from 'vue';

const props = defineProps({
    logs: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    managers: {
        type: Array,
        default: () => [],
    },
    eventGroups: {
        type: Array,
        default: () => [],
    },
    summary: {
        type: Object,
        default: () => ({}),
    },
    managerStats: {
        type: Array,
        default: () => [],
    },
});

const form = reactive({
    manager_id: props.filters.manager_id ?? '',
    event_group: props.filters.event_group ?? '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
});
const expandedLogIds = ref(new Set());

const submit = () => {
    const params = Object.fromEntries(
        Object.entries(form).filter(([, value]) => value !== null && value !== ''),
    );

    router.get(route('admin.manager-activity.index'), params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const resetFilters = () => {
    form.manager_id = '';
    form.event_group = '';
    form.date_from = '';
    form.date_to = '';
    submit();
};

const isExpanded = (logId) => expandedLogIds.value.has(logId);
const toggleLog = (logId) => {
    const ids = new Set(expandedLogIds.value);

    ids.has(logId) ? ids.delete(logId) : ids.add(logId);
    expandedLogIds.value = ids;
};

const eventIcons = {
    modules: Eye,
    logins: LogIn,
    orders: ShoppingCart,
    products: PackageSearch,
    site_structure: ListTree,
    catalog: Tags,
    payment_delivery: CreditCard,
    seo: SearchCheck,
    settings: Settings,
    roles: ShieldCheck,
    system: Wrench,
};
const eventIcon = (log) => eventIcons[log.event_group] ?? Activity;
const changesCount = (log) => (log.changes?.length ?? 0) + (log.changes_extra_count ?? 0);
const compactChange = (log) => {
    const change = log.changes?.[0];

    if (!change) {
        return 'Без деталізації змін';
    }

    return `${change.label}: ${change.old ?? '—'} → ${change.new ?? '—'}`;
};

const summaryCards = [
    {
        key: 'total_actions',
        label: 'Дій',
        icon: Activity,
    },
    {
        key: 'active_managers',
        label: 'Активних',
        icon: UsersRound,
    },
    {
        key: 'module_views_count',
        label: 'Перегляди',
        icon: Eye,
    },
    {
        key: 'catalog_actions_count',
        label: 'Каталог',
        icon: Tags,
    },
    {
        key: 'order_actions_count',
        label: 'Замовлення',
        icon: ShoppingCart,
    },
    {
        key: 'settings_actions_count',
        label: 'Налаштув.',
        icon: Settings,
    },
];
</script>

<template>
    <Head title="Аналіз менеджерів" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-bold text-slate-500">Операції</p>
                    <h1 class="text-3xl font-black tracking-tight text-[#343241]">Аналіз менеджерів</h1>
                    <p class="mt-1 text-sm font-medium text-slate-500">
                        Остання активність: {{ summary.last_activity_at || '—' }}
                    </p>
                </div>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
                    <div
                        v-for="card in summaryCards"
                        :key="card.key"
                        class="min-w-28 rounded-lg bg-slate-50 px-4 py-3"
                    >
                        <div class="mb-1 flex items-center gap-2 text-xs font-black uppercase text-slate-500">
                            <component :is="card.icon" class="h-4 w-4" />
                            {{ card.label }}
                        </div>
                        <div class="text-xl font-black text-[#343241]">{{ summary[card.key] ?? 0 }}</div>
                    </div>
                </div>
            </div>
        </template>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
            <section class="space-y-5">
                <form
                    class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]"
                    @submit.prevent="submit"
                >
                    <div class="grid gap-3 lg:grid-cols-[minmax(180px,1.1fr)_minmax(160px,0.9fr)_150px_150px_auto_auto]">
                        <label>
                            <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">Менеджер</span>
                            <select
                                v-model="form.manager_id"
                                class="h-11 w-full rounded-lg border-slate-200 text-sm font-bold text-[#343241] shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                            >
                                <option value="">Усі менеджери</option>
                                <option
                                    v-for="manager in managers"
                                    :key="manager.id"
                                    :value="manager.id"
                                >
                                    {{ manager.name }} · {{ manager.role_label }}
                                </option>
                            </select>
                        </label>

                        <label>
                            <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">Тип дії</span>
                            <select
                                v-model="form.event_group"
                                class="h-11 w-full rounded-lg border-slate-200 text-sm font-bold text-[#343241] shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                            >
                                <option
                                    v-for="group in eventGroups"
                                    :key="group.value"
                                    :value="group.value"
                                >
                                    {{ group.label }}
                                </option>
                            </select>
                        </label>

                        <label>
                            <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">Від</span>
                            <input
                                v-model="form.date_from"
                                type="date"
                                class="h-11 w-full rounded-lg border-slate-200 text-sm font-bold text-[#343241] shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                            >
                        </label>

                        <label>
                            <span class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">До</span>
                            <input
                                v-model="form.date_to"
                                type="date"
                                class="h-11 w-full rounded-lg border-slate-200 text-sm font-bold text-[#343241] shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                            >
                        </label>

                        <button
                            type="submit"
                            class="mt-5 inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-[#343241] px-5 text-sm font-black text-white lg:mt-6"
                        >
                            <Search class="h-4 w-4" />
                            Застосувати
                        </button>

                        <button
                            type="button"
                            class="mt-5 inline-flex h-11 items-center justify-center gap-2 rounded-lg border border-slate-200 px-4 text-sm font-black text-slate-600 transition hover:bg-slate-50 lg:mt-6"
                            @click="resetFilters"
                        >
                            <RotateCcw class="h-4 w-4" />
                            Скинути
                        </button>
                    </div>
                </form>

                <section class="overflow-hidden rounded-lg bg-white shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                        <div>
                            <h2 class="text-xl font-black text-[#343241]">Журнал дій</h2>
                            <p class="text-sm font-medium text-slate-500">Компактний список: натисни запис, щоб розгорнути деталі.</p>
                        </div>
                        <span class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600">
                            <Filter class="h-3.5 w-3.5" />
                            {{ logs.total ?? 0 }} записів
                        </span>
                    </div>

                    <div class="divide-y divide-slate-100">
                        <article
                            v-for="log in logs.data"
                            :key="log.id"
                            class="group bg-white transition hover:bg-slate-50/70"
                        >
                            <button
                                type="button"
                                class="grid w-full grid-cols-[minmax(0,1fr)_36px] items-center gap-3 px-5 py-4 text-left transition"
                                :aria-expanded="isExpanded(log.id)"
                                @click="toggleLog(log.id)"
                            >
                                <div class="grid min-w-0 gap-3 lg:grid-cols-[160px_minmax(190px,0.9fr)_minmax(180px,1fr)_minmax(200px,1.1fr)] lg:items-center">
                                    <div class="flex items-center gap-2 text-sm font-black text-[#343241]">
                                        <Clock3 class="h-4 w-4 shrink-0 text-slate-400" />
                                        <span class="truncate">{{ log.created_at }}</span>
                                    </div>

                                    <div class="flex min-w-0 items-center gap-3">
                                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[#f0edff] text-[#7561f7]">
                                            <UserRoundCheck class="h-5 w-5" />
                                        </div>
                                        <div class="min-w-0">
                                            <div class="truncate text-sm font-black text-[#343241]">{{ log.manager.name }}</div>
                                            <div class="truncate text-xs font-semibold text-slate-500">
                                                {{ log.manager.role_label }} · {{ log.manager.email || '—' }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="min-w-0">
                                        <div
                                            class="inline-flex max-w-full items-center gap-2 rounded-full px-3 py-1 text-xs font-black ring-1"
                                            :class="log.event_class"
                                        >
                                            <component :is="eventIcon(log)" class="h-3.5 w-3.5 shrink-0" />
                                            <span class="truncate">{{ log.event_label }}</span>
                                        </div>
                                        <div class="mt-1 truncate text-xs font-semibold text-slate-500">
                                            {{ log.subject_label || log.description || '—' }}
                                        </div>
                                    </div>

                                    <div class="min-w-0 text-sm">
                                        <div class="truncate font-bold text-[#343241]">
                                            {{ compactChange(log) }}
                                        </div>
                                        <div class="mt-0.5 text-xs font-semibold text-slate-400">
                                            {{ changesCount(log) }} змін
                                        </div>
                                    </div>
                                </div>

                                <span
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition group-hover:border-[#7561f7]/30 group-hover:text-[#7561f7]"
                                    :class="isExpanded(log.id) ? 'border-[#7561f7]/30 bg-[#f0edff] text-[#7561f7]' : ''"
                                    aria-hidden="true"
                                >
                                    <ChevronDown
                                        class="h-5 w-5 transition"
                                        :class="isExpanded(log.id) ? 'rotate-180' : ''"
                                    />
                                </span>
                            </button>

                            <div
                                v-if="isExpanded(log.id)"
                                class="border-t border-slate-100 bg-[#fbfbff] px-5 py-4"
                            >
                                <div class="max-w-5xl text-sm leading-6">
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1 font-bold text-[#343241]">
                                        <span
                                            class="inline-flex rounded-full px-3 py-1 text-xs font-black ring-1"
                                            :class="log.event_class"
                                        >
                                            {{ log.event_label }}
                                        </span>
                                        <span>{{ log.description || 'Подія без додаткового опису' }}</span>
                                    </div>

                                    <p class="mt-3 text-slate-600">
                                        Менеджер:
                                        <span class="font-bold text-[#343241]">{{ log.manager.name }}</span>
                                        <span class="mx-1 text-slate-300">·</span>
                                        Обʼєкт:
                                        <Link
                                            v-if="log.subject_url"
                                            :href="log.subject_url"
                                            class="font-bold text-[#7561f7] transition hover:text-[#29277f]"
                                            @click.stop
                                        >
                                            {{ log.subject_label }}
                                        </Link>
                                        <span v-else class="font-bold text-[#343241]">{{ log.subject_label || '—' }}</span>
                                        <span class="mx-1 text-slate-300">·</span>
                                        IP:
                                        <span class="font-bold text-[#343241]">{{ log.ip_address || '—' }}</span>
                                        <span class="mx-1 text-slate-300">·</span>
                                        Час:
                                        <span class="font-bold text-[#343241]">{{ log.created_at }}</span>
                                    </p>

                                    <div v-if="log.changes.length" class="mt-3">
                                        <div class="text-xs font-black uppercase tracking-wide text-slate-400">
                                            Зміни
                                        </div>
                                        <ul class="mt-2 space-y-1.5 text-slate-700">
                                            <li
                                                v-for="change in log.changes"
                                                :key="`${log.id}-${change.field}`"
                                                class="break-words"
                                            >
                                                <span class="font-black text-[#343241]">{{ change.label }}:</span>
                                                <span class="ml-1">{{ change.old ?? '—' }}</span>
                                                <span class="mx-1 text-slate-400">→</span>
                                                <span class="font-bold text-[#343241]">{{ change.new ?? '—' }}</span>
                                            </li>
                                        </ul>
                                        <p
                                            v-if="log.changes_extra_count > 0"
                                            class="mt-2 text-xs font-bold text-slate-500"
                                        >
                                            Ще {{ log.changes_extra_count }} полів приховано у короткому перегляді.
                                        </p>
                                    </div>
                                    <p v-else class="mt-3 text-sm font-semibold text-slate-500">
                                        Для цієї події немає детального diff.
                                    </p>
                                </div>
                            </div>
                        </article>

                        <div v-if="logs.data.length === 0" class="px-5 py-14 text-center">
                            <Activity class="mx-auto h-9 w-9 text-slate-300" />
                            <div class="mt-3 text-base font-black text-[#343241]">Активності за цей період немає</div>
                            <div class="text-sm font-medium text-slate-500">Після входів або змін у товарах і замовленнях записи зʼявляться тут.</div>
                        </div>
                    </div>

                    <div v-if="logs.links?.length > 3" class="flex flex-wrap gap-2 border-t border-slate-100 px-5 py-4">
                        <template v-for="link in logs.links" :key="link.label">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                class="rounded-lg px-3 py-2 text-sm font-bold transition"
                                :class="link.active ? 'bg-[#7561f7] text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                v-html="link.label"
                            />
                            <span
                                v-else
                                class="rounded-lg bg-slate-50 px-3 py-2 text-sm font-bold text-slate-300"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </section>
            </section>

            <aside class="space-y-5">
                <section class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-lg font-black text-[#343241]">Активність персоналу</h2>
                        <CalendarDays class="h-5 w-5 text-slate-400" />
                    </div>
                    <div class="space-y-3">
                        <div
                            v-for="manager in managerStats"
                            :key="manager.user_id"
                            class="rounded-lg border border-slate-100 p-4"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="font-black text-[#343241]">{{ manager.name }}</div>
                                    <div class="text-xs font-semibold text-slate-500">{{ manager.role_label }} · {{ manager.email || '—' }}</div>
                                </div>
                                <span class="rounded-full bg-[#f0edff] px-3 py-1 text-xs font-black text-[#7561f7]">
                                    {{ manager.total_actions }}
                                </span>
                            </div>
                            <div class="mt-3 grid grid-cols-3 gap-2 text-center text-xs font-black text-slate-600">
                                <div class="rounded-lg bg-slate-50 px-2 py-2">
                                    <span class="block text-slate-400">Входи</span>
                                    {{ manager.logins_count }}
                                </div>
                                <div class="rounded-lg bg-slate-50 px-2 py-2">
                                    <span class="block text-slate-400">Зам.</span>
                                    {{ manager.order_actions_count }}
                                </div>
                                <div class="rounded-lg bg-slate-50 px-2 py-2">
                                    <span class="block text-slate-400">Тов.</span>
                                    {{ manager.product_actions_count }}
                                </div>
                            </div>
                            <div class="mt-3 text-xs font-semibold text-slate-500">
                                Остання дія: {{ manager.last_activity_at || '—' }}
                            </div>
                        </div>
                        <div v-if="managerStats.length === 0" class="rounded-lg border border-dashed border-slate-300 px-4 py-8 text-center text-sm font-semibold text-slate-500">
                            Немає активності за вибраний період.
                        </div>
                    </div>
                </section>
            </aside>
        </div>
    </AuthenticatedLayout>
</template>
