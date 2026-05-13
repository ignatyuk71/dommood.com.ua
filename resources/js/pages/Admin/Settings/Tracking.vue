<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    Activity,
    BarChart3,
    CheckCircle2,
    KeyRound,
    Loader2,
    PlugZap,
    Save,
    ServerCog,
    Settings2,
    ShieldCheck,
} from 'lucide-vue-next';
import { computed, watch } from 'vue';

const props = defineProps({
    activeChannel: {
        type: String,
        required: true,
    },
    channels: {
        type: Array,
        required: true,
    },
    integration: {
        type: Object,
        required: true,
    },
    formSchema: {
        type: Object,
        required: true,
    },
});

const active = computed(() => props.channels.find((channel) => channel.key === props.activeChannel) ?? props.channels[0]);
const secretTypes = computed(() => props.formSchema.fields
    .filter((field) => ['secret', 'textarea_secret'].includes(field.type))
    .map((field) => field.name));

const buildFormState = () => {
    const settings = props.integration.settings ?? {};
    const state = {
        status: props.integration.status ?? 'disabled',
        mode: props.integration.mode ?? 'prod',
        send_client: Boolean(settings.send_client),
        send_server: Boolean(settings.send_server),
    };

    props.formSchema.fields.forEach((field) => {
        if (['status', 'mode', 'send_client', 'send_server'].includes(field.name)) {
            return;
        }

        state[field.name] = ['secret', 'textarea_secret'].includes(field.type)
            ? ''
            : (settings[field.name] ?? '');

        if (['secret', 'textarea_secret'].includes(field.type)) {
            state[`${field.name}_clear`] = false;
        }
    });

    return state;
};

const form = useForm(buildFormState());
const credential = (name) => props.integration.credentials?.[name] ?? { exists: false, masked: '' };

const statusClass = computed(() => {
    if (props.integration.server_ready && props.integration.browser_ready) {
        return 'bg-emerald-50 text-emerald-700';
    }

    if (props.integration.configured) {
        return 'bg-blue-50 text-blue-700';
    }

    return 'bg-amber-50 text-amber-700';
});

const readinessCards = computed(() => [
    {
        label: 'Статус',
        value: props.integration.status_label,
        ready: props.integration.configured,
        icon: PlugZap,
    },
    {
        label: 'Browser tracking',
        value: props.integration.browser_ready ? 'Готово' : 'Не готово',
        ready: props.integration.browser_ready,
        icon: Activity,
    },
    {
        label: 'Server API',
        value: props.integration.server_ready ? 'Готово' : 'Не готово',
        ready: props.integration.server_ready,
        icon: ServerCog,
    },
]);

const submit = () => {
    form.put(route('admin.settings.tracking.update', props.activeChannel), {
        preserveScroll: true,
        onSuccess: () => {
            secretTypes.value.forEach((type) => {
                form[type] = '';
                form[`${type}_clear`] = false;
            });
        },
    });
};

watch(() => [props.activeChannel, props.integration], () => {
    form.defaults(buildFormState());
    form.reset();
    form.clearErrors();
}, { deep: true });
</script>

