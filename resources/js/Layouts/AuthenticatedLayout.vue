<script setup>
import ApplicationLogo from '@/components/ApplicationLogo.vue';
import GlobalLoadingIndicator from '@/components/GlobalLoadingIndicator.vue';
import ToastNotifications from '@/components/ToastNotifications.vue';
import WorkspaceLoadingOverlay from '@/components/WorkspaceLoadingOverlay.vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
    ChartNoAxesCombined,
    Box,
    Boxes,
    ChevronDown,
    ChevronRight,
    ClipboardList,
    CreditCard,
    Droplet,
    FileSearch,
    FileSpreadsheet,
    Grid2X2,
    Home,
    Layers3,
    ListTree,
    LogOut,
    Map,
    Menu,
    MessageSquare,
    PackageSearch,
    PanelLeftClose,
    PanelLeftOpen,
    PlugZap,
    RefreshCw,
    ServerCog,
    Settings,
    ShieldCheck,
    ShoppingCart,
    SlidersHorizontal,
    Truck,
    UsersRound,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const page = usePage();
const sidebarOpen = ref(false);
const sidebarPinned = ref(typeof window === 'undefined'
    ? true
    : window.localStorage.getItem('adminSidebarPinned') !== 'false');
const sidebarHovered = ref(false);

const user = computed(() => page.props.auth.user);
const activeOrdersCount = computed(() => page.props.adminStats?.active_orders_count ?? 0);
const authRoles = computed(() => page.props.auth.roles ?? []);
const authPermissions = computed(() => new Set(page.props.auth.permissions ?? []));
const isAdmin = computed(() => authRoles.value.includes('admin'));
const canClearCache = computed(() => can('admin.system.cache.clear'));
const roleLabel = computed(() => {
    if (authRoles.value.includes('admin')) return 'Адмін';
    if (authRoles.value.includes('manager')) return 'Менеджер';

    return 'Клієнт';
});
const userInitials = computed(() => {
    const nameParts = (user.value?.name ?? 'Admin')
        .trim()
        .split(/\s+/)
        .filter(Boolean);

    return nameParts
        .slice(0, 2)
        .map((part) => part.slice(0, 1))
        .join('')
        .toUpperCase();
});
const can = (permission) => !permission || isAdmin.value || authPermissions.value.has(permission);

