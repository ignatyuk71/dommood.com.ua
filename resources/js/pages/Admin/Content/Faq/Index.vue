<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Check, Pencil, Plus, Trash2, X } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps({
    items: { type: Array, required: true },
});

const editingId = ref(null);
const editForm = useForm({ question: '', answer: '', sort_order: 0, is_active: true });

const newForm = useForm({ question: '', answer: '', sort_order: 0, is_active: true });
const showNewForm = ref(false);

function startEdit(item) {
    editingId.value = item.id;
    editForm.question = item.question;
    editForm.answer = item.answer;
    editForm.sort_order = item.sort_order;
    editForm.is_active = item.is_active;
}

function cancelEdit() {
    editingId.value = null;
    editForm.reset();
}

function saveEdit(item) {
    editForm.put(route('admin.content.faq.update', item.id), {
        preserveScroll: true,
        onSuccess: () => { editingId.value = null; },
    });
}

function addNew() {
    newForm.post(route('admin.content.faq.store'), {
        preserveScroll: true,
        onSuccess: () => { showNewForm.value = false; newForm.reset(); newForm.is_active = true; },
    });
}

function destroy(item) {
    if (!confirm(`Видалити питання "${item.question}"?`)) return;
    router.delete(route('admin.content.faq.destroy', item.id), { preserveScroll: true });
}
</script>

<template>
    <Head title="FAQ головної сторінки" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-semibold text-slate-500">Контент</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-[#343241]">FAQ головної сторінки</h1>
                </div>
                <button
                    @click="showNewForm = true"
                    class="inline-flex h-10 items-center gap-2 rounded-lg bg-[#7561f7] px-4 text-sm font-bold text-white shadow-[0_10px_24px_rgba(117,97,247,0.22)] transition hover:bg-[#6552e8]"
                >
                    <Plus class="h-4 w-4" />
                    Додати питання
                </button>
            </div>
        </template>

        <div class="space-y-3">

            <!-- Форма нового питання -->
            <div v-if="showNewForm" class="rounded-xl bg-white p-5 shadow-[0_16px_45px_rgba(61,58,101,0.08)] border-2 border-[#7561f7]/20">
                <p class="mb-4 text-sm font-semibold text-slate-500">Нове питання</p>
                <div class="space-y-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Питання</label>
                        <input v-model="newForm.question" type="text" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-[#7561f7] focus:outline-none" placeholder="Як оформити замовлення?" />
                        <p v-if="newForm.errors.question" class="mt-1 text-xs text-red-500">{{ newForm.errors.question }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Відповідь</label>
                        <textarea v-model="newForm.answer" rows="4" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-[#7561f7] focus:outline-none resize-y" placeholder="Детальна відповідь..." />
                        <p v-if="newForm.errors.answer" class="mt-1 text-xs text-red-500">{{ newForm.errors.answer }}</p>
                    </div>
                    <div class="flex items-center gap-4">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">Порядок</label>
                            <input v-model.number="newForm.sort_order" type="number" min="0" class="w-20 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-[#7561f7] focus:outline-none" />
                        </div>
                        <label class="flex cursor-pointer items-center gap-2 pt-5">
                            <input v-model="newForm.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-[#7561f7]" />
                            <span class="text-sm text-slate-700">Активне</span>
                        </label>
                    </div>
                </div>
                <div class="mt-4 flex gap-2">
                    <button @click="addNew" :disabled="newForm.processing" class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-[#7561f7] px-4 text-sm font-semibold text-white transition hover:bg-[#6552e8] disabled:opacity-50">
                        <Check class="h-4 w-4" /> Зберегти
                    </button>
                    <button @click="showNewForm = false; newForm.reset();" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-slate-200 px-4 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">
                        <X class="h-4 w-4" /> Скасувати
                    </button>
                </div>
            </div>

            <!-- Список питань -->
            <div class="rounded-xl bg-white shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                <div v-if="items.length === 0" class="py-16 text-center text-sm text-slate-400">
                    Питань ще немає. Додайте перше.
                </div>

                <div v-for="(item, index) in items" :key="item.id" class="border-b border-slate-100 last:border-0">

                    <!-- Режим перегляду -->
                    <div v-if="editingId !== item.id" class="flex items-start gap-4 p-5">
                        <span class="mt-0.5 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-500">
                            {{ index + 1 }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <p class="font-semibold text-slate-800">{{ item.question }}</p>
                                <span v-if="!item.is_active" class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">Приховано</span>
                            </div>
                            <p class="mt-1 text-sm text-slate-500 whitespace-pre-wrap">{{ item.answer }}</p>
                        </div>
                        <div class="flex flex-shrink-0 gap-1">
                            <button @click="startEdit(item)" class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700">
                                <Pencil class="h-4 w-4" />
                            </button>
                            <button @click="destroy(item)" class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-red-50 hover:text-red-500">
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <!-- Режим редагування -->
                    <div v-else class="space-y-3 p-5 bg-slate-50">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">Питання</label>
                            <input v-model="editForm.question" type="text" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:border-[#7561f7] focus:outline-none" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">Відповідь</label>
                            <textarea v-model="editForm.answer" rows="4" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:border-[#7561f7] focus:outline-none resize-y" />
                        </div>
                        <div class="flex items-center gap-4">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">Порядок</label>
                                <input v-model.number="editForm.sort_order" type="number" min="0" class="w-20 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:border-[#7561f7] focus:outline-none" />
                            </div>
                            <label class="flex cursor-pointer items-center gap-2 pt-5">
                                <input v-model="editForm.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-[#7561f7]" />
                                <span class="text-sm text-slate-700">Активне</span>
                            </label>
                        </div>
                        <div class="flex gap-2">
                            <button @click="saveEdit(item)" :disabled="editForm.processing" class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-[#7561f7] px-4 text-sm font-semibold text-white transition hover:bg-[#6552e8] disabled:opacity-50">
                                <Check class="h-4 w-4" /> Зберегти
                            </button>
                            <button @click="cancelEdit" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-slate-200 px-4 text-sm font-semibold text-slate-600 transition hover:bg-white">
                                <X class="h-4 w-4" /> Скасувати
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
