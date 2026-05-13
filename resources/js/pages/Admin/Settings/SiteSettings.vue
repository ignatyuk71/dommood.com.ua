<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    CheckCircle2,
    EyeOff,
    Loader2,
    Save,
    Settings2,
    ShieldCheck,
} from 'lucide-vue-next';
import { computed, watch } from 'vue';

const props = defineProps({
    section: {
        type: String,
        required: true,
    },
    tabs: {
        type: Array,
        required: true,
    },
    meta: {
        type: Object,
        required: true,
    },
    settings: {
        type: Object,
        required: true,
    },
    schema: {
        type: Object,
        required: true,
    },
});

const fields = computed(() => props.schema.groups.flatMap((group) => group.fields));
const secretFields = computed(() => fields.value.filter((field) => field.type === 'secret'));

const buildFormState = () => {
    const state = { ...props.settings };

    secretFields.value.forEach((field) => {
        state[field.name] = '';
        state[`${field.name}_clear`] = false;
    });

    return state;
};

const form = useForm(buildFormState());

const inputClass = 'mt-1 h-10 w-full rounded-lg border-slate-200 text-sm font-semibold text-[#343241] shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]';
const labelClass = 'text-xs font-extrabold uppercase tracking-wide text-slate-500';

const submit = () => {
    form.put(route('admin.settings.site.update', props.section), {
        preserveScroll: true,
        onSuccess: () => {
            secretFields.value.forEach((field) => {
                form[field.name] = '';
                form[`${field.name}_clear`] = false;
            });
        },
    });
};

watch(() => [props.section, props.settings, props.schema], () => {
    form.defaults(buildFormState());
    form.reset();
    form.clearErrors();
}, { deep: true });
</script>

