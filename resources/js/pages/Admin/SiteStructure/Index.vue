<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import NestedMenuDraggable from '@/components/Admin/SiteStructure/NestedMenuDraggable.vue';
import InputError from '@/components/InputError.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Layers3,
    Loader2,
    Plus,
    Save,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    menu: {
        type: Object,
        required: true,
    },
    menus: {
        type: Array,
        required: true,
    },
    tree: {
        type: Array,
        required: true,
    },
    items: {
        type: Array,
        required: true,
    },
    categoryOptions: {
        type: Array,
        required: true,
    },
    pageOptions: {
        type: Array,
        required: true,
    },
});

const maxDepth = 4;
const cloneTree = (nodes) => nodes.map((node) => ({
    ...node,
    children: cloneTree(node.children ?? []),
}));

const tree = ref(cloneTree(props.tree));
const dirtyOrder = ref(false);
const savingOrder = ref(false);
const editingItem = ref(null);
const collapsedIds = ref(new Set());

const form = useForm({
    parent_id: '',
    title: '',
    type: 'custom_url',
    linkable_id: '',
    url: '/',
    target: '_self',
    badge: '',
    is_active: true,
});

watch(() => props.tree, (value) => {
    tree.value = cloneTree(value);
    dirtyOrder.value = false;
}, { deep: true });

const flattenAllRows = (nodes, depth = 0, ancestors = [], rows = []) => {
    nodes.forEach((node, index) => {
        rows.push({
            ...node,
            depth,
            index,
            ancestors,
            hasChildren: Boolean((node.children ?? []).length),
            isCollapsed: collapsedIds.value.has(node.id),
        });

        flattenAllRows(node.children ?? [], depth + 1, [...ancestors, node.id], rows);
    });

    return rows;
};

const allRows = computed(() => flattenAllRows(tree.value));
const isEditing = computed(() => Boolean(editingItem.value));
const parentOptions = computed(() => allRows.value
    .filter((item) => {
        if (!editingItem.value) {
            return true;
        }

        return item.id !== editingItem.value.id && !item.ancestors.includes(editingItem.value.id);
    })
    .map((item) => ({
        id: item.id,
        label: `${'— '.repeat(item.depth)}${item.title}`,
    })));

const activeLinkOptions = computed(() => (form.type === 'page' ? props.pageOptions : props.categoryOptions));

const selectedLinkLabel = computed(() => {
    if (!['category', 'page'].includes(form.type) || !form.linkable_id) {
        return null;
    }

    return activeLinkOptions.value.find((option) => Number(option.id) === Number(form.linkable_id))?.label ?? null;
});

const toast = (type, message) => {
    window.dispatchEvent(new CustomEvent('admin-toast', {
        detail: { type, message },
    }));
};

const toggleCollapsed = (item) => {
    if (!(item.children ?? []).length) {
        return;
    }

    const next = new Set(collapsedIds.value);

    if (next.has(item.id)) {
        next.delete(item.id);
    } else {
        next.add(item.id);
    }

    collapsedIds.value = next;
};

const resetForm = () => {
    editingItem.value = null;
    form.clearErrors();
    form.parent_id = '';
    form.title = '';
    form.type = 'custom_url';
    form.linkable_id = '';
    form.url = '/';
    form.target = '_self';
    form.badge = '';
    form.is_active = true;
};

const editItem = (item) => {
    editingItem.value = item;
    form.clearErrors();
    form.parent_id = item.parent_id ?? '';
    form.title = item.title ?? '';
    form.type = item.type ?? 'custom_url';
    form.linkable_id = item.linkable_id ?? '';
    form.url = item.url ?? item.resolved_url ?? '/';
    form.target = item.target ?? '_self';
    form.badge = item.badge ?? '';
    form.is_active = Boolean(item.is_active);
};

const payload = () => ({
    parent_id: form.parent_id || null,
    title: form.title,
    type: form.type,
    linkable_id: ['category', 'page'].includes(form.type) ? form.linkable_id : null,
    url: form.type === 'custom_url' ? form.url : null,
    target: form.target,
    badge: form.badge,
    is_active: form.is_active,
});

const submitItem = () => {
    if (isEditing.value) {
        form
            .transform(payload)
            .put(route('admin.site-structure.items.update', [props.menu.key, editingItem.value.id]), {
                preserveScroll: true,
                onSuccess: resetForm,
            });

        return;
    }

    form
        .transform(payload)
        .post(route('admin.site-structure.items.store', props.menu.key), {
            preserveScroll: true,
            onSuccess: resetForm,
        });
};

const destroyItem = (item) => {
    if (!window.confirm(`Видалити пункт "${item.title}"?`)) {
        return;
    }

    router.delete(route('admin.site-structure.items.destroy', [props.menu.key, item.id]), {
        preserveScroll: true,
        onSuccess: () => {
            if (editingItem.value?.id === item.id) {
                resetForm();
            }
        },
    });
};

