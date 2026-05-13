<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Check, Clock3, LockKeyhole, Mail, Plus, Power, ShieldCheck, UserCog, UserPlus, UsersRound } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    roles: {
        type: Array,
        required: true,
    },
    groups: {
        type: Array,
        required: true,
    },
    allPermissions: {
        type: Array,
        required: true,
    },
    staffTotal: {
        type: Number,
        default: 0,
    },
});

const selectedRoleId = ref(props.roles.find((role) => role.name === 'manager')?.id ?? props.roles[0]?.id);
const selectedRole = computed(() => props.roles.find((role) => role.id === selectedRoleId.value) ?? props.roles[0]);
const staffRoles = computed(() => props.roles.map((role) => ({
    value: role.name,
    label: role.label,
    locked: role.locked,
})));

const form = useForm({
    permissions: [],
});
const staffForm = useForm({
    name: '',
    email: '',
    phone: '',
    role: 'manager',
    password: '',
    is_active: true,
});

const syncForm = () => {
    form.permissions = [...(selectedRole.value?.permissions ?? [])];
};

watch(selectedRole, syncForm, { immediate: true });

const checked = (permission) => form.permissions.includes(permission);
const toggle = (permission) => {
    if (selectedRole.value?.locked) return;

    form.permissions = checked(permission)
        ? form.permissions.filter((item) => item !== permission)
        : [...form.permissions, permission];
};

const toggleGroup = (group) => {
    if (selectedRole.value?.locked) return;

    const groupPermissions = Object.keys(group.permissions);
    const allChecked = groupPermissions.every((permission) => checked(permission));

    form.permissions = allChecked
        ? form.permissions.filter((permission) => !groupPermissions.includes(permission))
        : [...new Set([...form.permissions, ...groupPermissions])];
};