const navItems = [
    {
        label: 'Дашборд',
        routeName: 'dashboard',
        icon: Home,
        permission: 'admin.dashboard.view',
    },
    {
        label: 'Аналітика',
        icon: ChartNoAxesCombined,
        permissionAny: ['admin.analytics.view', 'admin.manager_activity.view'],
        children: [
            { label: 'Google', routeName: 'admin.analytics.show', routeParams: { channel: 'google' }, icon: ChevronRight, permission: 'admin.analytics.view' },
            { label: 'TikTok', routeName: 'admin.analytics.show', routeParams: { channel: 'tiktok' }, icon: ChevronRight, permission: 'admin.analytics.view' },
            { label: 'Meta', routeName: 'admin.analytics.show', routeParams: { channel: 'meta' }, icon: ChevronRight, permission: 'admin.analytics.view' },
            { label: 'Аналіз менеджерів', routeName: 'admin.manager-activity.index', icon: ClipboardList, permission: 'admin.manager_activity.view' },
        ],
    },
    {
        label: 'Каталог',
        icon: PackageSearch,
        permissionAny: [
            'admin.products.view',
            'admin.products.manage',
            'admin.product_feeds.view',
            'admin.product_feeds.manage',
            'admin.categories.manage',
            'admin.attributes.manage',
            'admin.color_groups.manage',
            'admin.size_charts.manage',
            'admin.reviews.manage',
        ],
        children: [
            { label: 'Товари', routeName: 'admin.products.index', icon: Box, permissionAny: ['admin.products.view', 'admin.products.manage'] },
            { label: 'Product Feeds', routeName: 'admin.product-feeds.index', icon: FileSpreadsheet, permissionAny: ['admin.product_feeds.view', 'admin.product_feeds.manage'] },
            { label: 'Категорії', routeName: 'admin.categories.index', icon: ListTree, permission: 'admin.categories.manage' },
            { label: 'Характеристики', routeName: 'admin.attributes.index', icon: SlidersHorizontal, permission: 'admin.attributes.manage' },
            { label: 'Групи кольорів', routeName: 'admin.color-groups.index', icon: Droplet, permission: 'admin.color_groups.manage' },
            { label: 'Розмірні сітки', routeName: 'admin.size-charts.index', icon: Grid2X2, permission: 'admin.size_charts.manage' },
            { label: 'Відгуки', routeName: 'admin.reviews.index', icon: MessageSquare, permission: 'admin.reviews.manage' },
        ],
    },
    {
        label: 'Замовлення',
        icon: ShoppingCart,
        permission: 'admin.orders.view',
        children: [
            { label: 'Нові', routeName: 'admin.orders.index', routeParams: { status_group: 'new' }, icon: ChevronRight, permission: 'admin.orders.view' },
            { label: 'В роботі', routeName: 'admin.orders.index', routeParams: { status_group: 'in_work' }, icon: ChevronRight, permission: 'admin.orders.view' },
            { label: 'Завершені', routeName: 'admin.orders.index', routeParams: { status_group: 'completed' }, icon: ChevronRight, permission: 'admin.orders.view' },
            { label: 'Повернення', routeName: 'admin.orders.index', routeParams: { status_group: 'returned' }, icon: ChevronRight, permission: 'admin.orders.view' },
        ],
    },
    {
        label: 'Оплата та доставка',
        icon: Truck,
        permissionAny: [
            'admin.payment_delivery.delivery_methods.view',
            'admin.payment_delivery.delivery_methods.manage',
            'admin.payment_delivery.payment_methods.view',
            'admin.payment_delivery.payment_methods.manage',
            'admin.payment_delivery.tariffs.view',
            'admin.payment_delivery.tariffs.manage',
            'admin.payment_delivery.transactions.view',
        ],
        children: [
            { label: 'Методи доставки', routeName: 'admin.payment-delivery.show', routeParams: { section: 'delivery-methods' }, icon: ChevronRight, permissionAny: ['admin.payment_delivery.delivery_methods.view', 'admin.payment_delivery.delivery_methods.manage'] },
            { label: 'Методи оплати', routeName: 'admin.payment-delivery.show', routeParams: { section: 'payment-methods' }, icon: ChevronRight, permissionAny: ['admin.payment_delivery.payment_methods.view', 'admin.payment_delivery.payment_methods.manage'] },
            { label: 'Тарифи', routeName: 'admin.payment-delivery.show', routeParams: { section: 'tariffs' }, icon: ChevronRight, permissionAny: ['admin.payment_delivery.tariffs.view', 'admin.payment_delivery.tariffs.manage'] },
            { label: 'Транзакції', routeName: 'admin.payment-delivery.show', routeParams: { section: 'transactions' }, icon: ChevronRight, permission: 'admin.payment_delivery.transactions.view' },
        ],
    },
    {
        label: 'Контент',
        icon: Layers3,
        permission: 'admin.site_structure.manage',
        children: [
            { label: 'Сторінки', routeName: 'admin.pages.index', icon: ChevronRight, permission: 'admin.site_structure.manage' },
            { label: 'Банери', routeName: 'admin.banners.index', icon: ChevronRight, permission: 'admin.site_structure.manage' },
            { label: 'Промокоди', icon: ChevronRight, permission: 'admin.site_structure.manage' },
        ],
    },
    {
        label: 'Структура сайту',
        icon: Boxes,
        permission: 'admin.site_structure.manage',
        children: [
            { label: 'Меню', routeName: 'admin.site-structure.show', routeParams: { menu: 'main' }, icon: ChevronRight, permission: 'admin.site_structure.manage' },
            { label: 'Верхня полоска', routeName: 'admin.site-structure.show', routeParams: { menu: 'utility' }, icon: ChevronRight, permission: 'admin.site_structure.manage' },
            { label: 'Footer', routeName: 'admin.site-structure.show', routeParams: { menu: 'footer' }, icon: ChevronRight, permission: 'admin.site_structure.manage' },
            { label: 'Mobile menu', routeName: 'admin.site-structure.show', routeParams: { menu: 'mobile' }, icon: ChevronRight, permission: 'admin.site_structure.manage' },
        ],
    },
    {
        label: 'SEO',
        icon: FileSearch,
        permissionAny: [
            'admin.seo.audit.view',
            'admin.seo.meta.manage',
            'admin.seo.schema.manage',
            'admin.seo.redirects.manage',
            'admin.seo.indexing.manage',
            'admin.seo.sitemap.manage',
            'admin.seo.filter_seo.manage',
        ],
        children: [
            { label: 'Overview / Audit', routeName: 'admin.seo.show', routeParams: { section: 'overview' }, icon: ChevronRight, permission: 'admin.seo.audit.view' },
            { label: 'Meta & Templates', routeName: 'admin.seo.show', routeParams: { section: 'meta' }, icon: ChevronRight, permission: 'admin.seo.meta.manage' },
            { label: 'Schema', routeName: 'admin.seo.show', routeParams: { section: 'schema' }, icon: ChevronRight, permission: 'admin.seo.schema.manage' },
            { label: 'Redirects', routeName: 'admin.seo.show', routeParams: { section: 'redirects' }, icon: ChevronRight, permission: 'admin.seo.redirects.manage' },
            { label: 'Indexing / Robots', routeName: 'admin.seo.show', routeParams: { section: 'indexing' }, icon: ChevronRight, permission: 'admin.seo.indexing.manage' },
            { label: 'Sitemap', routeName: 'admin.seo.show', routeParams: { section: 'sitemap' }, icon: ChevronRight, permission: 'admin.seo.sitemap.manage' },
            { label: 'Filter SEO', routeName: 'admin.seo.show', routeParams: { section: 'filter-seo' }, icon: ChevronRight, permission: 'admin.seo.filter_seo.manage' },
        ],
    },
    {
        label: 'Налаштування',
        icon: Settings,
        permissionAny: [
            'admin.settings.store.manage',
            'admin.settings.checkout.manage',
            'admin.settings.integrations.manage',
            'admin.settings.payments.manage',
            'admin.settings.security.manage',
            'admin.settings.system.manage',
            'admin.settings.tracking.manage',
            'admin.settings.nova_poshta.manage',
        ],
        children: [
            { label: 'Магазин', routeName: 'admin.settings.site.show', routeParams: { section: 'store' }, icon: Settings, permission: 'admin.settings.store.manage' },
            { label: 'Checkout', routeName: 'admin.settings.site.show', routeParams: { section: 'checkout' }, icon: ShoppingCart, permission: 'admin.settings.checkout.manage' },
            { label: 'Платежі', routeName: 'admin.settings.site.show', routeParams: { section: 'payments' }, icon: CreditCard, permission: 'admin.settings.payments.manage' },
            { label: 'Tracking', routeName: 'admin.settings.tracking.show', routeParams: { channel: 'google' }, icon: ChartNoAxesCombined, permission: 'admin.settings.tracking.manage' },
            { label: 'API Нова пошта', routeName: 'admin.settings.nova-poshta.edit', icon: Truck, permission: 'admin.settings.nova_poshta.manage' },
            { label: 'Інтеграції', routeName: 'admin.settings.site.show', routeParams: { section: 'integrations' }, icon: PlugZap, permission: 'admin.settings.integrations.manage' },
            { label: 'Безпека', routeName: 'admin.settings.site.show', routeParams: { section: 'security' }, icon: ShieldCheck, permission: 'admin.settings.security.manage' },
            { label: 'Система', routeName: 'admin.settings.site.show', routeParams: { section: 'system' }, icon: ServerCog, permission: 'admin.settings.system.manage' },
        ],
    },
    {
        label: 'Клієнти',
        routeName: 'admin.customers.index',
        icon: UsersRound,
        permission: 'admin.customers.view',
    },
    {
        label: 'Ролі та доступи',
        routeName: 'admin.roles.index',
        icon: ShieldCheck,
        permission: 'admin.roles.manage',
    },
    {
        label: 'Sitemap.xml',
        href: '/sitemap.xml',
        external: true,
        icon: Map,
        permission: 'admin.seo.sitemap.manage',
    },
];

