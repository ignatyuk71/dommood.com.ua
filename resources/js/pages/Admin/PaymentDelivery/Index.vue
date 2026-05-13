<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/components/InputError.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Banknote,
    CreditCard,
    Pencil,
    Save,
    SlidersHorizontal,
    Trash2,
    Truck,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps({
    section: {
        type: String,
        required: true,
    },
    tabs: {
        type: Array,
        required: true,
    },
    deliveryMethods: {
        type: Array,
        required: true,
    },
    paymentMethods: {
        type: Array,
        required: true,
    },
    tariffs: {
        type: Array,
        required: true,
    },
    transactions: {
        type: Array,
        required: true,
    },
    options: {
        type: Object,
        required: true,
    },
});

const sectionMeta = {
    'delivery-methods': {
        title: 'Методи доставки',
        eyebrow: 'Оплата та доставка',
        description: 'Керуємо способами доставки, які будуть доступні у checkout.',
        icon: Truck,
    },
    'payment-methods': {
        title: 'Методи оплати',
        eyebrow: 'Оплата та доставка',
        description: 'Налаштовуємо активні способи оплати та комісії.',
        icon: CreditCard,
    },
    tariffs: {
        title: 'Тарифи',
        eyebrow: 'Оплата та доставка',
        description: 'Правила вартості доставки за методом, містом, регіоном або сумою замовлення.',
        icon: SlidersHorizontal,
    },
    transactions: {
        title: 'Транзакції',
        eyebrow: 'Оплата та доставка',
        description: 'Журнал callback, статусів оплат і зовнішніх ID платіжних провайдерів.',
        icon: Banknote,
    },
};

const activeMeta = computed(() => sectionMeta[props.section] ?? sectionMeta['delivery-methods']);
const editingDeliveryId = ref(null);
const editingPaymentId = ref(null);
const editingTariffId = ref(null);

const boolValue = (value) => (value ? 1 : 0);

const deliveryForm = useForm({
    name: '',
    code: '',
    provider: 'nova_poshta',
    type: 'branch',
    description: '',
    base_price: '0.00',
    free_from: '',
    is_active: true,
    sort_order: 0,
});

const paymentForm = useForm({
    name: '',
    code: '',
    type: 'cod',
    description: '',
    fee_percent: '0.00',
    fixed_fee: '0.00',
    is_active: true,
    sort_order: 0,
});

const tariffForm = useForm({
    delivery_method_id: '',
    name: '',
    code: '',
    region: '',
    city: '',
    min_order: '0.00',
    max_order: '',
    price: '0.00',
    free_from: '',
    is_active: true,
    sort_order: 0,
});

const resetDelivery = () => {
    editingDeliveryId.value = null;
    deliveryForm.reset();
    deliveryForm.clearErrors();
};

const resetPayment = () => {
    editingPaymentId.value = null;
    paymentForm.reset();
    paymentForm.clearErrors();
};

const resetTariff = () => {
    editingTariffId.value = null;
    tariffForm.reset();
    tariffForm.clearErrors();
};

const editDelivery = (method) => {
    editingDeliveryId.value = method.id;
    deliveryForm.defaults({
        name: method.name,
        code: method.code,
        provider: method.provider,
        type: method.type,
        description: method.description ?? '',
        base_price: method.base_price_value,
        free_from: method.free_from_value,
        is_active: method.is_active,
        sort_order: method.sort_order,
    });
    deliveryForm.reset();
    deliveryForm.clearErrors();
};

const editPayment = (method) => {
    editingPaymentId.value = method.id;
    paymentForm.defaults({
        name: method.name,
        code: method.code,
        type: method.type,
        description: method.description ?? '',
        fee_percent: method.fee_percent,
        fixed_fee: method.fixed_fee_value,
        is_active: method.is_active,
        sort_order: method.sort_order,
    });
    paymentForm.reset();
    paymentForm.clearErrors();
};

