<script setup>
import ApplicationLogo from '@/components/ApplicationLogo.vue';
import GlobalLoadingIndicator from '@/components/GlobalLoadingIndicator.vue';
import ToastNotifications from '@/components/ToastNotifications.vue';
import WorkspaceLoadingOverlay from '@/components/WorkspaceLoadingOverlay.vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
    Bell,
    Box,
    Boxes,
    ChevronDown,
    ChevronRight,
    Droplet,
    FileSearch,
    Grid2X2,
    Home,
    Layers3,
    ListTree,
    Map,
    Menu,
    MessageSquare,
    Moon,
    PackageSearch,
    RefreshCw,
    Search,
    Settings,
    ShieldCheck,
    ShoppingCart,
    SlidersHorizontal,
    Truck,
    UsersRound,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

const page = usePage();
const sidebarOpen = ref(false);
const openGroups = ref(['Каталог']);

const user = computed(() => page.props.auth.user);

const navItems = [
    {
        label: 'Дашборд',
        routeName: 'dashboard',
        icon: Home,
    },
    {
        label: 'Каталог',
        icon: PackageSearch,
        children: [
            { label: 'Товари', icon: Box },
            { label: 'Категорії', routeName: 'admin.categories.index', icon: ListTree },
            { label: 'Характеристики', icon: SlidersHorizontal },
            { label: 'Групи кольорів', routeName: 'admin.color-groups.index', icon: Droplet },
            { label: 'Розмірні сітки', routeName: 'admin.size-charts.index', icon: Grid2X2 },
            { label: 'Відгуки', routeName: 'admin.reviews.index', icon: MessageSquare },
        ],
    },
    {
        label: 'Замовлення',
        icon: ShoppingCart,
        children: ['Нові', 'В роботі', 'Завершені', 'Повернення'],
    },
    {
        label: 'Оплата та доставка',
        icon: Truck,
        children: ['Методи доставки', 'Методи оплати', 'Тарифи'],
    },
    {
        label: 'Контент',
        icon: Layers3,
        children: ['Сторінки', 'Банери', 'Промокоди'],
    },
    {
        label: 'Структура сайту',
        icon: Boxes,
        children: ['Меню', 'Footer', 'Mobile menu'],
    },
    {
        label: 'SEO',
        icon: FileSearch,
        children: ['Meta', 'Schema', 'Redirects'],
    },
    {
        label: 'Налаштування',
        icon: Settings,
        children: ['Магазин', 'Tracking', 'Інтеграції'],
    },
    {
        label: 'Клієнти',
        icon: UsersRound,
    },
    {
        label: 'Ролі та доступи',
        icon: ShieldCheck,
    },
    {
        label: 'Sitemap.xml',
        icon: Map,
    },
];

const hasRoute = (name) => Boolean(name && route().has?.(name));
const itemHref = (item) => (hasRoute(item.routeName) ? route(item.routeName) : '#');
const routePattern = (name) => name?.replace(/\.index$/, '.*');
const isActive = (item) => item.routeName && (
    route().current(item.routeName) || route().current(routePattern(item.routeName))
);
const hasActiveChild = (item) => item.children?.some((child) => isActive(child));
const isOpen = (item) => openGroups.value.includes(item.label) || hasActiveChild(item);
const childLabel = (child) => (typeof child === 'string' ? child : child.label);
const childIcon = (child) => (typeof child === 'string' ? ChevronRight : child.icon);
const toggleGroup = (label) => {
    openGroups.value = openGroups.value.includes(label)
        ? openGroups.value.filter((item) => item !== label)
        : [...openGroups.value, label];
};
</script>