const hasRoute = (name) => Boolean(name && route().has?.(name));
const hasAnyPermission = (item) => item.permissionAny?.some((permission) => can(permission));
const isVisible = (item) => can(item.permission) && (!item.permissionAny || hasAnyPermission(item));
const filterChildren = (children) => children
    ?.filter((child) => typeof child === 'string' || isVisible(child))
    ?? [];
const visibleNavItems = computed(() => navItems
    .map((item) => item.children ? { ...item, children: filterChildren(item.children) } : item)
    .filter((item) => isVisible(item) && (!item.children || item.children.length > 0)));
const routeParams = (item) => item.routeParams ?? {};
const itemHref = (item) => (hasRoute(item.routeName) ? route(item.routeName, routeParams(item)) : '#');
const routePattern = (name) => name?.replace(/\.index$/, '.*');
const isActive = (item) => item.routeName && (
    item.routeParams
        ? route().current(item.routeName, item.routeParams)
        : route().current(item.routeName) || route().current(routePattern(item.routeName))
);
const hasActiveChild = (item) => item.children?.some((child) => isActive(child));
const activeGroupLabel = computed(() => visibleNavItems.value.find((item) => hasActiveChild(item))?.label ?? null);
const openGroup = ref(activeGroupLabel.value);
const sidebarExpanded = computed(() => sidebarPinned.value || sidebarHovered.value || sidebarOpen.value);
const mainOffsetClass = computed(() => (sidebarPinned.value ? 'lg:pl-[316px]' : 'lg:pl-[88px]'));
const isOpen = (item) => openGroup.value === item.label;
const cacheClearing = ref(false);
const childLabel = (child) => (typeof child === 'string' ? child : child.label);
const childIcon = (child) => (typeof child === 'string' ? ChevronRight : child.icon);
const handlePlainNavClick = (item, event) => {
    if (!item.href) {
        event.preventDefault();

        return;
    }

    sidebarOpen.value = false;
};
const finishSidebarTransition = (element, done) => {
    const cleanup = (event) => {
        if (event.propertyName !== 'height') {
            return;
        }

        element.style.height = '';
        element.style.opacity = '';
        element.style.transform = '';
        element.style.transition = '';
        element.removeEventListener('transitionend', cleanup);
        done?.();
    };

    element.addEventListener('transitionend', cleanup);
};
const beforeSidebarSubmenuEnter = (element) => {
    element.style.height = '0';
    element.style.opacity = '0';
    element.style.transform = 'translateY(-6px)';
    element.style.overflow = 'hidden';
};
const sidebarSubmenuEnter = (element, done) => {
    const targetHeight = element.scrollHeight;

    element.style.transition = 'height 240ms cubic-bezier(0.22, 1, 0.36, 1), opacity 180ms ease, transform 220ms ease';

    requestAnimationFrame(() => {
        element.style.height = `${targetHeight}px`;
        element.style.opacity = '1';
        element.style.transform = 'translateY(0)';
    });

    finishSidebarTransition(element, done);
};
const sidebarSubmenuAfterEnter = (element) => {
    element.style.overflow = '';
};
const beforeSidebarSubmenuLeave = (element) => {
    element.style.height = `${element.scrollHeight}px`;
    element.style.opacity = '1';
    element.style.transform = 'translateY(0)';
    element.style.overflow = 'hidden';
};
const sidebarSubmenuLeave = (element, done) => {
    element.style.transition = 'height 190ms cubic-bezier(0.4, 0, 0.2, 1), opacity 150ms ease, transform 170ms ease';

    requestAnimationFrame(() => {
        element.style.height = '0';
        element.style.opacity = '0';
        element.style.transform = 'translateY(-4px)';
    });

    finishSidebarTransition(element, done);
};
const sidebarSubmenuAfterLeave = (element) => {
    element.style.overflow = '';
};
const toast = (type, message) => {
    window.dispatchEvent(new CustomEvent('admin-toast', {
        detail: { type, message },
    }));
};
const toggleGroup = (label) => {
    openGroup.value = openGroup.value === label ? null : label;
};
const toggleSidebarPin = () => {
    sidebarPinned.value = !sidebarPinned.value;

    window.localStorage.setItem('adminSidebarPinned', sidebarPinned.value ? 'true' : 'false');

    if (sidebarPinned.value) {
        sidebarHovered.value = false;
        openGroup.value = openGroup.value ?? activeGroupLabel.value;
    }
};