const editTariff = (tariff) => {
    editingTariffId.value = tariff.id;
    tariffForm.defaults({
        delivery_method_id: tariff.delivery_method_id ?? '',
        name: tariff.name,
        code: tariff.code,
        region: tariff.region ?? '',
        city: tariff.city ?? '',
        min_order: tariff.min_order_value,
        max_order: tariff.max_order_value,
        price: tariff.price_value,
        free_from: tariff.free_from_value,
        is_active: tariff.is_active,
        sort_order: tariff.sort_order,
    });
    tariffForm.reset();
    tariffForm.clearErrors();
};

const submitDelivery = () => {
    const options = {
        preserveScroll: true,
        onSuccess: resetDelivery,
    };
    const payload = (data) => ({
        ...data,
        is_active: boolValue(data.is_active),
    });

    if (editingDeliveryId.value) {
        deliveryForm.transform(payload).put(route('admin.payment-delivery.delivery-methods.update', editingDeliveryId.value), options);
        return;
    }

    deliveryForm.transform(payload).post(route('admin.payment-delivery.delivery-methods.store'), options);
};

const submitPayment = () => {
    const options = {
        preserveScroll: true,
        onSuccess: resetPayment,
    };
    const payload = (data) => ({
        ...data,
        is_active: boolValue(data.is_active),
    });

    if (editingPaymentId.value) {
        paymentForm.transform(payload).put(route('admin.payment-delivery.payment-methods.update', editingPaymentId.value), options);
        return;
    }

    paymentForm.transform(payload).post(route('admin.payment-delivery.payment-methods.store'), options);
};

const submitTariff = () => {
    const options = {
        preserveScroll: true,
        onSuccess: resetTariff,
    };
    const payload = (data) => ({
        ...data,
        delivery_method_id: data.delivery_method_id || null,
        is_active: boolValue(data.is_active),
    });

    if (editingTariffId.value) {
        tariffForm.transform(payload).put(route('admin.payment-delivery.tariffs.update', editingTariffId.value), options);
        return;
    }

    tariffForm.transform(payload).post(route('admin.payment-delivery.tariffs.store'), options);
};

const destroyItem = (url, message) => {
    if (!window.confirm(message)) {
        return;
    }

    router.delete(url, {
        preserveScroll: true,
    });
};

const statusClass = (active) => active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500';
const inputClass = 'mt-1 h-9 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]';
const labelClass = 'text-xs font-bold uppercase text-slate-500';
const providerConnections = computed(() => props.options.paymentConnections ?? {});
const connectionFor = (type) => providerConnections.value[type] ?? null;
const selectedPaymentConnection = computed(() => connectionFor(paymentForm.type));
const connectionStatusClass = (connection) => connection?.configured
    ? 'bg-emerald-50 text-emerald-700'
    : 'bg-amber-50 text-amber-700';
const connectionStatusLabel = (connection) => {
    if (!connection) return null;
    if (connection.configured) return `${connection.label} підключено`;
    if (connection.enabled) return `${connection.label}: не вистачає ключів`;

    return `${connection.label} вимкнено в налаштуваннях`;
};
</script>