const removeNode = (nodes, id) => {
    const index = nodes.findIndex((node) => node.id === id);

    if (index !== -1) {
        return nodes.splice(index, 1)[0];
    }

    for (const node of nodes) {
        const removed = removeNode(node.children ?? [], id);

        if (removed) {
            return removed;
        }
    }

    return null;
};

const normalizeTree = (nodes) => {
    nodes.forEach((node) => {
        node.children = Array.isArray(node.children) ? node.children : [];
        normalizeTree(node.children);
    });

    return nodes;
};

const markTreeChanged = () => {
    normalizeTree(tree.value);
    tree.value = [...tree.value];
    dirtyOrder.value = true;
};

const moveToRoot = (item) => {
    const source = removeNode(tree.value, item.id);

    if (!source) {
        return;
    }

    tree.value.push(source);
    markTreeChanged();
};

const maxTreeDepth = (nodes, depth = 0) => nodes.reduce((maxDepthValue, node) => Math.max(
    maxDepthValue,
    depth,
    maxTreeDepth(node.children ?? [], depth + 1),
), -1);

const validateTreeDepth = () => {
    if (maxTreeDepth(tree.value) <= maxDepth) {
        return true;
    }

    toast('error', `Меню підтримує максимум ${maxDepth + 1} рівнів вкладеності`);

    return false;
};

const serializeTree = (nodes) => nodes.map((node) => ({
    id: node.id,
    children: serializeTree(node.children ?? []),
}));

const saveOrder = async () => {
    normalizeTree(tree.value);

    if (!validateTreeDepth()) {
        return;
    }

    savingOrder.value = true;

    try {
        await window.axios.post(route('admin.site-structure.reorder', props.menu.key), {
            tree: serializeTree(tree.value),
        });
        dirtyOrder.value = false;
        toast('success', 'Порядок меню збережено');
    } catch (error) {
        const errors = error.response?.data?.errors ?? {};
        const firstError = Object.values(errors)[0]?.[0];
        toast('error', firstError ?? error.response?.data?.message ?? 'Не вдалося зберегти порядок');
    } finally {
        savingOrder.value = false;
    }
};
</script>

