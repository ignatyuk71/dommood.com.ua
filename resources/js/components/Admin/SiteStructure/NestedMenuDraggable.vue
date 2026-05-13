<script>
import draggable from 'vuedraggable';
import {
    ChevronDown,
    ChevronRight,
    CornerUpLeft,
    FolderTree,
    GripVertical,
    Layers3,
    Link2,
    Pencil,
    Trash2,
} from 'lucide-vue-next';

export default {
    name: 'NestedMenuDraggable',
    components: {
        draggable,
        ChevronDown,
        ChevronRight,
        CornerUpLeft,
        FolderTree,
        GripVertical,
        Layers3,
        Link2,
        Pencil,
        Trash2,
    },
    props: {
        items: {
            type: Array,
            required: true,
        },
        depth: {
            type: Number,
            default: 0,
        },
        maxDepth: {
            type: Number,
            default: 4,
        },
        collapsedIds: {
            type: Object,
            required: true,
        },
    },
    emits: ['change', 'toggle', 'edit', 'delete', 'move-root'],
    methods: {
        emitChange() {
            this.$emit('change');
        },
        hasChildren(item) {
            return Boolean((item.children ?? []).length);
        },
        ensureChildren(item) {
            if (!Array.isArray(item.children)) {
                item.children = [];
            }

            return item.children;
        },
        isCollapsed(item) {
            return this.collapsedIds.has(item.id);
        },
        itemIcon(item) {
            if (item.type === 'category') {
                return FolderTree;
            }

            if (item.type === 'page') {
                return Layers3;
            }

            return Link2;
        },
        subtreeDepth(item) {
            const children = item.children ?? [];

            if (!children.length) {
                return 0;
            }

            return 1 + Math.max(...children.map((child) => this.subtreeDepth(child)));
        },
        containsList(item, targetList) {
            if (!targetList || !Array.isArray(item.children)) {
                return false;
            }

            if (item.children === targetList) {
                return true;
            }

            return item.children.some((child) => this.containsList(child, targetList));
        },
        canMove(event) {
            const dragged = event.draggedContext?.element;
            const targetList = event.relatedContext?.list;

            if (!dragged) {
                return true;
            }

            if (this.containsList(dragged, targetList)) {
                return false;
            }

            return this.depth + this.subtreeDepth(dragged) <= this.maxDepth;
        },
    },
};
</script>