<template>
    <Head :title="activeMeta.title" />

    <AuthenticatedLayout>
        <div class="space-y-5">
            <nav class="flex flex-wrap gap-2 rounded-lg bg-white p-2 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                <Link
                    v-for="tab in tabs"
                    :key="tab.value"
                    :href="tab.route"
                    class="inline-flex h-9 items-center rounded-lg px-3 text-sm font-bold transition"
                    :class="section === tab.value ? 'bg-[#7561f7] text-white shadow-[0_10px_24px_rgba(117,97,247,0.22)]' : 'text-slate-600 hover:bg-[#f5f4ff] hover:text-[#7561f7]'"
                >
                    {{ tab.label }}
                </Link>
            </nav>

            <section v-if="section === 'delivery-methods'" class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div class="rounded-lg bg-white shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <div class="border-b border-slate-100 px-4 py-4">
                        <h2 class="text-lg font-bold text-[#343241]">Методи доставки</h2>
                        <p class="mt-0.5 text-sm text-slate-500">Активні методи потім підуть у checkout і картку замовлення.</p>
                    </div>

                    <div class="divide-y divide-slate-100">
                        <div v-for="method in deliveryMethods" :key="method.id" class="grid gap-4 px-4 py-4 lg:grid-cols-[minmax(0,1fr)_300px_112px] lg:items-center">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <Truck class="h-4 w-4 text-[#7561f7]" />
                                    <div class="font-bold text-[#343241]">{{ method.name }}</div>
                                    <span class="rounded-full px-2 py-0.5 text-xs font-bold" :class="statusClass(method.is_active)">
                                        {{ method.is_active ? 'Активний' : 'Вимкнений' }}
                                    </span>
                                </div>
                                <div class="mt-1 flex flex-wrap gap-2 text-xs font-semibold text-slate-500">
                                    <span>{{ method.provider_label }}</span>
                                    <span>·</span>
                                    <span>{{ method.type_label }}</span>
                                    <span>·</span>
                                    <code>{{ method.code }}</code>
                                </div>
                                <p v-if="method.description" class="mt-2 text-sm text-slate-500">{{ method.description }}</p>
                            </div>
                            <div class="space-y-1 text-sm font-semibold text-slate-600">
                                <div class="flex items-center justify-between gap-4 whitespace-nowrap">
                                    <span>База:</span>
                                    <span class="font-bold text-[#343241]">{{ method.base_price }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-4 whitespace-nowrap">
                                    <span>Безкоштовно від:</span>
                                    <span class="font-bold text-[#343241]">{{ method.free_from }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-4 whitespace-nowrap">
                                    <span>Тарифів:</span>
                                    <span class="font-bold text-[#343241]">{{ method.tariffs_count }}</span>
                                </div>
                            </div>
                            <div class="flex justify-end gap-2">
                                <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:border-[#7561f7] hover:text-[#7561f7]" @click="editDelivery(method)">
                                    <Pencil class="h-4 w-4" />
                                </button>
                                <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-red-200 text-red-500 hover:bg-red-50" @click="destroyItem(route('admin.payment-delivery.delivery-methods.destroy', method.id), `Видалити метод доставки '${method.name}'?`)">
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </div>

                        <div v-if="deliveryMethods.length === 0" class="px-4 py-10 text-center text-sm font-semibold text-slate-500">
                            Методів доставки ще немає.
                        </div>
                    </div>
                </div>

                <div class="space-y-5">
                    <form class="rounded-lg bg-white p-4 shadow-[0_16px_45px_rgba(61,58,101,0.08)]" @submit.prevent="submitDelivery">
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="text-lg font-bold text-[#343241]">{{ editingDeliveryId ? 'Редагувати доставку' : 'Новий метод' }}</h2>
                            <button v-if="editingDeliveryId" type="button" class="text-slate-400 hover:text-slate-700" @click="resetDelivery">
                                <X class="h-5 w-5" />
                            </button>
                        </div>

                        <div class="mt-4 space-y-3">
                            <div>
                                <label :class="labelClass">Назва</label>
                                <input v-model="deliveryForm.name" :class="inputClass" type="text" placeholder="Нова пошта: відділення" required />
                                <InputError class="mt-1" :message="deliveryForm.errors.name" />
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label :class="labelClass">Код</label>
                                    <input v-model="deliveryForm.code" :class="inputClass" type="text" placeholder="nova_poshta_branch" />
                                    <InputError class="mt-1" :message="deliveryForm.errors.code" />
                                </div>
                                <div>
                                    <label :class="labelClass">Порядок</label>
                                    <input v-model="deliveryForm.sort_order" :class="inputClass" type="number" min="0" />
                                </div>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label :class="labelClass">Провайдер</label>
                                    <select v-model="deliveryForm.provider" :class="inputClass">
                                        <option v-for="option in options.deliveryProviders" :key="option.value" :value="option.value">{{ option.label }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label :class="labelClass">Тип</label>
                                    <select v-model="deliveryForm.type" :class="inputClass">
                                        <option v-for="option in options.deliveryTypes" :key="option.value" :value="option.value">{{ option.label }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label :class="labelClass">Базова ціна</label>
                                    <input v-model="deliveryForm.base_price" :class="inputClass" type="number" min="0" step="0.01" />
                                </div>
                                <div>
                                    <label :class="labelClass">Безкоштовно від</label>
                                    <input v-model="deliveryForm.free_from" :class="inputClass" type="number" min="0" step="0.01" placeholder="Не задано" />
                                </div>
                            </div>
                            <div>
                                <label :class="labelClass">Опис</label>
                                <textarea v-model="deliveryForm.description" class="mt-1 min-h-20 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]" placeholder="Коротко для менеджера або checkout"></textarea>
                            </div>
                            <label class="inline-flex items-center gap-2 text-sm font-bold text-[#343241]">
                                <input v-model="deliveryForm.is_active" type="checkbox" class="rounded border-slate-300 text-[#7561f7] focus:ring-[#7561f7]" />
                                Активний
                            </label>
                            <button type="submit" class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-[#7561f7] text-sm font-bold text-white hover:bg-[#6552e8]" :disabled="deliveryForm.processing">
                                <Save class="h-4 w-4" />
                                {{ editingDeliveryId ? 'Зберегти зміни' : 'Додати метод' }}
                            </button>
                        </div>
                    </form>
                </div>
            </section>

            <section v-if="section === 'payment-methods'" class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div class="rounded-lg bg-white shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <div class="border-b border-slate-100 px-4 py-4">
                        <h2 class="text-lg font-bold text-[#343241]">Методи оплати</h2>
                        <p class="mt-0.5 text-sm text-slate-500">Список способів оплати, комісії та активність для checkout.</p>
                    </div>

                    <div class="divide-y divide-slate-100">
                        <div v-for="method in paymentMethods" :key="method.id" class="grid gap-4 px-4 py-4 lg:grid-cols-[minmax(0,1fr)_260px_112px] lg:items-center">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <CreditCard class="h-4 w-4 text-[#7561f7]" />
                                    <div class="font-bold text-[#343241]">{{ method.name }}</div>
                                    <span class="rounded-full px-2 py-0.5 text-xs font-bold" :class="statusClass(method.is_active)">
                                        {{ method.is_active ? 'Активний' : 'Вимкнений' }}
                                    </span>
                                </div>
                                <div class="mt-1 flex flex-wrap gap-2 text-xs font-semibold text-slate-500">
                                    <span>{{ method.type_label }}</span>
                                    <span>·</span>
                                    <code>{{ method.code }}</code>
                                    <template v-if="connectionFor(method.type)">
                                        <span>·</span>
                                        <span>{{ connectionFor(method.type).mode === 'live' ? 'Бойовий' : 'Тестовий' }}</span>
                                        <span class="rounded px-1.5 py-0.5" :class="connectionStatusClass(connectionFor(method.type))">
                                            {{ connectionFor(method.type).configured ? 'підключено' : 'не підключено' }}
                                        </span>
                                    </template>
                                </div>
                                <p v-if="method.description" class="mt-2 text-sm text-slate-500">{{ method.description }}</p>
                            </div>
                            <div class="space-y-1 text-sm font-semibold text-slate-600">
                                <div class="flex items-center justify-between gap-4 whitespace-nowrap">
                                    <span>Комісія:</span>
                                    <span class="font-bold text-[#343241]">{{ method.fee_percent }}%</span>
                                </div>
                                <div class="flex items-center justify-between gap-4 whitespace-nowrap">
                                    <span>Фікс. комісія:</span>
                                    <span class="font-bold text-[#343241]">{{ method.fixed_fee }}</span>
                                </div>
                            </div>
                            <div class="flex justify-end gap-2">
                                <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:border-[#7561f7] hover:text-[#7561f7]" @click="editPayment(method)">
                                    <Pencil class="h-4 w-4" />
                                </button>
                                <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-red-200 text-red-500 hover:bg-red-50" @click="destroyItem(route('admin.payment-delivery.payment-methods.destroy', method.id), `Видалити метод оплати '${method.name}'?`)">
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </div>

                        <div v-if="paymentMethods.length === 0" class="px-4 py-10 text-center text-sm font-semibold text-slate-500">
                            Методів оплати ще немає.
                        </div>
                    </div>
                </div>

                <form class="rounded-lg bg-white p-4 shadow-[0_16px_45px_rgba(61,58,101,0.08)]" @submit.prevent="submitPayment">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-lg font-bold text-[#343241]">{{ editingPaymentId ? 'Редагувати оплату' : 'Новий метод' }}</h2>
                        <button v-if="editingPaymentId" type="button" class="text-slate-400 hover:text-slate-700" @click="resetPayment">
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="mt-4 space-y-3">
                        <div>
                            <label :class="labelClass">Назва</label>
                            <input v-model="paymentForm.name" :class="inputClass" type="text" placeholder="Оплата при отриманні" required />
                            <InputError class="mt-1" :message="paymentForm.errors.name" />
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label :class="labelClass">Код</label>
                                <input v-model="paymentForm.code" :class="inputClass" type="text" placeholder="cod" />
                                <InputError class="mt-1" :message="paymentForm.errors.code" />
                            </div>
                            <div>
                                <label :class="labelClass">Тип</label>
                                <select v-model="paymentForm.type" :class="inputClass">
                                    <option v-for="option in options.paymentTypes" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div>
                                <label :class="labelClass">Комісія %</label>
                                <input v-model="paymentForm.fee_percent" :class="inputClass" type="number" min="0" step="0.01" />
                            </div>
                            <div>
                                <label :class="labelClass">Фікс. комісія</label>
                                <input v-model="paymentForm.fixed_fee" :class="inputClass" type="number" min="0" step="0.01" />
                            </div>
                            <div>
                                <label :class="labelClass">Порядок</label>
                                <input v-model="paymentForm.sort_order" :class="inputClass" type="number" min="0" />
                            </div>
                        </div>
                        <div>
                            <label :class="labelClass">Опис</label>
                            <textarea v-model="paymentForm.description" class="mt-1 min-h-20 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]" placeholder="Умови, підказки або обмеження"></textarea>
                        </div>
                        <div v-if="selectedPaymentConnection" class="space-y-3 rounded-lg border border-slate-100 bg-slate-50 p-3">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-bold text-[#343241]">{{ selectedPaymentConnection.label }}</h3>
                                    <p class="mt-0.5 text-xs font-semibold text-slate-500">Ключі провайдера зберігаються в налаштуваннях. Тут тільки вмикаємо метод для checkout.</p>
                                </div>
                                <span class="rounded-full px-2.5 py-1 text-xs font-bold" :class="connectionStatusClass(selectedPaymentConnection)">
                                    {{ connectionStatusLabel(selectedPaymentConnection) }}
                                </span>
                            </div>
                            <Link
                                :href="selectedPaymentConnection.settings_route"
                                class="inline-flex h-9 items-center rounded-lg border border-slate-200 bg-white px-3 text-xs font-bold text-[#343241] hover:border-[#7561f7] hover:text-[#7561f7]"
                            >
                                Відкрити платіжні підключення
                            </Link>
                        </div>
                        <label class="inline-flex items-center gap-2 text-sm font-bold text-[#343241]">
                            <input v-model="paymentForm.is_active" type="checkbox" class="rounded border-slate-300 text-[#7561f7] focus:ring-[#7561f7]" />
                            Активний
                        </label>
                        <button type="submit" class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-[#7561f7] text-sm font-bold text-white hover:bg-[#6552e8]" :disabled="paymentForm.processing">
                            <Save class="h-4 w-4" />
                            {{ editingPaymentId ? 'Зберегти зміни' : 'Додати метод' }}
                        </button>
                    </div>
                </form>
            </section>

            <section v-if="section === 'tariffs'" class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_390px]">
                <div class="rounded-lg bg-white shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <div class="border-b border-slate-100 px-4 py-4">
                        <h2 class="text-lg font-bold text-[#343241]">Тарифи доставки</h2>
                        <p class="mt-0.5 text-sm text-slate-500">Поки без API Нової пошти: ручні правила, які потім підключимо до checkout.</p>
                    </div>

                    <div class="divide-y divide-slate-100">
                        <div v-for="tariff in tariffs" :key="tariff.id" class="grid gap-4 px-4 py-4 lg:grid-cols-[minmax(0,1fr)_360px_112px] lg:items-center">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <Banknote class="h-4 w-4 text-[#7561f7]" />
                                    <div class="font-bold text-[#343241]">{{ tariff.name }}</div>
                                    <span class="rounded-full px-2 py-0.5 text-xs font-bold" :class="statusClass(tariff.is_active)">
                                        {{ tariff.is_active ? 'Активний' : 'Вимкнений' }}
                                    </span>
                                </div>
                                <div class="mt-1 flex flex-wrap gap-2 text-xs font-semibold text-slate-500">
                                    <span>{{ tariff.delivery_method_name || 'Усі методи' }}</span>
                                    <span>·</span>
                                    <code>{{ tariff.code }}</code>
                                </div>
                                <div class="mt-2 flex flex-wrap gap-2 text-xs font-semibold text-slate-500">
                                    <span v-if="tariff.region">Регіон: {{ tariff.region }}</span>
                                    <span v-if="tariff.city">Місто: {{ tariff.city }}</span>
                                </div>
                            </div>
                            <div class="space-y-1 text-sm font-semibold text-slate-600">
                                <div class="flex items-center justify-between gap-4 whitespace-nowrap">
                                    <span>Ціна:</span>
                                    <span class="font-bold text-[#343241]">{{ tariff.price }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-4 whitespace-nowrap">
                                    <span>Замовлення:</span>
                                    <span class="font-bold text-[#343241]">{{ tariff.min_order }} - {{ tariff.max_order }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-4 whitespace-nowrap">
                                    <span>Безкоштовно від:</span>
                                    <span class="font-bold text-[#343241]">{{ tariff.free_from }}</span>
                                </div>
                            </div>
                            <div class="flex justify-end gap-2">
                                <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:border-[#7561f7] hover:text-[#7561f7]" @click="editTariff(tariff)">
                                    <Pencil class="h-4 w-4" />
                                </button>
                                <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-red-200 text-red-500 hover:bg-red-50" @click="destroyItem(route('admin.payment-delivery.tariffs.destroy', tariff.id), `Видалити тариф '${tariff.name}'?`)">
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </div>

                        <div v-if="tariffs.length === 0" class="px-4 py-10 text-center text-sm font-semibold text-slate-500">
                            Тарифів ще немає.
                        </div>
                    </div>
                </div>

                <form class="rounded-lg bg-white p-4 shadow-[0_16px_45px_rgba(61,58,101,0.08)]" @submit.prevent="submitTariff">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-lg font-bold text-[#343241]">{{ editingTariffId ? 'Редагувати тариф' : 'Новий тариф' }}</h2>
                        <button v-if="editingTariffId" type="button" class="text-slate-400 hover:text-slate-700" @click="resetTariff">
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="mt-4 space-y-3">
                        <div>
                            <label :class="labelClass">Метод доставки</label>
                            <select v-model="tariffForm.delivery_method_id" :class="inputClass">
                                <option value="">Усі методи</option>
                                <option v-for="method in deliveryMethods" :key="method.id" :value="method.id">{{ method.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label :class="labelClass">Назва</label>
                            <input v-model="tariffForm.name" :class="inputClass" type="text" placeholder="Стандартний тариф" required />
                            <InputError class="mt-1" :message="tariffForm.errors.name" />
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label :class="labelClass">Код</label>
                                <input v-model="tariffForm.code" :class="inputClass" type="text" placeholder="standard" />
                                <InputError class="mt-1" :message="tariffForm.errors.code" />
                            </div>
                            <div>
                                <label :class="labelClass">Порядок</label>
                                <input v-model="tariffForm.sort_order" :class="inputClass" type="number" min="0" />
                            </div>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label :class="labelClass">Регіон</label>
                                <input v-model="tariffForm.region" :class="inputClass" type="text" placeholder="Вся Україна" />
                            </div>
                            <div>
                                <label :class="labelClass">Місто</label>
                                <input v-model="tariffForm.city" :class="inputClass" type="text" placeholder="Опційно" />
                            </div>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label :class="labelClass">Ціна</label>
                                <input v-model="tariffForm.price" :class="inputClass" type="number" min="0" step="0.01" />
                            </div>
                            <div>
                                <label :class="labelClass">Безкоштовно від</label>
                                <input v-model="tariffForm.free_from" :class="inputClass" type="number" min="0" step="0.01" placeholder="Не задано" />
                            </div>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label :class="labelClass">Мін. сума</label>
                                <input v-model="tariffForm.min_order" :class="inputClass" type="number" min="0" step="0.01" />
                            </div>
                            <div>
                                <label :class="labelClass">Макс. сума</label>
                                <input v-model="tariffForm.max_order" :class="inputClass" type="number" min="0" step="0.01" placeholder="Без обмеження" />
                            </div>
                        </div>
                        <label class="inline-flex items-center gap-2 text-sm font-bold text-[#343241]">
                            <input v-model="tariffForm.is_active" type="checkbox" class="rounded border-slate-300 text-[#7561f7] focus:ring-[#7561f7]" />
                            Активний
                        </label>
                        <button type="submit" class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-[#7561f7] text-sm font-bold text-white hover:bg-[#6552e8]" :disabled="tariffForm.processing">
                            <Save class="h-4 w-4" />
                            {{ editingTariffId ? 'Зберегти зміни' : 'Додати тариф' }}
                        </button>
                    </div>
                </form>
            </section>

            <section v-if="section === 'transactions'" class="rounded-lg bg-white shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                <div class="border-b border-slate-100 px-4 py-4">
                    <h2 class="text-lg font-bold text-[#343241]">Платіжні транзакції</h2>
                    <p class="mt-0.5 text-sm text-slate-500">Останні 100 callback/оплат. Тут буде видно LiqPay ID, суму, статус і привʼязане замовлення.</p>
                </div>

                <div class="divide-y divide-slate-100">
                    <div v-for="transaction in transactions" :key="transaction.id" class="grid gap-4 px-4 py-4 xl:grid-cols-[minmax(0,1fr)_180px_170px_160px] xl:items-center">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <Banknote class="h-4 w-4 text-[#7561f7]" />
                                <div class="font-bold text-[#343241]">{{ transaction.provider_label }}</div>
                                <span class="rounded-full px-2 py-0.5 text-xs font-bold" :class="transaction.status_class">
                                    {{ transaction.status_label }}
                                </span>
                                <span v-if="transaction.is_test" class="rounded-full bg-purple-50 px-2 py-0.5 text-xs font-bold text-purple-700">test</span>
                            </div>
                            <div class="mt-1 flex flex-wrap gap-2 text-xs font-semibold text-slate-500">
                                <span>Замовлення: {{ transaction.order_number || '—' }}</span>
                                <span>·</span>
                                <span>{{ transaction.created_at }}</span>
                            </div>
                            <div class="mt-2 break-all text-xs font-semibold text-slate-500">
                                LiqPay ID: {{ transaction.provider_transaction_id || '—' }}
                            </div>
                            <p v-if="transaction.failure_reason" class="mt-2 text-sm font-semibold text-red-600">{{ transaction.failure_reason }}</p>
                        </div>
                        <div class="text-sm font-semibold text-slate-600">
                            <div class="text-xs font-bold uppercase text-slate-500">Сума</div>
                            <div class="mt-1 font-bold text-[#343241]">{{ transaction.amount }}</div>
                        </div>
                        <div class="text-sm font-semibold text-slate-600">
                            <div class="text-xs font-bold uppercase text-slate-500">Метод</div>
                            <div class="mt-1 text-[#343241]">{{ transaction.payment_method || '—' }}</div>
                        </div>
                        <div class="text-sm font-semibold text-slate-600">
                            <div class="text-xs font-bold uppercase text-slate-500">Оброблено</div>
                            <div class="mt-1 text-[#343241]">{{ transaction.processed_at || '—' }}</div>
                        </div>
                    </div>

                    <div v-if="transactions.length === 0" class="px-4 py-10 text-center text-sm font-semibold text-slate-500">
                        Транзакцій ще немає.
                    </div>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