<template>
    <Head :title="`Структура сайту: ${menu.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-sm font-semibold text-slate-500">Структура сайту</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-[#343241]">{{ menu.name }}</h1>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Link
                        v-for="item in menus"
                        :key="item.key"
                        :href="item.url"
                        class="inline-flex h-9 items-center rounded-lg border px-3 text-sm font-bold transition"
                        :class="item.key === menu.key
                            ? 'border-[#7561f7] bg-[#7561f7] text-white shadow-[0_10px_24px_rgba(117,97,247,0.22)]'
                            : 'border-slate-200 bg-white text-slate-600 hover:border-[#7561f7] hover:text-[#7561f7]'"
                    >
                        {{ item.name }}
                    </Link>
                </div>
            </div>
        </template>

        <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
            <section class="rounded-lg bg-white shadow-[0_16px_45px_rgba(61,58,101,0.08)]">
                <div class="flex flex-col gap-3 border-b border-slate-100 px-4 py-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-base font-bold text-[#343241]">Дерево навігації</h2>
                        <p class="mt-0.5 text-xs font-medium text-slate-500">{{ menu.description }}</p>
                    </div>

                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center gap-2 rounded-lg bg-[#343241] px-3 text-sm font-bold text-white transition hover:bg-[#292736] disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="!dirtyOrder || savingOrder"
                        @click="saveOrder"
                    >
                        <Loader2 v-if="savingOrder" class="h-4 w-4 animate-spin" />
                        <Save v-else class="h-4 w-4" />
                        Порядок
                    </button>
                </div>

                <div class="p-3">
                    <div v-if="tree.length" class="space-y-2">
                        <NestedMenuDraggable
                            :items="tree"
                            :depth="0"
                            :max-depth="maxDepth"
                            :collapsed-ids="collapsedIds"
                            @change="markTreeChanged"
                            @toggle="toggleCollapsed"
                            @edit="editItem"
                            @delete="destroyItem"
                            @move-root="moveToRoot"
                        />
                    </div>

                    <div v-else class="flex min-h-56 flex-col items-center justify-center rounded-lg border border-dashed border-slate-200 bg-slate-50/70 p-6 text-center">
                        <Layers3 class="h-8 w-8 text-slate-300" />
                        <h3 class="mt-3 text-sm font-bold text-[#343241]">Пунктів ще немає</h3>
                        <p class="mt-1 max-w-sm text-xs font-medium text-slate-500">Створи перший пункт справа, потім перетягуванням задай порядок і вкладеність.</p>
                    </div>
                </div>
            </section>

            <aside class="rounded-lg bg-white p-4 shadow-[0_16px_45px_rgba(61,58,101,0.08)] xl:sticky xl:top-28 xl:self-start">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-bold text-[#343241]">
                            {{ isEditing ? 'Редагувати пункт' : 'Новий пункт' }}
                        </h2>
                        <p v-if="selectedLinkLabel" class="mt-0.5 truncate text-xs font-semibold text-slate-400">
                            {{ selectedLinkLabel }}
                        </p>
                    </div>

                    <button
                        v-if="isEditing"
                        type="button"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition hover:border-[#7561f7] hover:text-[#7561f7]"
                        aria-label="Скасувати редагування"
                        @click="resetForm"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <form class="space-y-3" @submit.prevent="submitItem">
                    <div>
                        <label class="text-xs font-bold uppercase text-slate-500" for="menu_title">Назва</label>
                        <input
                            id="menu_title"
                            v-model="form.title"
                            type="text"
                            class="mt-1 h-9 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                            placeholder="Напр., Каталог"
                        />
                        <InputError class="mt-1" :message="form.errors.title" />
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-xs font-bold uppercase text-slate-500" for="menu_type">Тип</label>
                            <select
                                id="menu_type"
                                v-model="form.type"
                                class="mt-1 h-9 w-full rounded-lg border-slate-200 py-1 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                            >
                                <option value="custom_url">URL</option>
                                <option value="category">Категорія</option>
                                <option value="page">Сторінка</option>
                            </select>
                            <InputError class="mt-1" :message="form.errors.type" />
                        </div>

                        <div>
                            <label class="text-xs font-bold uppercase text-slate-500" for="menu_target">Вікно</label>
                            <select
                                id="menu_target"
                                v-model="form.target"
                                class="mt-1 h-9 w-full rounded-lg border-slate-200 py-1 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                            >
                                <option value="_self">Поточне</option>
                                <option value="_blank">Нове</option>
                            </select>
                            <InputError class="mt-1" :message="form.errors.target" />
                        </div>
                    </div>

                    <div v-if="form.type === 'custom_url'">
                        <label class="text-xs font-bold uppercase text-slate-500" for="menu_url">URL</label>
                        <input
                            id="menu_url"
                            v-model="form.url"
                            type="text"
                            class="mt-1 h-9 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                            placeholder="/catalog або https://..."
                            required
                        />
                        <InputError class="mt-1" :message="form.errors.url" />
                    </div>

                    <div v-else>
                        <label class="text-xs font-bold uppercase text-slate-500" for="menu_linkable">
                            {{ form.type === 'category' ? 'Категорія' : 'Сторінка' }}
                        </label>
                        <select
                            id="menu_linkable"
                            v-model="form.linkable_id"
                            class="mt-1 h-9 w-full rounded-lg border-slate-200 py-1 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                            required
                        >
                            <option value="">Вибрати</option>
                            <option v-for="option in activeLinkOptions" :key="option.id" :value="option.id">
                                {{ option.label }}
                            </option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.linkable_id" />
                    </div>

                    <div>
                        <label class="text-xs font-bold uppercase text-slate-500" for="menu_parent">Батьківський</label>
                        <select
                            id="menu_parent"
                            v-model="form.parent_id"
                            class="mt-1 h-9 w-full rounded-lg border-slate-200 py-1 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                        >
                            <option value="">Корінь меню</option>
                            <option v-for="option in parentOptions" :key="option.id" :value="option.id">
                                {{ option.label }}
                            </option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.parent_id" />
                    </div>

                    <div class="grid grid-cols-[minmax(0,1fr)_112px] gap-2">
                        <div>
                            <label class="text-xs font-bold uppercase text-slate-500" for="menu_badge">Мітка</label>
                            <input
                                id="menu_badge"
                                v-model="form.badge"
                                type="text"
                                class="mt-1 h-9 w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-[#7561f7] focus:ring-[#7561f7]"
                                placeholder="Sale"
                            />
                            <InputError class="mt-1" :message="form.errors.badge" />
                        </div>

                        <label class="mt-5 inline-flex h-9 items-center justify-center gap-2 rounded-lg border border-slate-200 px-3 text-sm font-bold text-slate-600">
                            <input
                                v-model="form.is_active"
                                type="checkbox"
                                class="rounded border-slate-300 text-[#7561f7] focus:ring-[#7561f7]"
                            />
                            Активний
                        </label>
                    </div>

                    <div class="pt-2">
                        <button
                            type="submit"
                            class="inline-flex h-9 w-full items-center justify-center gap-2 rounded-lg bg-[#7561f7] px-3 text-sm font-bold text-white shadow-[0_10px_24px_rgba(117,97,247,0.22)] transition hover:bg-[#6552e8] disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="form.processing"
                        >
                            <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" />
                            <CheckCircle2 v-else-if="isEditing" class="h-4 w-4" />
                            <Plus v-else class="h-4 w-4" />
                            {{ isEditing ? 'Зберегти' : 'Додати' }}
                        </button>
                    </div>
                </form>
            </aside>
        </div>
    </AuthenticatedLayout>
</template>