<template>
    <Head :title="meta.title" />

    <AuthenticatedLayout>
        <div class="space-y-5">
            <section class="rounded-lg bg-white px-5 py-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)] lg:px-6">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                    <div class="flex items-start gap-4">
                        <span
                            class="inline-flex h-12 w-12 items-center justify-center rounded-2xl text-white shadow-lg"
                            :style="{ backgroundColor: meta.accent }"
                        >
                            <Settings2 class="h-6 w-6" />
                        </span>
                        <div>
                            <p class="text-sm font-extrabold text-slate-500">{{ meta.eyebrow }}</p>
                            <h1 class="mt-1 text-3xl font-black tracking-tight text-[#343241]">{{ meta.title }}</h1>
                            <p class="mt-2 max-w-3xl text-sm font-semibold text-slate-500">{{ meta.description }}</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2 rounded-xl bg-slate-100 p-1">
                        <Link
                            v-for="tab in tabs"
                            :key="tab.key"
                            :href="tab.route"
                            class="inline-flex h-10 items-center rounded-lg px-3 text-sm font-extrabold transition"
                            :class="tab.active ? 'bg-[#7561f7] text-white shadow-lg shadow-indigo-500/20' : 'text-slate-600 hover:bg-white hover:text-[#7561f7]'"
                        >
                            {{ tab.label }}
                        </Link>
                    </div>
                </div>
            </section>

            <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
                <form class="rounded-lg bg-white shadow-[0_16px_45px_rgba(61,58,101,0.08)]" @submit.prevent="submit">
                    <div class="border-b border-slate-100 px-5 py-4">
                        <h2 class="text-lg font-black text-[#343241]">Параметри</h2>
                        <p class="mt-1 text-sm font-semibold text-slate-500">
                            Значення зберігаються у базі й кешуються для швидкого доступу в checkout, storefront і системних сервісах.
                        </p>
                    </div>

                    <div class="space-y-5 p-5">
                        <section
                            v-for="group in schema.groups"
                            :key="group.title"
                            class="rounded-xl border border-slate-100 bg-slate-50/50 p-4"
                        >
                            <h3 class="text-base font-black text-[#343241]">{{ group.title }}</h3>
                            <p v-if="group.description" class="mt-1 text-sm font-semibold leading-5 text-slate-500">
                                {{ group.description }}
                            </p>

                            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                                <div
                                    v-for="field in group.fields"
                                    :key="field.name"
                                    :class="field.span === 2 ? 'lg:col-span-2' : ''"
                                >
                                    <label
                                        v-if="field.type === 'toggle'"
                                        class="flex min-h-10 items-center justify-between gap-4 rounded-lg border border-slate-200 bg-white px-3 py-2"
                                    >
                                        <span class="text-sm font-extrabold text-[#343241]">{{ field.label }}</span>
                                        <input
                                            v-model="form[field.name]"
                                            type="checkbox"
                                            class="rounded border-slate-300 text-[#7561f7] focus:ring-[#7561f7]"
                                        />
                                    </label>

                                    <div v-else>
                                        <label :class="labelClass" :for="field.name">{{ field.label }}</label>

                                        <select
                                            v-if="field.type === 'select'"
                                            :id="field.name"
                                            v-model="form[field.name]"
                                            :class="inputClass"
                                        >
                                            <option
                                                v-for="option in field.options"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>

                                        <textarea
                                            v-else-if="field.type === 'textarea'"
                                            :id="field.name"
                                            v-model="form[field.name]"
                                            rows="4"
                                            class="mt-1 w-full rounded-lg border-slate-200 text-sm font-semibold text-[#343241] shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                            :placeholder="field.placeholder"
                                        ></textarea>

                                        <input
                                            v-else
                                            :id="field.name"
                                            v-model="form[field.name]"
                                            :type="field.type === 'secret' ? 'password' : field.type"
                                            :class="inputClass"
                                            :placeholder="field.secret?.exists ? field.secret.masked : field.placeholder"
                                        />

                                        <div
                                            v-if="field.type === 'secret' && field.secret?.exists"
                                            class="mt-2 flex flex-wrap items-center justify-between gap-2 rounded-lg bg-white px-3 py-2"
                                        >
                                            <span class="inline-flex items-center gap-2 text-xs font-extrabold text-emerald-600">
                                                <EyeOff class="h-4 w-4" />
                                                Збережено {{ field.secret.masked }}
                                            </span>
                                            <label class="inline-flex items-center gap-2 text-xs font-bold text-red-500">
                                                <input
                                                    v-model="form[`${field.name}_clear`]"
                                                    type="checkbox"
                                                    class="rounded border-slate-300 text-red-500 focus:ring-red-500"
                                                />
                                                Очистити
                                            </label>
                                        </div>

                                        <p v-if="field.hint" class="mt-2 text-xs font-semibold leading-5 text-slate-500">
                                            {{ field.hint }}
                                        </p>

                                        <InputError class="mt-1" :message="form.errors[field.name]" />
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>

                    <div class="border-t border-slate-100 px-5 py-4">
                        <button
                            type="submit"
                            class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-[#343241] text-sm font-extrabold text-white transition hover:bg-[#24212f] disabled:opacity-60 sm:w-auto sm:px-6"
                            :disabled="form.processing"
                        >
                            <Loader2 v-if="form.processing" class="h-5 w-5 animate-spin" />
                            <Save v-else class="h-5 w-5" />
                            Зберегти
                        </button>
                    </div>
                </form>

                <aside class="space-y-5">
                    <section class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                        <div class="flex items-center gap-3">
                            <CheckCircle2 class="h-5 w-5 text-emerald-500" />
                            <h2 class="text-lg font-black text-[#343241]">Поточний стан</h2>
                        </div>

                        <div class="mt-4 space-y-3">
                            <article
                                v-for="item in schema.summary"
                                :key="item.label"
                                class="rounded-xl bg-slate-50 px-4 py-3"
                            >
                                <p class="text-xs font-extrabold uppercase tracking-wide text-slate-500">{{ item.label }}</p>
                                <p class="mt-1 break-words text-base font-black text-[#343241]">{{ item.value }}</p>
                            </article>
                        </div>
                    </section>

                    <section class="rounded-lg bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                        <div class="flex items-center gap-3">
                            <ShieldCheck class="h-5 w-5 text-[#7561f7]" />
                            <h2 class="text-lg font-black text-[#343241]">Архітектура</h2>
                        </div>
                        <p class="mt-3 text-sm font-semibold leading-6 text-slate-500">
                            Налаштування лежать у БД, але читаються через кешований сервіс. Після збереження кеш секції очищається автоматично.
                        </p>
                    </section>
                </aside>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