<template>
    <Head :title="`Tracking: ${active.label}`" />

    <AuthenticatedLayout>
        <section class="mb-5 rounded-lg bg-white px-5 py-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)] lg:px-7">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-start gap-4">
                    <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-[#7561f7] text-white shadow-lg shadow-indigo-500/20">
                        <Settings2 class="h-7 w-7" />
                    </span>
                    <div>
                        <p class="text-sm font-extrabold text-slate-500">Налаштування</p>
                        <h1 class="mt-1 text-3xl font-black tracking-tight text-[#343241]">Tracking</h1>
                        <p class="mt-2 max-w-3xl text-sm font-semibold text-slate-500">
                            Тут зберігаємо токени, Pixel ID, GA4/GTM і server-side ключі. Аналітика тільки показує дані.
                        </p>
                    </div>
                </div>

                <div class="inline-flex rounded-xl bg-slate-100 p-1">
                    <Link
                        v-for="channel in channels"
                        :key="channel.key"
                        :href="channel.route"
                        class="inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-extrabold transition"
                        :class="channel.key === activeChannel ? 'bg-[#7561f7] text-white shadow-lg shadow-indigo-500/20' : 'text-slate-600 hover:bg-white'"
                    >
                        <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: channel.key === activeChannel ? '#fff' : channel.color }"></span>
                        {{ channel.label }}
                    </Link>
                </div>
            </div>
        </section>

        <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_420px]">
            <form class="rounded-lg bg-white shadow-[0_16px_45px_rgba(61,58,101,0.08)]" @submit.prevent="submit">
                <div class="flex flex-col gap-4 border-b border-slate-100 px-5 py-5 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex items-start gap-4">
                        <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl text-white shadow-lg" :style="{ backgroundColor: active.color }">
                            <BarChart3 class="h-6 w-6" />
                        </span>
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-2xl font-black text-[#343241]">{{ active.label }}</h2>
                                <span class="rounded-lg bg-slate-100 px-2 py-1 text-xs font-extrabold text-slate-600">{{ active.badge }}</span>
                            </div>
                            <p class="mt-1 max-w-3xl text-sm font-semibold text-slate-500">{{ active.description }}</p>
                        </div>
                    </div>

                    <span class="inline-flex items-center gap-2 rounded-full px-3 py-2 text-sm font-extrabold" :class="statusClass">
                        <CheckCircle2 class="h-4 w-4" />
                        {{ integration.status_label }}
                    </span>
                </div>

                <div class="grid gap-4 p-5 lg:grid-cols-2">
                    <template v-for="field in formSchema.fields" :key="field.name">
                        <label
                            v-if="field.type === 'toggle'"
                            class="flex items-center justify-between gap-4 rounded-xl border border-slate-200 px-4 py-3"
                        >
                            <span class="text-sm font-extrabold text-[#343241]">{{ field.label }}</span>
                            <input
                                v-if="field.name === 'status'"
                                type="checkbox"
                                class="rounded border-slate-300 text-[#7561f7] focus:ring-[#7561f7]"
                                :checked="form.status === 'active'"
                                @change="form.status = $event.target.checked ? 'active' : 'disabled'"
                            />
                            <input
                                v-else
                                v-model="form[field.name]"
                                type="checkbox"
                                class="rounded border-slate-300 text-[#7561f7] focus:ring-[#7561f7]"
                            />
                        </label>

                        <label
                            v-else-if="field.type === 'mode'"
                            class="flex items-center justify-between gap-4 rounded-xl border border-slate-200 px-4 py-3"
                        >
                            <span class="text-sm font-extrabold text-[#343241]">{{ field.label }}</span>
                            <input
                                type="checkbox"
                                class="rounded border-slate-300 text-[#7561f7] focus:ring-[#7561f7]"
                                :checked="form.mode === 'test'"
                                @change="form.mode = $event.target.checked ? 'test' : 'prod'"
                            />
                        </label>

                        <div v-else :class="field.type === 'textarea_secret' ? 'lg:col-span-2' : ''">
                            <label class="text-xs font-extrabold uppercase tracking-wide text-slate-500" :for="field.name">
                                {{ field.label }}
                            </label>
                            <textarea
                                v-if="field.type === 'textarea_secret'"
                                :id="field.name"
                                v-model="form[field.name]"
                                rows="5"
                                class="mt-1 w-full rounded-xl border-slate-200 text-sm font-semibold text-[#343241] shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                :placeholder="credential(field.name).exists ? credential(field.name).masked : field.placeholder"
                            ></textarea>
                            <input
                                v-else
                                :id="field.name"
                                v-model="form[field.name]"
                                :type="field.type === 'secret' ? 'password' : 'text'"
                                class="mt-1 h-11 w-full rounded-xl border-slate-200 text-sm font-semibold text-[#343241] shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                :placeholder="field.type === 'secret' && credential(field.name).exists ? credential(field.name).masked : field.placeholder"
                            />
                            <div
                                v-if="['secret', 'textarea_secret'].includes(field.type) && credential(field.name).exists"
                                class="mt-2 flex flex-wrap items-center justify-between gap-2 rounded-xl bg-slate-50 px-3 py-2"
                            >
                                <span class="inline-flex items-center gap-2 text-xs font-extrabold text-emerald-600">
                                    <KeyRound class="h-4 w-4" />
                                    Збережено {{ credential(field.name).masked }}
                                </span>
                                <label class="inline-flex items-center gap-2 text-xs font-bold text-red-500">
                                    <input v-model="form[`${field.name}_clear`]" type="checkbox" class="rounded border-slate-300 text-red-500 focus:ring-red-500" />
                                    Очистити
                                </label>
                            </div>
                            <InputError class="mt-1" :message="form.errors[field.name]" />
                        </div>
                    </template>
                </div>

                <div class="border-t border-slate-100 px-5 py-5">
                    <button
                        type="submit"
                        class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-[#343241] text-sm font-extrabold text-white hover:bg-[#24212f] disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        <Loader2 v-if="form.processing" class="h-5 w-5 animate-spin" />
                        <Save v-else class="h-5 w-5" />
                        Зберегти tracking
                    </button>
                </div>
            </form>

            <aside class="space-y-5">
                <section class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <div class="flex items-center gap-3">
                        <ShieldCheck class="h-5 w-5 text-[#7561f7]" />
                        <h2 class="text-lg font-black text-[#343241]">Стан підключення</h2>
                    </div>
                    <div class="mt-4 space-y-3">
                        <article
                            v-for="card in readinessCards"
                            :key="card.label"
                            class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 px-4 py-3"
                        >
                            <span class="flex items-center gap-3">
                                <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-white text-[#7561f7] shadow-sm">
                                    <component :is="card.icon" class="h-5 w-5" />
                                </span>
                                <span>
                                    <span class="block text-sm font-extrabold text-[#343241]">{{ card.label }}</span>
                                    <span class="block text-xs font-semibold text-slate-500">{{ card.value }}</span>
                                </span>
                            </span>
                            <span class="h-3 w-3 rounded-full" :class="card.ready ? 'bg-emerald-500' : 'bg-amber-400'"></span>
                        </article>
                    </div>
                </section>

                <section class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                    <h2 class="text-lg font-black text-[#343241]">Де дивитися дані</h2>
                    <p class="mt-2 text-sm font-semibold leading-6 text-slate-500">
                        Події, графіки, помилки відправки й funnel лишаються в аналітиці. Тут тільки ключі й режим роботи інтеграції.
                    </p>
                    <Link
                        :href="active.analytics_route"
                        class="mt-4 inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-slate-200 text-sm font-extrabold text-[#343241] hover:border-[#7561f7] hover:text-[#7561f7]"
                    >
                        <BarChart3 class="h-4 w-4" />
                        Відкрити аналітику {{ active.label }}
                    </Link>
                </section>
            </aside>
        </section>
    </AuthenticatedLayout>
</template>