<template>
    <div class="min-h-screen bg-[#f4f5fb] text-[#343241]">
        <GlobalLoadingIndicator />
        <ToastNotifications />

        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-30 bg-slate-950/30 backdrop-blur-sm lg:hidden"
            @click="sidebarOpen = false"
        />

        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-[292px] flex-col border-r border-slate-100 bg-white shadow-[20px_0_45px_rgba(61,58,101,0.08)] transition-transform duration-200 lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="flex h-24 items-center justify-between px-6">
                <Link :href="route('dashboard')" class="flex items-center gap-3">
                    <ApplicationLogo class="h-12 w-12 object-contain" />
                    <span class="text-2xl font-bold tracking-tight text-[#6d5df6]">
                        DomMood
                    </span>
                </Link>
                <button
                    type="button"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 lg:hidden"
                    @click="sidebarOpen = false"
                    aria-label="Закрити меню"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-4 pb-6">
                <template v-for="item in navItems" :key="item.label">
                    <Link
                        v-if="item.routeName"
                        :href="itemHref(item)"
                        class="group flex min-h-12 items-center gap-4 rounded-lg px-4 text-[15px] font-semibold transition"
                        :class="isActive(item)
                            ? 'bg-[#7561f7] text-white shadow-[0_12px_28px_rgba(117,97,247,0.34)]'
                            : 'text-[#615d72] hover:bg-slate-50 hover:text-[#29277f]'"
                        @click="sidebarOpen = false"
                    >
                        <component :is="item.icon" class="h-5 w-5 shrink-0" />
                        <span class="flex-1">{{ item.label }}</span>
                    </Link>

                    <div v-else-if="item.children">
                        <button
                            type="button"
                            class="group flex min-h-12 w-full items-center gap-4 rounded-lg px-4 text-left text-[15px] font-semibold transition"
                            :class="hasActiveChild(item)
                                ? 'bg-[#7561f7] text-white shadow-[0_12px_28px_rgba(117,97,247,0.34)]'
                                : 'text-[#615d72] hover:bg-slate-50 hover:text-[#29277f]'"
                            @click="toggleGroup(item.label)"
                        >
                            <component :is="item.icon" class="h-5 w-5 shrink-0" />
                            <span class="flex-1">{{ item.label }}</span>
                            <ChevronDown
                                class="h-4 w-4 transition"
                                :class="isOpen(item) ? 'rotate-180' : ''"
                            />
                        </button>

                        <div v-show="isOpen(item)" class="space-y-1 py-2 pl-7">
                            <template v-for="child in item.children" :key="childLabel(child)">
                                <Link
                                    v-if="hasRoute(child.routeName)"
                                    :href="itemHref(child)"
                                    class="group flex min-h-11 items-center gap-4 rounded-lg px-4 text-[15px] font-semibold transition"
                                    :class="isActive(child)
                                        ? 'bg-slate-100 text-[#29277f]'
                                        : 'text-[#615d72] hover:bg-slate-50 hover:text-[#29277f]'"
                                    @click="sidebarOpen = false"
                                >
                                    <component :is="childIcon(child)" class="h-4 w-4 shrink-0" />
                                    <span class="flex-1">{{ childLabel(child) }}</span>
                                </Link>

                                <a
                                    v-else
                                    href="#"
                                    class="group flex min-h-11 items-center gap-4 rounded-lg px-4 text-[15px] font-semibold text-[#615d72] transition hover:bg-slate-50 hover:text-[#29277f]"
                                    @click.prevent
                                >
                                    <component :is="childIcon(child)" class="h-4 w-4 shrink-0" />
                                    <span class="flex-1">{{ childLabel(child) }}</span>
                                </a>
                            </template>
                        </div>
                    </div>

                    <a
                        v-else
                        href="#"
                        class="group flex min-h-12 items-center gap-4 rounded-lg px-4 text-[15px] font-semibold text-[#615d72] transition hover:bg-slate-50 hover:text-[#29277f]"
                        @click.prevent
                    >
                        <component :is="item.icon" class="h-5 w-5 shrink-0" />
                        <span class="flex-1">{{ item.label }}</span>
                        <ChevronRight class="h-4 w-4 text-slate-400 transition group-hover:translate-x-0.5" />
                    </a>
                </template>
            </nav>
        </aside>

        <div class="min-h-screen lg:pl-[292px]">
            <header class="sticky top-0 z-20 bg-[#f4f5fb]/90 px-4 py-4 backdrop-blur md:px-7">
                <div class="flex min-h-20 items-center justify-between rounded-lg bg-white px-4 shadow-[0_16px_45px_rgba(61,58,101,0.08)] md:px-6">
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            class="inline-flex h-11 w-11 items-center justify-center rounded-lg border border-slate-200 text-slate-600 lg:hidden"
                            @click="sidebarOpen = true"
                            aria-label="Відкрити меню"
                        >
                            <Menu class="h-5 w-5" />
                        </button>
                        <div class="hidden items-center gap-3 rounded-lg border border-slate-200 px-4 py-3 text-slate-500 xl:flex">
                            <Search class="h-5 w-5" />
                            <span class="text-sm font-medium">Пошук товарів, замовлень, клієнтів</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 md:gap-4">
                        <button class="relative inline-flex h-11 w-11 items-center justify-center rounded-lg text-[#6e6a7f] hover:bg-slate-50" type="button" aria-label="Кошик">
                            <ShoppingCart class="h-6 w-6" />
                            <span class="absolute -right-1 -top-1 rounded-full bg-[#7561f7] px-1.5 text-xs font-bold text-white">2</span>
                        </button>
                        <button class="hidden h-11 w-11 items-center justify-center rounded-lg text-[#6e6a7f] hover:bg-slate-50 sm:inline-flex" type="button" aria-label="Оновити">
                            <RefreshCw class="h-5 w-5" />
                        </button>
                        <button class="relative inline-flex h-11 w-11 items-center justify-center rounded-lg text-[#6e6a7f] hover:bg-slate-50" type="button" aria-label="Сповіщення">
                            <Bell class="h-5 w-5" />
                            <span class="absolute -right-1 -top-1 rounded-full bg-[#ef5252] px-1.5 text-xs font-bold text-white">2</span>
                        </button>
                        <button class="hidden h-11 w-11 items-center justify-center rounded-lg text-[#6e6a7f] hover:bg-slate-50 sm:inline-flex" type="button" aria-label="Темний режим">
                            <Moon class="h-5 w-5" />
                        </button>

                        <div class="hidden h-12 w-px bg-slate-200 md:block"></div>

                        <Link :href="route('profile.edit')" class="flex items-center gap-3 rounded-lg px-2 py-2 transition hover:bg-slate-50">
                            <div class="text-right leading-tight max-md:hidden">
                                <div class="text-sm font-bold text-[#3a3748]">{{ user.name }}</div>
                                <div class="text-xs font-medium text-slate-500">Адмін</div>
                            </div>
                            <div class="relative inline-flex h-12 w-12 items-center justify-center rounded-full bg-[#29277f] text-sm font-bold text-white shadow-lg shadow-indigo-900/10">
                                {{ user.name?.slice(0, 1) }}
                                <span class="absolute bottom-0 right-0 h-3.5 w-3.5 rounded-full border-2 border-white bg-emerald-500"></span>
                            </div>
                        </Link>
                    </div>
                </div>
            </header>

            <main class="relative min-h-[calc(100vh-112px)] px-4 pb-10 md:px-7">
                <WorkspaceLoadingOverlay />

                <section v-if="$slots.header" class="mb-6 rounded-lg bg-white px-5 py-6 shadow-[0_16px_45px_rgba(61,58,101,0.08)] md:px-7">
                    <slot name="header" />
                </section>

                <slot />
            </main>
        </div>
    </div>
</template>