<template>
    <draggable
        class="site-menu-draggable"
        tag="div"
        :list="items"
        :group="{ name: 'siteMenuTree' }"
        item-key="id"
        handle=".site-menu-drag-handle"
        ghost-class="site-menu-ghost"
        chosen-class="site-menu-chosen"
        drag-class="site-menu-drag"
        :animation="200"
        :fallback-on-body="true"
        :swap-threshold="0.65"
        :empty-insert-threshold="24"
        :move="canMove"
        @change="emitChange"
        @end="emitChange"
    >
        <template #item="{ element }">
            <div
                class="site-menu-node"
                :class="{ 'is-child': depth > 0 }"
            >
                <div class="site-menu-row group">
                    <GripVertical class="site-menu-drag-handle h-4 w-4 shrink-0 text-slate-300 transition group-hover:text-[#7561f7]" />

                    <button
                        v-if="hasChildren(element)"
                        type="button"
                        class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-slate-400 transition hover:bg-[#f5f4ff] hover:text-[#7561f7]"
                        :aria-label="isCollapsed(element) ? 'Розгорнути пункт' : 'Згорнути пункт'"
                        @click.stop="$emit('toggle', element)"
                    >
                        <ChevronDown
                            class="h-4 w-4 transition-transform duration-200"
                            :class="isCollapsed(element) ? '-rotate-90' : 'rotate-0'"
                        />
                    </button>
                    <span v-else class="h-7 w-7 shrink-0" />

                    <component :is="itemIcon(element)" class="h-4 w-4 shrink-0 text-[#7561f7]" />

                    <div class="min-w-0 flex-1">
                        <div class="flex min-w-0 flex-wrap items-center gap-2">
                            <span class="truncate text-sm font-bold text-[#343241]">{{ element.title }}</span>
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-500">
                                {{ element.type_label }}
                            </span>
                            <span
                                v-if="element.badge"
                                class="rounded-full bg-[#f0edff] px-2 py-0.5 text-[11px] font-bold text-[#7561f7]"
                            >
                                {{ element.badge }}
                            </span>
                            <span
                                class="rounded-full px-2 py-0.5 text-[11px] font-bold"
                                :class="element.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-400'"
                            >
                                {{ element.is_active ? 'Активний' : 'Вимкнено' }}
                            </span>
                        </div>
                        <div class="mt-0.5 flex items-center gap-1 truncate text-xs font-semibold text-slate-400">
                            <ChevronRight v-if="depth" class="h-3 w-3 shrink-0" />
                            <span class="truncate">{{ element.resolved_url || element.linkable_title || '-' }}</span>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-1 opacity-100 md:opacity-0 md:transition md:group-hover:opacity-100">
                        <button
                            v-if="depth"
                            type="button"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition hover:border-[#7561f7] hover:text-[#7561f7]"
                            title="Перенести в корінь меню"
                            aria-label="Перенести в корінь меню"
                            @click.stop="$emit('move-root', element)"
                        >
                            <CornerUpLeft class="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition hover:border-[#7561f7] hover:text-[#7561f7]"
                            aria-label="Редагувати"
                            @click.stop="$emit('edit', element)"
                        >
                            <Pencil class="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition hover:border-red-300 hover:text-red-600"
                            aria-label="Видалити"
                            @click.stop="$emit('delete', element)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <div
                    v-show="!isCollapsed(element)"
                    class="site-menu-children"
                    :class="{ 'is-empty': !hasChildren(element) }"
                >
                    <NestedMenuDraggable
                        :items="ensureChildren(element)"
                        :depth="depth + 1"
                        :max-depth="maxDepth"
                        :collapsed-ids="collapsedIds"
                        @change="$emit('change')"
                        @toggle="$emit('toggle', $event)"
                        @edit="$emit('edit', $event)"
                        @delete="$emit('delete', $event)"
                        @move-root="$emit('move-root', $event)"
                    />
                </div>
            </div>
        </template>
    </draggable>
</template>

<style scoped>
.site-menu-draggable {
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-height: 8px;
}

.site-menu-node {
    position: relative;
    border-radius: 8px;
    transition: background-color 0.2s ease, box-shadow 0.2s ease;
}

.site-menu-row {
    position: relative;
    z-index: 5;
    display: flex;
    min-height: 64px;
    align-items: center;
    gap: 8px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fff;
    padding: 10px 12px;
    transition: border-color 0.2s ease, background-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
}

.site-menu-row:hover {
    border-color: #c9c3ff;
    background: #fbfaff;
    box-shadow: 0 10px 26px rgba(117, 97, 247, 0.1);
}

.site-menu-drag-handle {
    cursor: grab;
}

.site-menu-drag-handle:active {
    cursor: grabbing;
}

.site-menu-children {
    position: relative;
    margin-left: 34px;
    padding-top: 8px;
    padding-left: 18px;
}

.site-menu-children::before {
    content: '';
    position: absolute;
    top: -8px;
    bottom: 0;
    left: 0;
    width: 2px;
    border-radius: 999px;
    background: linear-gradient(180deg, rgba(117, 97, 247, 0.18), rgba(117, 97, 247, 0.58));
}

.site-menu-children.is-empty {
    min-height: 10px;
}

.site-menu-children.is-empty::before {
    opacity: 0;
}

.site-menu-node.is-child::before {
    content: '';
    position: absolute;
    top: 23px;
    left: -18px;
    z-index: 1;
    width: 18px;
    height: 18px;
    border-bottom: 2px solid rgba(117, 97, 247, 0.72);
    border-left: 2px solid rgba(117, 97, 247, 0.72);
    border-bottom-left-radius: 14px;
}

.site-menu-chosen {
    background: rgba(117, 97, 247, 0.06);
    box-shadow: 0 0 0 2px rgba(117, 97, 247, 0.2);
}

.site-menu-ghost {
    background: rgba(117, 97, 247, 0.08);
    outline: 2px dashed #7561f7;
    outline-offset: 2px;
}

.site-menu-ghost .site-menu-row {
    border-color: #7561f7;
    background: #f5f4ff;
    opacity: 0.65;
    box-shadow: none;
}

.site-menu-drag {
    opacity: 0.96;
}
</style>
