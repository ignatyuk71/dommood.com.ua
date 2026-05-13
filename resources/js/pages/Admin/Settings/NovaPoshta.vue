<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    Box,
    Building2,
    CheckCircle2,
    Cloud,
    KeyRound,
    Loader2,
    MapPin,
    Phone,
    RefreshCw,
    Save,
    Search,
    ShieldCheck,
    Warehouse,
} from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps({
    settings: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    is_active: props.settings.is_active ?? true,
    api_key: '',
    api_url: props.settings.api_url ?? 'https://api.novaposhta.ua/v2.0/json/',
    sender_phone: props.settings.sender_phone ?? '',
    sender_city_ref: props.settings.sender_city_ref ?? '',
    sender_city_name: props.settings.sender_city_name ?? '',
    sender_warehouse_ref: props.settings.sender_warehouse_ref ?? '',
    sender_warehouse_name: props.settings.sender_warehouse_name ?? '',
    sender_ref: props.settings.sender_ref ?? '',
    sender_name: props.settings.sender_name ?? '',
    contact_ref: props.settings.contact_ref ?? '',
    contact_name: props.settings.contact_name ?? '',
    default_weight: props.settings.default_weight ?? '1',
});

const citySearch = ref(props.settings.sender_city_name ?? '');
const warehouseSearch = ref(props.settings.sender_warehouse_name ?? '');
const cityResults = ref([]);
const warehouseResults = ref([]);
const cityLoading = ref(false);
const warehouseLoading = ref(false);
const syncLoading = ref(false);
const lookupError = ref('');
const syncMessage = ref('');

let cityTimer = null;
let warehouseTimer = null;

const inputClass = 'mt-1 h-11 w-full rounded-xl border-slate-200 bg-white text-sm font-semibold text-[#343241] shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]';
const labelClass = 'text-xs font-extrabold uppercase tracking-wide text-slate-500';
const iconBoxClass = 'inline-flex h-12 w-12 items-center justify-center rounded-xl';

const clearMaskedKey = (event) => {
    if (props.settings.has_api_key && form.api_key === '') {
        event.target.value = '';
    }
};

const updateApiKey = (event) => {
    form.api_key = event.target.value.includes('•') ? '' : event.target.value;
};

const submit = () => {
    form
        .transform((data) => ({
            ...data,
            is_active: data.is_active ? 1 : 0,
        }))
        .put(route('admin.settings.nova-poshta.update'), {
            preserveScroll: true,
            onSuccess: () => form.reset('api_key'),
        });
};

const searchCities = () => {
    window.clearTimeout(cityTimer);
    cityTimer = window.setTimeout(async () => {
        const query = citySearch.value.trim();
        cityResults.value = [];
        lookupError.value = '';

        if (query.length < 2) {
            return;
        }

        cityLoading.value = true;

        try {
            const { data } = await window.axios.get(route('shipping.nova-poshta.cities'), {
                params: { q: query, limit: 8 },
            });
            cityResults.value = data.items ?? [];
        } catch (error) {
            lookupError.value = error.response?.data?.error ?? 'Не вдалося знайти місто. Перевір API ключ.';
        } finally {
            cityLoading.value = false;
        }
    }, 300);
};

const selectCity = (city) => {
    citySearch.value = [city.name, city.area].filter(Boolean).join(', ');
    form.sender_city_name = citySearch.value;
    form.sender_city_ref = city.ref;
    form.sender_warehouse_name = '';
    form.sender_warehouse_ref = '';
    warehouseSearch.value = '';
    cityResults.value = [];
    warehouseResults.value = [];
};

const searchWarehouses = () => {
    window.clearTimeout(warehouseTimer);
    warehouseTimer = window.setTimeout(async () => {
        const query = warehouseSearch.value.trim();
        warehouseResults.value = [];
        lookupError.value = '';

        if (!form.sender_city_ref) {
            lookupError.value = 'Спочатку вибери місто зі списку.';
            return;
        }

        if (query.length < 1) {
            return;
        }

        warehouseLoading.value = true;

        try {
            const { data } = await window.axios.get(route('shipping.nova-poshta.warehouses'), {
                params: { city_ref: form.sender_city_ref, q: query, limit: 10 },
            });
            warehouseResults.value = data.items ?? [];
        } catch (error) {
            lookupError.value = error.response?.data?.error ?? 'Не вдалося знайти відділення.';
        } finally {
            warehouseLoading.value = false;
        }
    }, 300);
};