watch(activeGroupLabel, (label) => {
    openGroup.value = label;
});

const clearSystemCache = async () => {
    if (!hasRoute('admin.system.cache.clear') || cacheClearing.value) {
        return;
    }

    cacheClearing.value = true;

    try {
        const response = await window.axios.post(route('admin.system.cache.clear'));

        toast('success', response.data?.message ?? 'Кеш очищено');
    } catch (error) {
        toast('error', error.response?.data?.message ?? 'Не вдалося очистити кеш');
    } finally {
        cacheClearing.value = false;
    }
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
            class="fixed inset-y-0 left-0 z-40 flex w-[316px] flex-col border-r border-slate-100 bg-white shadow-[20px_0_45px_rgba(61,58,101,0.08)] transition-[transform,width] duration-200 lg:translate-x-0"
            :class="[
                sidebarOpen ? 'translate-x-0' : '-translate-x-full',
                sidebarExpanded ? 'lg:w-[316px]' : 'lg:w-[88px]',
            ]"
            @mouseenter="sidebarHovered = true"
            @mouseleave="sidebarHovered = false"
        >
            <div
                class="flex h-24 items-center gap-3 px-6 transition-all"
                :class="sidebarExpanded ? 'justify-between' : 'justify-center lg:px-4'"
            >
                <a
                    href="/"
                    target="_blank"
                    rel="noopener"
                    title="Відкрити сайт у новій вкладці"
                    class="flex min-w-0"
                    :class="sidebarExpanded ? 'flex-col items-start gap-1' : 'mx-auto h-14 w-14 items-center justify-center rounded-lg p-0'"
                >
                    <ApplicationLogo
                        v-show="!sidebarExpanded"
                        class="object-contain"
                        :class="'h-14 w-14'"
                    />
                    <img
                        v-show="sidebarExpanded"
                        src="/brand/dom-mood-wordmark.png"
                        alt="DomMood"
                        class="h-9 w-auto max-w-[190px] object-contain"
                    />
                    <span
                        v-show="sidebarExpanded"
                        class="pl-0.5 text-[10px] font-bold uppercase tracking-[0.24em] text-slate-400"
                    >
                        e-commerce crm
                    </span>
                </a>
                <button
                    v-show="sidebarExpanded"
                    type="button"
                    class="hidden h-10 w-10 shrink-0 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-[#29277f] lg:inline-flex"
                    :aria-label="sidebarPinned ? 'Відкріпити меню' : 'Закріпити меню'"
                    :title="sidebarPinned ? 'Відкріпити меню' : 'Закріпити меню'"
                    @click="toggleSidebarPin"
                >
                    <PanelLeftClose v-if="sidebarPinned" class="h-5 w-5" />
                    <PanelLeftOpen v-else class="h-5 w-5" />
                </button>
                <button
                    type="button"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 lg:hidden"
                    @click="sidebarOpen = false"
                    aria-label="Закрити меню"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>

            <nav
                class="flex-1 space-y-0.5 overflow-y-auto pb-6"
                :class="sidebarExpanded ? 'px-4' : 'px-2'"
            >
                <template v-for="item in visibleNavItems" :key="item.label">
                    <Link
                        v-if="item.routeName"
                        :href="itemHref(item)"
                        class="group flex items-center rounded-lg text-[15px] font-semibold transition"
                        :class="[
                            sidebarExpanded ? 'min-h-11 gap-4 px-3' : 'mx-auto h-11 w-11 justify-center p-0',
                            isActive(item)
                                ? 'bg-[#7561f7] text-white shadow-[0_12px_28px_rgba(117,97,247,0.34)]'
                                : 'text-[#615d72] hover:bg-slate-50 hover:text-[#29277f]',
                        ]"
                        :title="sidebarExpanded ? null : item.label"
                        @click="sidebarOpen = false"
                    >
                        <component :is="item.icon" class="h-5 w-5 shrink-0" />
                        <span
                            v-show="sidebarExpanded"
                            class="min-w-0 flex-1 whitespace-nowrap"
                        >
                            {{ item.label }}
                        </span>
                    </Link>

                    <div v-else-if="item.children">
                        <button
                            type="button"
                            class="group grid items-center rounded-lg text-left text-[15px] font-semibold transition"
                            :class="[
                                sidebarExpanded ? 'min-h-11 w-full grid-cols-[20px_minmax(0,1fr)_16px] gap-3 px-3' : 'mx-auto h-11 w-11 grid-cols-1 justify-items-center p-0',
                                hasActiveChild(item)
                                    ? 'bg-[#7561f7] text-white shadow-[0_12px_28px_rgba(117,97,247,0.34)]'
                                    : 'text-[#615d72] hover:bg-slate-50 hover:text-[#29277f]',
                            ]"
                            :title="sidebarExpanded ? null : item.label"
                            @click="toggleGroup(item.label)"
                        >
                            <component :is="item.icon" class="h-5 w-5 shrink-0" />
                            <span
                                v-show="sidebarExpanded"
                                class="min-w-0 truncate whitespace-nowrap"
                            >
                                {{ item.label }}
                            </span>
                            <ChevronDown
                                v-show="sidebarExpanded"
                                class="h-4 w-4 transition"
                                :class="isOpen(item) ? 'rotate-180' : ''"
                            />
                        </button>

                        <Transition
                            @before-enter="beforeSidebarSubmenuEnter"
                            @enter="sidebarSubmenuEnter"
                            @after-enter="sidebarSubmenuAfterEnter"
                            @before-leave="beforeSidebarSubmenuLeave"
                            @leave="sidebarSubmenuLeave"
                            @after-leave="sidebarSubmenuAfterLeave"
                        >
                            <div v-show="sidebarExpanded && isOpen(item)" class="space-y-0.5 py-1.5 pl-4">
                                <template v-for="child in item.children" :key="childLabel(child)">
                                    <Link
                                        v-if="hasRoute(child.routeName)"
                                        :href="itemHref(child)"
                                        class="group flex min-h-10 items-center gap-3 rounded-lg px-3 text-[15px] font-semibold transition"
                                        :class="isActive(child)
                                            ? 'bg-slate-100 text-[#29277f]'
                                            : 'text-[#615d72] hover:bg-slate-50 hover:text-[#29277f]'"
                                        @click="sidebarOpen = false"
                                    >
                                        <component :is="childIcon(child)" class="h-4 w-4 shrink-0" />
                                        <span class="min-w-0 flex-1 truncate whitespace-nowrap">{{ childLabel(child) }}</span>
                                    </Link>

                                    <a
                                        v-else
                                        href="#"
                                        class="group flex min-h-10 items-center gap-3 rounded-lg px-3 text-[15px] font-semibold text-[#615d72] transition hover:bg-slate-50 hover:text-[#29277f]"
                                        @click.prevent
                                    >
                                        <component :is="childIcon(child)" class="h-4 w-4 shrink-0" />
                                        <span class="min-w-0 flex-1 truncate whitespace-nowrap">{{ childLabel(child) }}</span>
                                    </a>
                                </template>
                            </div>
                        </Transition>
                    </div>

                    <a
                        v-else
                        :href="item.href ?? '#'"
                        :target="item.external ? '_blank' : null"
                        :rel="item.external ? 'noopener' : null"
                        class="group flex items-center rounded-lg text-[15px] font-semibold text-[#615d72] transition hover:bg-slate-50 hover:text-[#29277f]"
                        :class="sidebarExpanded ? 'min-h-11 gap-4 px-3' : 'mx-auto h-11 w-11 justify-center p-0'"
                        :title="sidebarExpanded ? null : item.label"
                        @click="handlePlainNavClick(item, $event)"
                    >
                        <component :is="item.icon" class="h-5 w-5 shrink-0" />
                        <span
                            v-show="sidebarExpanded"
                            class="min-w-0 flex-1 whitespace-nowrap"
                        >
                            {{ item.label }}
                        </span>
                        <ChevronRight
                            v-show="sidebarExpanded"
                            class="h-4 w-4 text-slate-400 transition group-hover:translate-x-0.5"
                        />
                    </a>
                </template>
            </nav>
        </aside>

        <div class="min-h-screen transition-[padding] duration-200" :class="mainOffsetClass">
            <header class="sticky top-0 z-20 bg-[#f4f5fb]/90 px-4 py-4 backdrop-blur md:px-7">
                <div class="flex min-h-20 flex-wrap items-center gap-3 rounded-lg bg-white px-4 shadow-[0_16px_45px_rgba(61,58,101,0.08)] md:px-6">
                    <div class="flex min-w-0 items-center gap-3">
                        <button
                            type="button"
                            class="inline-flex h-11 w-11 items-center justify-center rounded-lg border border-slate-200 text-slate-600 lg:hidden"
                            @click="sidebarOpen = true"
                            aria-label="Відкрити меню"
                        >
                            <Menu class="h-5 w-5" />
                        </button>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full bg-emerald-500 shadow-[0_0_0_4px_rgba(16,185,129,0.12)]"></span>
                                <span class="text-sm font-extrabold text-[#343241]">Операційна панель</span>
                            </div>
                            <div class="mt-0.5 hidden text-xs font-semibold text-slate-500 sm:block">
                                Замовлення, кеш і профіль адміністратора
                            </div>
                        </div>
                    </div>

                    <div class="ml-auto flex min-w-0 flex-wrap items-center justify-end gap-2 md:gap-3">
                        <Link
                            :href="hasRoute('admin.orders.index') ? route('admin.orders.index', { status_group: 'new' }) : '#'"
                            class="relative inline-flex h-11 w-11 items-center justify-center rounded-lg bg-[#f4f2ff] text-[#29277f] transition hover:bg-[#ece8ff]"
                            aria-label="Нові замовлення"
                            title="Нові замовлення"
                        >
                            <ShoppingCart class="h-5 w-5" />
                            <span
                                v-if="activeOrdersCount > 0"
                                class="absolute -right-1 -top-1 min-w-5 rounded-full bg-[#7561f7] px-1.5 text-center text-xs font-bold leading-5 text-white"
                            >
                                {{ activeOrdersCount }}
                            </span>
                        </Link>
                        <button
                            v-if="canClearCache"
                            class="inline-flex h-11 w-11 items-center justify-center rounded-lg border border-slate-200 text-[#615d72] transition hover:border-[#7561f7]/30 hover:bg-slate-50 hover:text-[#29277f] disabled:opacity-50"
                            type="button"
                            aria-label="Очистити кеш"
                            :title="cacheClearing ? 'Очищення кешу' : 'Очистити кеш'"
                            :disabled="cacheClearing"
                            @click="clearSystemCache"
                        >
                            <RefreshCw class="h-5 w-5" :class="cacheClearing ? 'animate-spin' : ''" />
                        </button>

                        <div class="hidden h-12 w-px bg-slate-200 lg:block"></div>

                        <Link :href="route('profile.edit')" class="flex min-w-0 items-center gap-3 rounded-lg px-2 py-2 transition hover:bg-slate-50">
                            <div class="text-right leading-tight max-md:hidden">
                                <div class="max-w-44 truncate text-sm font-bold text-[#3a3748]">{{ user.name }}</div>
                                <div class="text-xs font-medium text-slate-500">{{ roleLabel }}</div>
                            </div>
                            <div class="relative inline-flex h-12 w-12 items-center justify-center rounded-full bg-[#29277f] text-sm font-bold text-white shadow-lg shadow-indigo-900/10">
                                {{ userInitials }}
                                <span class="absolute bottom-0 right-0 h-3.5 w-3.5 rounded-full border-2 border-white bg-emerald-500"></span>
                            </div>
                        </Link>

                        <Link
                            :href="route('logout')"
                            method="post"
                            as="button"
                            class="inline-flex h-11 items-center justify-center gap-2 rounded-lg border border-slate-200 px-3 text-sm font-extrabold text-[#343241] transition hover:border-red-100 hover:bg-red-50 hover:text-red-600"
                            title="Вийти з адмінки"
                        >
                            <LogOut class="h-5 w-5" />
                            <span class="hidden xl:inline">Вийти</span>
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