const submit = () => {
    if (!selectedRole.value || selectedRole.value.locked) return;

    form.put(route('admin.roles.update', selectedRole.value.id), {
        preserveScroll: true,
    });
};
const submitStaff = () => {
    staffForm.post(route('admin.roles.staff.store'), {
        preserveScroll: true,
        onSuccess: () => {
            staffForm.reset();
            staffForm.role = 'manager';
            staffForm.is_active = true;
        },
    });
};
const toggleStaffStatus = (staffUser) => {
    useForm({
        role: staffUser.role,
        is_active: !staffUser.is_active,
    }).patch(route('admin.roles.staff.update', staffUser.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Ролі та доступи" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-bold text-slate-500">Безпека</p>
                    <h1 class="text-3xl font-black tracking-tight text-[#343241]">Ролі та доступи</h1>
                    <p class="mt-1 text-sm font-medium text-slate-500">Тут тільки персонал адмінки. Покупці живуть окремо у CRM-клієнтах і не мають доступу до адмінки.</p>
                </div>
                <button
                    class="inline-flex h-11 items-center gap-2 rounded-lg bg-[#343241] px-5 text-sm font-black text-white disabled:opacity-50"
                    type="button"
                    :disabled="selectedRole?.locked || form.processing"
                    @click="submit"
                >
                    <Check class="h-5 w-5" />
                    Зберегти доступи
                </button>
            </div>
        </template>

        <div class="grid gap-5 xl:grid-cols-[340px_minmax(0,1fr)]">
            <section class="rounded-lg bg-white p-4 shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-lg font-black text-[#343241]">Персонал</h2>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600">
                        {{ staffTotal }} людей
                    </span>
                </div>
                <div class="space-y-2">
                    <button
                        v-for="role in roles"
                        :key="role.id"
                        type="button"
                        class="flex w-full items-center justify-between rounded-lg border px-4 py-3 text-left transition"
                        :class="selectedRoleId === role.id ? 'border-[#7561f7] bg-[#f3f0ff]' : 'border-slate-200 hover:bg-slate-50'"
                        @click="selectedRoleId = role.id"
                    >
                        <span class="flex items-center gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-white text-[#7561f7]">
                                <ShieldCheck v-if="role.name === 'admin'" class="h-5 w-5" />
                                <UserCog v-else class="h-5 w-5" />
                            </span>
                            <span>
                                <span class="block font-black text-[#343241]">{{ role.label }}</span>
                                <span class="text-xs font-semibold text-slate-500">{{ role.users_count }} користувачів</span>
                            </span>
                        </span>
                        <LockKeyhole v-if="role.locked" class="h-4 w-4 text-slate-400" />
                    </button>
                </div>

                <form class="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-4" @submit.prevent="submitStaff">
                    <div class="mb-4 flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-white text-[#7561f7]">
                            <UserPlus class="h-5 w-5" />
                        </span>
                        <div>
                            <h3 class="text-sm font-black uppercase tracking-wide text-[#343241]">Новий працівник</h3>
                            <p class="text-xs font-semibold text-slate-500">Доступи беруться з вибраної ролі.</p>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <label class="text-xs font-black uppercase tracking-wide text-slate-500" for="staff-name">Імʼя</label>
                            <input
                                id="staff-name"
                                v-model="staffForm.name"
                                type="text"
                                class="mt-1 h-10 w-full rounded-lg border-slate-200 text-sm font-bold text-[#343241] shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                placeholder="Напр., Олена"
                            >
                            <InputError class="mt-1" :message="staffForm.errors.name" />
                        </div>

                        <div>
                            <label class="text-xs font-black uppercase tracking-wide text-slate-500" for="staff-email">Email</label>
                            <input
                                id="staff-email"
                                v-model="staffForm.email"
                                type="email"
                                class="mt-1 h-10 w-full rounded-lg border-slate-200 text-sm font-bold text-[#343241] shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                placeholder="manager@example.com"
                            >
                            <InputError class="mt-1" :message="staffForm.errors.email" />
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                            <div>
                                <label class="text-xs font-black uppercase tracking-wide text-slate-500" for="staff-phone">Телефон</label>
                                <input
                                    id="staff-phone"
                                    v-model="staffForm.phone"
                                    type="text"
                                    class="mt-1 h-10 w-full rounded-lg border-slate-200 text-sm font-bold text-[#343241] shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                    placeholder="+380..."
                                >
                                <InputError class="mt-1" :message="staffForm.errors.phone" />
                            </div>

                            <div>
                                <label class="text-xs font-black uppercase tracking-wide text-slate-500" for="staff-role">Роль</label>
                                <select
                                    id="staff-role"
                                    v-model="staffForm.role"
                                    class="mt-1 h-10 w-full rounded-lg border-slate-200 text-sm font-bold text-[#343241] shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                >
                                    <option
                                        v-for="role in staffRoles"
                                        :key="role.value"
                                        :value="role.value"
                                    >
                                        {{ role.label }}
                                    </option>
                                </select>
                                <InputError class="mt-1" :message="staffForm.errors.role" />
                            </div>
                        </div>

                        <div>
                            <label class="text-xs font-black uppercase tracking-wide text-slate-500" for="staff-password">Пароль</label>
                            <input
                                id="staff-password"
                                v-model="staffForm.password"
                                type="password"
                                class="mt-1 h-10 w-full rounded-lg border-slate-200 text-sm font-bold text-[#343241] shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                placeholder="Мінімум 8 символів"
                            >
                            <InputError class="mt-1" :message="staffForm.errors.password" />
                        </div>

                        <label class="flex items-center justify-between rounded-lg bg-white px-3 py-2 text-sm font-black text-[#343241]">
                            Активний доступ
                            <input
                                v-model="staffForm.is_active"
                                type="checkbox"
                                class="rounded border-slate-300 text-[#7561f7] focus:ring-[#7561f7]"
                            >
                        </label>

                        <InputError class="mt-1" :message="staffForm.errors.staff" />

                        <button
                            type="submit"
                            class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-[#7561f7] text-sm font-black text-white shadow-lg shadow-indigo-500/20 disabled:opacity-60"
                            :disabled="staffForm.processing"
                        >
                            <Plus class="h-4 w-4" />
                            Створити працівника
                        </button>
                    </div>
                </form>

                <div class="mt-5 border-t border-slate-100 pt-4">
                    <h3 class="mb-3 text-sm font-black uppercase tracking-wide text-slate-500">
                        {{ selectedRole?.label }}
                    </h3>
                    <div class="space-y-2">
                        <div
                            v-for="staffUser in selectedRole?.users ?? []"
                            :key="staffUser.id"
                            class="rounded-lg border border-slate-200 bg-white px-3 py-3"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-black text-[#343241]">{{ staffUser.name }}</div>
                                    <div class="mt-1 flex min-w-0 items-center gap-1 text-xs font-semibold text-slate-500">
                                        <Mail class="h-3.5 w-3.5 shrink-0" />
                                        <span class="truncate">{{ staffUser.email }}</span>
                                    </div>
                                    <div class="mt-1 flex items-center gap-1 text-xs font-semibold text-slate-500">
                                        <Clock3 class="h-3.5 w-3.5" />
                                        {{ staffUser.last_login_at || 'Ще не входив' }}
                                    </div>
                                </div>
                                <div class="flex shrink-0 flex-col items-end gap-2">
                                    <span
                                        class="rounded-full px-2.5 py-1 text-[11px] font-black"
                                        :class="staffUser.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'"
                                    >
                                        {{ staffUser.is_active ? 'Активний' : 'Вимкнено' }}
                                    </span>
                                    <button
                                        type="button"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:border-[#7561f7] hover:text-[#7561f7]"
                                        :title="staffUser.is_active ? 'Вимкнути доступ' : 'Увімкнути доступ'"
                                        @click="toggleStaffStatus(staffUser)"
                                    >
                                        <Power class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="(selectedRole?.users ?? []).length === 0"
                            class="rounded-lg border border-dashed border-slate-200 px-4 py-6 text-center"
                        >
                            <UsersRound class="mx-auto h-8 w-8 text-slate-300" />
                            <div class="mt-2 text-sm font-black text-[#343241]">Користувачів немає</div>
                            <p class="text-xs font-semibold text-slate-500">Коли додамо менеджера, він зʼявиться тут.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-lg bg-white shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                <div class="border-b border-slate-100 px-5 py-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-xl font-black text-[#343241]">{{ selectedRole?.label }}</h2>
                            <p class="text-sm font-medium text-slate-500">{{ selectedRole?.description }}</p>
                        </div>
                        <span
                            class="rounded-full px-3 py-1 text-xs font-black"
                            :class="selectedRole?.locked ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700'"
                        >
                            {{ selectedRole?.locked ? 'Системна роль' : 'Можна редагувати' }}
                        </span>
                    </div>
                </div>

                <div class="grid gap-4 p-5 lg:grid-cols-2">
                    <article
                        v-for="group in groups"
                        :key="group.key"
                        class="rounded-lg border border-slate-200 p-4"
                    >
                        <button
                            type="button"
                            class="mb-3 flex w-full items-center justify-between text-left"
                            @click="toggleGroup(group)"
                        >
                            <span class="text-base font-black text-[#343241]">{{ group.label }}</span>
                            <span class="text-xs font-bold text-slate-500">
                                {{ Object.keys(group.permissions).filter((permission) => checked(permission)).length }}/{{ Object.keys(group.permissions).length }}
                            </span>
                        </button>

                        <div class="space-y-2">
                            <label
                                v-for="(label, permission) in group.permissions"
                                :key="permission"
                                class="flex cursor-pointer items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2 text-sm font-bold text-[#343241]"
                            >
                                <span>{{ label }}</span>
                                <input
                                    type="checkbox"
                                    class="rounded border-slate-300 text-[#7561f7] focus:ring-[#7561f7]"
                                    :checked="checked(permission)"
                                    :disabled="selectedRole?.locked"
                                    @change="toggle(permission)"
                                >
                            </label>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