const selectWarehouse = (warehouse) => {
    warehouseSearch.value = warehouse.name;
    form.sender_warehouse_name = warehouse.name;
    form.sender_warehouse_ref = warehouse.ref;
    warehouseResults.value = [];
};

const syncSender = async () => {
    syncLoading.value = true;
    syncMessage.value = '';
    lookupError.value = '';

    try {
        const { data } = await window.axios.post(route('admin.settings.nova-poshta.sync-sender'));
        form.sender_ref = data.sender_ref ?? '';
        form.sender_name = data.sender_name ?? '';
        form.contact_ref = data.contact_ref ?? '';
        form.contact_name = data.contact_name ?? '';
        form.sender_phone = data.sender_phone ?? form.sender_phone;
        syncMessage.value = data.message ?? 'Дані відправника синхронізовано';
    } catch (error) {
        lookupError.value = error.response?.data?.error ?? 'Не вдалося синхронізувати відправника.';
    } finally {
        syncLoading.value = false;
    }
};
</script>

<template>
    <Head title="API Нова пошта" />

    <AuthenticatedLayout>
        <form class="space-y-5" @submit.prevent="submit">
            <section class="flex flex-col gap-4 rounded-lg bg-white px-5 py-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)] lg:flex-row lg:items-center lg:justify-between lg:px-7">
                <div class="flex items-center gap-4">
                    <div class="inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-[#ef3e42] text-white shadow-[0_18px_35px_rgba(239,62,66,0.22)]">
                        <Box class="h-8 w-8" />
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-3xl font-extrabold tracking-tight text-[#343241]">Нова Пошта</h1>
                            <span class="rounded-lg bg-blue-50 px-2 py-1 text-xs font-extrabold text-blue-600">API 2.0</span>
                        </div>
                        <p class="mt-1 text-sm font-semibold text-slate-500">API ключ, відправник, місто та відділення для checkout.</p>
                    </div>
                </div>

                <button
                    type="submit"
                    class="inline-flex h-12 items-center justify-center gap-2 rounded-xl bg-[#111827] px-6 text-sm font-extrabold text-white shadow-[0_16px_32px_rgba(17,24,39,0.18)] transition hover:bg-[#343241]"
                    :disabled="form.processing"
                >
                    <Save class="h-5 w-5" />
                    Зберегти зміни
                </button>
            </section>

            <div v-if="lookupError" class="rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm font-bold text-red-700">
                {{ lookupError }}
            </div>
            <div v-if="syncMessage" class="rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700">
                {{ syncMessage }}
            </div>

            <section class="grid gap-5 xl:grid-cols-[minmax(0,0.9fr)_minmax(520px,1.1fr)]">
                <div class="space-y-5">
                    <div class="overflow-hidden rounded-lg border-t-4 border-[#7561f7] bg-white shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                        <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4">
                            <div class="flex items-center gap-4">
                                <span :class="[iconBoxClass, 'bg-indigo-50 text-[#7561f7]']">
                                    <ShieldCheck class="h-6 w-6" />
                                </span>
                                <div>
                                    <h2 class="text-xl font-extrabold text-[#343241]">API ключ</h2>
                                    <p class="mt-1 text-sm font-semibold text-slate-500">Ключ зберігається зашифровано.</p>
                                </div>
                            </div>
                            <span class="rounded-full px-3 py-1 text-xs font-extrabold" :class="settings.is_active && settings.has_api_key ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'">
                                {{ settings.is_active && settings.has_api_key ? 'Підключено' : 'Не налаштовано' }}
                            </span>
                        </div>

                        <div class="space-y-4 px-5 py-5">
                            <label class="inline-flex items-center gap-3 text-sm font-extrabold text-[#343241]">
                                <input v-model="form.is_active" type="checkbox" class="h-5 w-5 rounded border-slate-300 text-[#7561f7] focus:ring-[#7561f7]" />
                                API активне
                            </label>

                            <div>
                                <label :class="labelClass">API ключ</label>
                                <div class="relative">
                                    <KeyRound class="pointer-events-none absolute left-3 top-4 h-4 w-4 text-slate-400" />
                                    <input
                                        :value="form.api_key || (settings.has_api_key ? '••••••••••••••••••••' : '')"
                                        :class="[inputClass, 'pl-10']"
                                        type="text"
                                        autocomplete="new-password"
                                        placeholder="Встав API ключ"
                                        @focus="clearMaskedKey"
                                        @input="updateApiKey"
                                    />
                                </div>
                                <p class="mt-1 text-xs font-bold text-slate-500">Якщо ключ уже збережений, бачиш маску. Новий ключ вводиш тільки коли треба замінити.</p>
                                <InputError class="mt-1" :message="form.errors.api_key" />
                            </div>

                            <div>
                                <label :class="labelClass">API URL</label>
                                <div class="relative">
                                    <Cloud class="pointer-events-none absolute left-3 top-4 h-4 w-4 text-slate-400" />
                                    <input v-model="form.api_url" :class="[inputClass, 'pl-10']" type="url" required />
                                </div>
                                <InputError class="mt-1" :message="form.errors.api_url" />
                            </div>
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-lg bg-white shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                        <div class="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-4">
                            <div>
                                <h2 class="text-lg font-extrabold text-[#343241]">Відправник з кабінету НП</h2>
                                <p class="mt-1 text-sm font-semibold text-slate-500">Ці дані витягуються з API після збереження ключа.</p>
                            </div>
                            <button
                                type="button"
                                class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-[#7561f7] px-4 text-sm font-extrabold text-[#7561f7] transition hover:bg-[#f5f4ff] disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="syncLoading || !settings.has_api_key"
                                @click="syncSender"
                            >
                                <Loader2 v-if="syncLoading" class="h-4 w-4 animate-spin" />
                                <RefreshCw v-else class="h-4 w-4" />
                                Витягнути
                            </button>
                        </div>

                        <div class="grid gap-3 px-5 py-5 sm:grid-cols-2">
                            <div class="rounded-xl bg-slate-50 px-4 py-3">
                                <div class="text-xs font-extrabold uppercase text-slate-500">Відправник</div>
                                <div class="mt-1 text-sm font-extrabold text-[#343241]">{{ form.sender_name || 'Ще не витягнуто' }}</div>
                                <div class="mt-1 break-all text-xs font-bold text-slate-500">{{ form.sender_ref || 'Ref зʼявиться після синхронізації' }}</div>
                            </div>
                            <div class="rounded-xl bg-slate-50 px-4 py-3">
                                <div class="text-xs font-extrabold uppercase text-slate-500">Контакт</div>
                                <div class="mt-1 text-sm font-extrabold text-[#343241]">{{ form.contact_name || 'Ще не витягнуто' }}</div>
                                <div class="mt-1 break-all text-xs font-bold text-slate-500">{{ form.contact_ref || 'Ref зʼявиться після синхронізації' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="overflow-visible rounded-lg bg-white shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <div class="flex items-center gap-4 border-b border-slate-100 px-5 py-4">
                        <span :class="[iconBoxClass, 'bg-rose-50 text-rose-500']">
                            <MapPin class="h-6 w-6" />
                        </span>
                        <div>
                            <h2 class="text-xl font-extrabold text-[#343241]">Дані відправки</h2>
                            <p class="mt-1 text-sm font-semibold text-slate-500">Місто та відділення вибираються пошуком через API.</p>
                        </div>
                    </div>

                    <div class="space-y-4 px-5 py-5">
                        <div>
                            <label :class="labelClass">Контактний телефон</label>
                            <div class="relative">
                                <Phone class="pointer-events-none absolute left-3 top-4 h-4 w-4 text-slate-400" />
                                <input v-model="form.sender_phone" :class="[inputClass, 'pl-10']" type="text" placeholder="380..." />
                            </div>
                            <InputError class="mt-1" :message="form.errors.sender_phone" />
                        </div>

                        <div class="relative">
                            <label :class="labelClass">Місто відправника</label>
                            <div class="relative">
                                <Building2 class="pointer-events-none absolute left-3 top-4 h-4 w-4 text-slate-400" />
                                <input
                                    v-model="citySearch"
                                    :class="[inputClass, 'pl-10 pr-10']"
                                    type="text"
                                    placeholder="Почни вводити місто"
                                    @input="searchCities"
                                />
                                <Loader2 v-if="cityLoading" class="absolute right-3 top-4 h-4 w-4 animate-spin text-slate-400" />
                                <Search v-else class="pointer-events-none absolute right-3 top-4 h-4 w-4 text-slate-400" />
                            </div>
                            <div v-if="cityResults.length" class="absolute z-30 mt-2 max-h-64 w-full overflow-y-auto rounded-xl border border-slate-100 bg-white p-2 shadow-[0_18px_40px_rgba(61,58,101,0.16)]">
                                <button
                                    v-for="city in cityResults"
                                    :key="city.ref"
                                    type="button"
                                    class="block w-full rounded-lg px-3 py-2 text-left text-sm font-bold text-[#343241] hover:bg-[#f5f4ff]"
                                    @click="selectCity(city)"
                                >
                                    {{ city.name }}
                                    <span class="block text-xs font-semibold text-slate-500">{{ [city.area, city.region].filter(Boolean).join(', ') }}</span>
                                </button>
                            </div>
                            <div v-if="form.sender_city_ref" class="mt-1 inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-1 text-xs font-bold text-emerald-700">
                                <CheckCircle2 class="h-3.5 w-3.5" />
                                Місто вибрано
                            </div>
                            <InputError class="mt-1" :message="form.errors.sender_city_ref" />
                        </div>

                        <div class="relative">
                            <label :class="labelClass">Відділення / поштомат</label>
                            <div class="relative">
                                <Warehouse class="pointer-events-none absolute left-3 top-4 h-4 w-4 text-slate-400" />
                                <input
                                    v-model="warehouseSearch"
                                    :class="[inputClass, 'pl-10 pr-10']"
                                    type="text"
                                    placeholder="Почни вводити номер або адресу"
                                    @input="searchWarehouses"
                                />
                                <Loader2 v-if="warehouseLoading" class="absolute right-3 top-4 h-4 w-4 animate-spin text-slate-400" />
                                <Search v-else class="pointer-events-none absolute right-3 top-4 h-4 w-4 text-slate-400" />
                            </div>
                            <div v-if="warehouseResults.length" class="absolute z-30 mt-2 max-h-72 w-full overflow-y-auto rounded-xl border border-slate-100 bg-white p-2 shadow-[0_18px_40px_rgba(61,58,101,0.16)]">
                                <button
                                    v-for="warehouse in warehouseResults"
                                    :key="warehouse.ref"
                                    type="button"
                                    class="block w-full rounded-lg px-3 py-2 text-left text-sm font-bold text-[#343241] hover:bg-[#f5f4ff]"
                                    @click="selectWarehouse(warehouse)"
                                >
                                    {{ warehouse.name }}
                                    <span class="block text-xs font-semibold text-slate-500">{{ warehouse.address }}</span>
                                </button>
                            </div>
                            <div v-if="form.sender_warehouse_ref" class="mt-1 inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-1 text-xs font-bold text-emerald-700">
                                <CheckCircle2 class="h-3.5 w-3.5" />
                                Відділення вибрано
                            </div>
                            <InputError class="mt-1" :message="form.errors.sender_warehouse_ref" />
                        </div>

                        <div>
                            <label :class="labelClass">Вага за замовчуванням, кг</label>
                            <input v-model="form.default_weight" :class="inputClass" type="number" min="0.1" max="1000" step="0.1" />
                            <InputError class="mt-1" :message="form.errors.default_weight" />
                        </div>

                        <button
                            type="submit"
                            class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-[#343241] text-sm font-extrabold text-white transition hover:bg-[#2b2938]"
                            :disabled="form.processing"
                        >
                            <Save class="h-5 w-5" />
                            Зберегти Нову пошту
                        </button>
                    </div>
                </div>
            </section>
        </form>
    </AuthenticatedLayout>
</template>
