<script setup lang="ts">
import {
    Head,
    Link,
    router,
    usePage,
} from '@inertiajs/vue3';
import {
    BadgeCheck,
    BarChart3,
    Building2,
    CircleCheck,
    Clock3,
    ExternalLink,
    FileText,
    Inbox,
    LayoutDashboard,
    LogOut,
    Mail,
    Menu,
    MessageCircle,
    MessageSquare,
    Phone,
    Plus,
    Search,
    Settings,
    ShieldCheck,
    Star,
    X,
} from 'lucide-vue-next';
import {
    computed,
    ref,
} from 'vue';

type Metrics = {
    totalBusinesses: number;
    publishedListings: number;
    draftListings: number;
    verifiedBusinesses: number;
    pendingVerification: number;
};

type DashboardBusiness = {
    name: string;
    slug: string;
    category: string | null;
    location: string;
    listingStatus: string;
    claimStatus: string;
    verificationStatus: string;
    isPublished: boolean;
    updatedAt: string | null;
    publicUrl: string | null;
};

type Lead = {
    id: number;
    requestType: string;
    contactName: string;
    contactEmail: string | null;
    contactPhone: string | null;
    preferredChannel: string;
    message: string;
    status: string;
    deliveryStatus: string;
    createdAt: string | null;
    replyWhatsappUrl: string | null;
    business: {
        name: string;
        slug: string;
        publicUrl: string | null;
    };
};

type OwnerReview = {
    id: number;
    rating: number;
    title: string | null;
    body: string;
    status: string;
    publishedAt: string | null;
    updatedAt: string | null;
    reviewerName: string;
    business: {
        name: string;
        slug: string;
    };
};

type OwnerActivity = {
    metrics: {
        newLeads: number;
        totalLeads: number;
        quoteRequests: number;
        leadsLast30Days: number;
        pendingReviews: number;
        approvedReviews: number;
        averageRating: number | null;
    };
    recentLeads: Lead[];
    recentReviews: OwnerReview[];
};

const props = defineProps<{
    metrics: Metrics;
    businesses: DashboardBusiness[];
    ownerActivity?: OwnerActivity | null;
}>();

const page = usePage();
const mobileOpen = ref(false);

const emptyActivity: OwnerActivity = {
    metrics: {
        newLeads: 0,
        totalLeads: 0,
        quoteRequests: 0,
        leadsLast30Days: 0,
        pendingReviews: 0,
        approvedReviews: 0,
        averageRating: null,
    },
    recentLeads: [],
    recentReviews: [],
};

const activity = computed(
    () => props.ownerActivity ?? emptyActivity,
);

const user = computed(
    () => (page.props.auth as any)?.user ?? null,
);

const userName = computed(
    () => user.value?.name ?? 'Business Owner',
);

const userEmail = computed(
    () => user.value?.email ?? '',
);

const isAdmin = computed(
    () => Boolean(user.value?.is_admin),
);

const flashStatus = computed(
    () => (page.props.flash as any)?.status ?? null,
);

const initials = computed(() =>
    userName.value
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map(
            (part: string) =>
                part.charAt(0).toUpperCase(),
        )
        .join(''),
);

function logout() {
    router.post('/logout');
}

function updateLeadStatus(
    lead: Lead,
    status: string,
) {
    router.patch(
        `/dashboard/enquiries/${lead.id}`,
        {
            status,
        },
        {
            preserveScroll: true,
        },
    );
}

function titleCase(value: string): string {
    return value
        .replace(/_/g, ' ')
        .replace(
            /\b\w/g,
            (character) => character.toUpperCase(),
        );
}

function formatDate(value: string | null): string {
    if (! value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat(
        'en-NG',
        {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        },
    ).format(date);
}

function statusClass(value: string): string {
    if (value === 'responded' || value === 'closed') {
        return 'bg-emerald-50 text-emerald-700';
    }

    if (value === 'new') {
        return 'bg-amber-50 text-amber-700';
    }

    return 'bg-slate-100 text-slate-600';
}

function verificationClass(value: string): string {
    if (value === 'verified') {
        return 'bg-emerald-50 text-emerald-700';
    }

    if (value === 'pending') {
        return 'bg-amber-50 text-amber-700';
    }

    return 'bg-slate-100 text-slate-600';
}

function reviewStatusClass(value: string): string {
    if (value === 'approved') {
        return 'bg-emerald-50 text-emerald-700';
    }

    if (value === 'rejected') {
        return 'bg-rose-50 text-rose-700';
    }

    return 'bg-amber-50 text-amber-700';
}
</script>

<template>
    <Head title="Business Dashboard" />

    <div class="min-h-screen bg-[#f4f7f5] text-[#062c31]">
        <header
            class="sticky top-0 z-40 flex h-16 items-center justify-between border-b border-slate-200 bg-white px-5 lg:hidden"
        >
            <a
                href="/"
                class="flex items-center gap-2"
            >
                <svg
                    viewBox="0 0 50 56"
                    class="h-9 w-8"
                    aria-hidden="true"
                >
                    <ellipse
                        cx="25"
                        cy="50"
                        rx="18"
                        ry="4"
                        fill="#16a34a"
                        opacity=".9"
                    />
                    <path
                        d="M25 3C13.95 3 5 11.95 5 23c0 15 20 27 20 27s20-12 20-27C45 11.95 36.05 3 25 3Z"
                        fill="#078844"
                    />
                    <circle
                        cx="25"
                        cy="22"
                        r="8"
                        fill="white"
                    />
                </svg>

                <span class="font-black tracking-tight">
                    BusinessFinder
                    <span class="text-emerald-600">
                        Nigeria
                    </span>
                </span>
            </a>

            <button
                type="button"
                class="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200"
                @click="mobileOpen = !mobileOpen"
            >
                <X
                    v-if="mobileOpen"
                    class="h-5 w-5"
                />
                <Menu
                    v-else
                    class="h-5 w-5"
                />
            </button>
        </header>

        <div class="flex min-h-screen">
            <aside
                :class="[
                    'fixed inset-y-0 left-0 z-50 w-[282px] border-r border-slate-200 bg-white transition-transform lg:translate-x-0',
                    mobileOpen ? 'translate-x-0' : '-translate-x-full',
                ]"
            >
                <div class="flex h-full flex-col">
                    <div
                        class="flex h-[78px] items-center border-b border-slate-100 px-6"
                    >
                        <a
                            href="/"
                            class="flex items-center gap-2.5"
                        >
                            <svg
                                viewBox="0 0 50 56"
                                class="h-10 w-9"
                                aria-hidden="true"
                            >
                                <ellipse
                                    cx="25"
                                    cy="50"
                                    rx="18"
                                    ry="4"
                                    fill="#16a34a"
                                    opacity=".9"
                                />
                                <path
                                    d="M25 3C13.95 3 5 11.95 5 23c0 15 20 27 20 27s20-12 20-27C45 11.95 36.05 3 25 3Z"
                                    fill="#078844"
                                />
                                <circle
                                    cx="25"
                                    cy="22"
                                    r="8"
                                    fill="white"
                                />
                            </svg>

                            <span
                                class="text-lg font-black tracking-[-0.03em]"
                            >
                                BusinessFinder
                                <span class="text-emerald-600">
                                    Nigeria
                                </span>
                            </span>
                        </a>
                    </div>

                    <nav
                        class="flex-1 overflow-y-auto px-4 py-6"
                    >
                        <p
                            class="px-3 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400"
                        >
                            Workspace
                        </p>

                        <div class="mt-3 grid gap-1">
                            <a
                                href="/dashboard"
                                class="flex items-center gap-3 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-black text-emerald-800"
                            >
                                <LayoutDashboard class="h-5 w-5" />
                                Overview
                            </a>

                            <a
                                href="#businesses"
                                class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-bold text-slate-600 transition hover:bg-slate-50 hover:text-slate-900"
                            >
                                <Building2 class="h-5 w-5" />
                                Businesses
                                <span
                                    class="ml-auto rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-black"
                                >
                                    {{ metrics.totalBusinesses }}
                                </span>
                            </a>

                            <a
                                href="#leads"
                                class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-bold text-slate-600 transition hover:bg-slate-50 hover:text-slate-900"
                            >
                                <Inbox class="h-5 w-5" />
                                Leads
                                <span
                                    v-if="activity.metrics.newLeads > 0"
                                    class="ml-auto rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-black text-amber-800"
                                >
                                    {{ activity.metrics.newLeads }}
                                </span>
                            </a>

                            <a
                                href="#reviews"
                                class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-bold text-slate-600 transition hover:bg-slate-50 hover:text-slate-900"
                            >
                                <Star class="h-5 w-5" />
                                Reviews
                            </a>
                        </div>

                        <p
                            class="mt-7 px-3 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400"
                        >
                            Actions
                        </p>

                        <div class="mt-3 grid gap-1">
                            <Link
                                href="/dashboard/businesses"
                                class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-bold text-slate-600 transition hover:bg-slate-50 hover:text-slate-900"
                            >
                                <Building2 class="h-5 w-5" />
                                Manage listings
                            </Link>

                            <Link
                                href="/add-business"
                                class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-bold text-slate-600 transition hover:bg-slate-50 hover:text-slate-900"
                            >
                                <Plus class="h-5 w-5" />
                                Add business
                            </Link>

                            <Link
                                href="/search"
                                class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-bold text-slate-600 transition hover:bg-slate-50 hover:text-slate-900"
                            >
                                <Search class="h-5 w-5" />
                                Find & claim
                            </Link>

                            <Link
                                v-if="isAdmin"
                                href="/admin/listings"
                                class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-bold text-slate-600 transition hover:bg-slate-50 hover:text-slate-900"
                            >
                                <FileText class="h-5 w-5" />
                                Listing review
                            </Link>

                            <Link
                                v-if="isAdmin"
                                href="/admin/claims"
                                class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-bold text-slate-600 transition hover:bg-slate-50 hover:text-slate-900"
                            >
                                <ShieldCheck class="h-5 w-5" />
                                Claim review
                            </Link>

                            <Link
                                v-if="isAdmin"
                                href="/admin/engagement"
                                class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-bold text-slate-600 transition hover:bg-slate-50 hover:text-slate-900"
                            >
                                <MessageSquare class="h-5 w-5" />
                                Engagement admin
                            </Link>

                            <Link
                                href="/settings/profile"
                                class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-bold text-slate-600 transition hover:bg-slate-50 hover:text-slate-900"
                            >
                                <Settings class="h-5 w-5" />
                                Account settings
                            </Link>
                        </div>
                    </nav>

                    <div class="border-t border-slate-100 p-4">
                        <div class="rounded-2xl bg-slate-50 p-3">
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#062c31] text-xs font-black text-white"
                                >
                                    {{ initials || 'BF' }}
                                </div>

                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-xs font-black text-slate-800">
                                        {{ userName }}
                                    </p>
                                    <p class="truncate text-[10px] font-semibold text-slate-500">
                                        {{ userEmail }}
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    class="rounded-lg p-2 text-slate-400 hover:bg-white hover:text-rose-600"
                                    title="Sign out"
                                    @click="logout"
                                >
                                    <LogOut class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>

            <button
                v-if="mobileOpen"
                type="button"
                class="fixed inset-0 z-40 bg-slate-900/30 lg:hidden"
                aria-label="Close menu"
                @click="mobileOpen = false"
            />

            <main class="min-w-0 flex-1 lg:ml-[282px]">
                <div
                    class="mx-auto max-w-[1500px] px-5 py-7 sm:px-7 lg:px-10 lg:py-10"
                >
                    <div
                        v-if="flashStatus"
                        class="mb-6 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-900"
                    >
                        <CircleCheck class="mt-0.5 h-5 w-5 shrink-0" />
                        {{ flashStatus }}
                    </div>

                    <div
                        class="flex flex-col justify-between gap-5 lg:flex-row lg:items-end"
                    >
                        <div>
                            <p
                                class="text-[11px] font-black uppercase tracking-[0.18em] text-emerald-700"
                            >
                                Owner workspace
                            </p>

                            <h1
                                class="mt-2 text-3xl font-black tracking-[-0.045em] sm:text-4xl"
                            >
                                Business dashboard
                            </h1>

                            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                                Manage real listings, customer leads and moderated reviews.
                                Every number below comes from live BusinessFinder data.
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <Link
                                href="/search"
                                class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-black text-slate-700 shadow-sm transition hover:border-emerald-300 hover:text-emerald-700"
                            >
                                <Search class="h-4 w-4" />
                                Find a listing
                            </Link>

                            <Link
                                href="/add-business"
                                class="inline-flex items-center gap-2 rounded-xl bg-[#078844] px-4 py-3 text-sm font-black text-white shadow-sm transition hover:bg-emerald-700"
                            >
                                <Plus class="h-4 w-4" />
                                Add business
                            </Link>
                        </div>
                    </div>

                    <section
                        class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
                    >
                        <article
                            class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"
                        >
                            <div class="flex items-center justify-between">
                                <div
                                    class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700"
                                >
                                    <Building2 class="h-5 w-5" />
                                </div>
                                <span class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">
                                    Listings
                                </span>
                            </div>

                            <p class="mt-5 text-3xl font-black tracking-[-0.04em]">
                                {{ metrics.totalBusinesses }}
                            </p>
                            <p class="mt-1 text-xs font-bold text-slate-500">
                                {{ metrics.publishedListings }} public ·
                                {{ metrics.draftListings }} draft
                            </p>
                        </article>

                        <article
                            class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"
                        >
                            <div class="flex items-center justify-between">
                                <div
                                    class="flex h-11 w-11 items-center justify-center rounded-2xl bg-amber-50 text-amber-700"
                                >
                                    <Inbox class="h-5 w-5" />
                                </div>
                                <span class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">
                                    Leads
                                </span>
                            </div>

                            <p class="mt-5 text-3xl font-black tracking-[-0.04em]">
                                {{ activity.metrics.newLeads }}
                            </p>
                            <p class="mt-1 text-xs font-bold text-slate-500">
                                new · {{ activity.metrics.totalLeads }} total
                            </p>
                        </article>

                        <article
                            class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"
                        >
                            <div class="flex items-center justify-between">
                                <div
                                    class="flex h-11 w-11 items-center justify-center rounded-2xl bg-sky-50 text-sky-700"
                                >
                                    <FileText class="h-5 w-5" />
                                </div>
                                <span class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">
                                    Quote requests
                                </span>
                            </div>

                            <p class="mt-5 text-3xl font-black tracking-[-0.04em]">
                                {{ activity.metrics.quoteRequests }}
                            </p>
                            <p class="mt-1 text-xs font-bold text-slate-500">
                                {{ activity.metrics.leadsLast30Days }} leads in last 30 days
                            </p>
                        </article>

                        <article
                            class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"
                        >
                            <div class="flex items-center justify-between">
                                <div
                                    class="flex h-11 w-11 items-center justify-center rounded-2xl bg-amber-50 text-amber-600"
                                >
                                    <Star class="h-5 w-5" />
                                </div>
                                <span class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">
                                    Reviews
                                </span>
                            </div>

                            <p class="mt-5 text-3xl font-black tracking-[-0.04em]">
                                {{ activity.metrics.averageRating ?? '—' }}
                            </p>
                            <p class="mt-1 text-xs font-bold text-slate-500">
                                {{ activity.metrics.approvedReviews }} published ·
                                {{ activity.metrics.pendingReviews }} pending
                            </p>
                        </article>
                    </section>

                    <section
                        v-if="metrics.totalBusinesses === 0"
                        class="mt-7 rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center shadow-sm"
                    >
                        <Building2 class="mx-auto h-10 w-10 text-slate-300" />
                        <h2 class="mt-4 text-xl font-black">
                            No businesses connected yet
                        </h2>
                        <p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-500">
                            Add a genuine business you own or find an existing BusinessFinder
                            listing and submit an ownership claim. We do not create sample listings.
                        </p>

                        <div class="mt-5 flex flex-wrap justify-center gap-3">
                            <Link
                                href="/add-business"
                                class="rounded-xl bg-[#078844] px-5 py-3 text-sm font-black text-white"
                            >
                                Add your business
                            </Link>

                            <Link
                                href="/search"
                                class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-black text-slate-700"
                            >
                                Search listings
                            </Link>
                        </div>
                    </section>

                    <section
                        id="leads"
                        class="mt-7 scroll-mt-8 rounded-3xl border border-slate-200 bg-white shadow-sm"
                    >
                        <div
                            class="flex flex-col justify-between gap-3 border-b border-slate-100 px-6 py-5 sm:flex-row sm:items-center"
                        >
                            <div>
                                <p
                                    class="text-[10px] font-black uppercase tracking-[0.16em] text-emerald-700"
                                >
                                    Customer enquiries
                                </p>

                                <h2 class="mt-1 text-xl font-black tracking-[-0.025em]">
                                    Recent leads
                                </h2>
                            </div>

                            <div class="text-xs font-bold text-slate-400">
                                {{ activity.metrics.totalLeads }}
                                {{ activity.metrics.totalLeads === 1 ? 'lead' : 'leads' }}
                            </div>
                        </div>

                        <div
                            v-if="activity.recentLeads.length"
                            class="divide-y divide-slate-100"
                        >
                            <article
                                v-for="lead in activity.recentLeads"
                                :key="lead.id"
                                class="p-6"
                            >
                                <div
                                    class="flex flex-col justify-between gap-5 xl:flex-row xl:items-start"
                                >
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span
                                                class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black uppercase tracking-[0.08em] text-slate-600"
                                            >
                                                {{ lead.requestType === 'quote' ? 'Quote request' : 'Enquiry' }}
                                            </span>

                                            <span
                                                :class="[
                                                    'rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-[0.08em]',
                                                    statusClass(lead.status),
                                                ]"
                                            >
                                                {{ titleCase(lead.status) }}
                                            </span>

                                            <span class="text-xs font-semibold text-slate-400">
                                                {{ formatDate(lead.createdAt) }}
                                            </span>
                                        </div>

                                        <h3 class="mt-4 text-base font-black text-slate-900">
                                            {{ lead.contactName }}
                                            <span class="font-semibold text-slate-400">
                                                · {{ lead.business.name }}
                                            </span>
                                        </h3>

                                        <p
                                            class="mt-2 line-clamp-4 whitespace-pre-line text-sm leading-6 text-slate-600"
                                        >
                                            {{ lead.message }}
                                        </p>

                                        <div class="mt-4 flex flex-wrap gap-2">
                                            <a
                                                v-if="lead.replyWhatsappUrl"
                                                :href="lead.replyWhatsappUrl"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 px-3 py-2 text-xs font-black text-emerald-700"
                                            >
                                                <MessageCircle class="h-3.5 w-3.5" />
                                                WhatsApp
                                            </a>

                                            <a
                                                v-if="lead.contactPhone"
                                                :href="`tel:${lead.contactPhone}`"
                                                class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-3 py-2 text-xs font-black text-slate-700"
                                            >
                                                <Phone class="h-3.5 w-3.5" />
                                                {{ lead.contactPhone }}
                                            </a>

                                            <a
                                                v-if="lead.contactEmail"
                                                :href="`mailto:${lead.contactEmail}`"
                                                class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-3 py-2 text-xs font-black text-slate-700"
                                            >
                                                <Mail class="h-3.5 w-3.5" />
                                                Email
                                            </a>

                                            <a
                                                v-if="lead.business.publicUrl"
                                                :href="lead.business.publicUrl"
                                                class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-3 py-2 text-xs font-black text-slate-700"
                                            >
                                                <ExternalLink class="h-3.5 w-3.5" />
                                                Listing
                                            </a>
                                        </div>
                                    </div>

                                    <div class="flex shrink-0 flex-wrap gap-2 xl:max-w-[280px] xl:justify-end">
                                        <button
                                            v-if="lead.status === 'new'"
                                            type="button"
                                            class="rounded-xl border border-slate-200 px-3.5 py-2.5 text-xs font-black text-slate-700 transition hover:border-emerald-300 hover:text-emerald-700"
                                            @click="updateLeadStatus(lead, 'viewed')"
                                        >
                                            Mark viewed
                                        </button>

                                        <button
                                            v-if="lead.status === 'new' || lead.status === 'viewed'"
                                            type="button"
                                            class="rounded-xl bg-[#062c31] px-3.5 py-2.5 text-xs font-black text-white"
                                            @click="updateLeadStatus(lead, 'responded')"
                                        >
                                            Mark responded
                                        </button>

                                        <button
                                            v-if="lead.status !== 'closed'"
                                            type="button"
                                            class="rounded-xl border border-slate-200 px-3.5 py-2.5 text-xs font-black text-slate-600"
                                            @click="updateLeadStatus(lead, 'closed')"
                                        >
                                            Close
                                        </button>

                                        <button
                                            v-else
                                            type="button"
                                            class="rounded-xl border border-slate-200 px-3.5 py-2.5 text-xs font-black text-slate-600"
                                            @click="updateLeadStatus(lead, 'viewed')"
                                        >
                                            Reopen
                                        </button>
                                    </div>
                                </div>
                            </article>
                        </div>

                        <div
                            v-else
                            class="px-6 py-12 text-center"
                        >
                            <Inbox class="mx-auto h-9 w-9 text-slate-300" />
                            <h3 class="mt-3 text-base font-black text-slate-700">
                                No customer leads yet
                            </h3>
                            <p class="mx-auto mt-2 max-w-lg text-sm leading-6 text-slate-500">
                                Genuine enquiries and quote requests submitted through your
                                claimed listings will appear here. The dashboard does not seed fake activity.
                            </p>
                        </div>
                    </section>

                    <section
                        id="reviews"
                        class="mt-7 scroll-mt-8 rounded-3xl border border-slate-200 bg-white shadow-sm"
                    >
                        <div
                            class="flex flex-col justify-between gap-3 border-b border-slate-100 px-6 py-5 sm:flex-row sm:items-center"
                        >
                            <div>
                                <p
                                    class="text-[10px] font-black uppercase tracking-[0.16em] text-emerald-700"
                                >
                                    Customer feedback
                                </p>
                                <h2 class="mt-1 text-xl font-black tracking-[-0.025em]">
                                    Recent reviews
                                </h2>
                            </div>

                            <div class="flex items-center gap-3 text-xs font-bold text-slate-400">
                                <span>
                                    {{ activity.metrics.approvedReviews }} published
                                </span>
                                <span>
                                    {{ activity.metrics.pendingReviews }} pending
                                </span>
                            </div>
                        </div>

                        <div
                            v-if="activity.recentReviews.length"
                            class="grid gap-4 p-6 lg:grid-cols-2"
                        >
                            <article
                                v-for="review in activity.recentReviews"
                                :key="review.id"
                                class="rounded-2xl border border-slate-200 p-5"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-black text-slate-900">
                                            {{ review.business.name }}
                                        </p>
                                        <p class="mt-1 text-xs font-semibold text-slate-400">
                                            {{ review.reviewerName }} · {{ formatDate(review.updatedAt) }}
                                        </p>
                                    </div>

                                    <span
                                        :class="[
                                            'rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.08em]',
                                            reviewStatusClass(review.status),
                                        ]"
                                    >
                                        {{ review.status }}
                                    </span>
                                </div>

                                <div class="mt-4 flex gap-0.5 text-amber-500">
                                    <Star
                                        v-for="index in 5"
                                        :key="index"
                                        class="h-4 w-4"
                                        :fill="index <= review.rating ? 'currentColor' : 'none'"
                                    />
                                </div>

                                <h3
                                    v-if="review.title"
                                    class="mt-3 text-sm font-black text-slate-800"
                                >
                                    {{ review.title }}
                                </h3>

                                <p class="mt-2 line-clamp-4 whitespace-pre-line text-sm leading-6 text-slate-600">
                                    {{ review.body }}
                                </p>
                            </article>
                        </div>

                        <div
                            v-else
                            class="px-6 py-12 text-center"
                        >
                            <Star class="mx-auto h-9 w-9 text-slate-300" />
                            <h3 class="mt-3 text-base font-black text-slate-700">
                                No reviews yet
                            </h3>
                            <p class="mx-auto mt-2 max-w-lg text-sm leading-6 text-slate-500">
                                Approved and pending reviews for your businesses will appear here
                                when real customers submit them.
                            </p>
                        </div>
                    </section>

                    <section
                        id="businesses"
                        class="mt-7 scroll-mt-8 rounded-3xl border border-slate-200 bg-white shadow-sm"
                    >
                        <div
                            class="flex flex-col justify-between gap-3 border-b border-slate-100 px-6 py-5 sm:flex-row sm:items-center"
                        >
                            <div>
                                <p
                                    class="text-[10px] font-black uppercase tracking-[0.16em] text-emerald-700"
                                >
                                    Portfolio
                                </p>
                                <h2 class="mt-1 text-xl font-black tracking-[-0.025em]">
                                    Your businesses
                                </h2>
                            </div>

                            <Link
                                href="/add-business"
                                class="inline-flex items-center gap-2 text-xs font-black text-emerald-700"
                            >
                                <Plus class="h-4 w-4" />
                                Add business
                            </Link>
                        </div>

                        <div
                            v-if="businesses.length"
                            class="divide-y divide-slate-100"
                        >
                            <article
                                v-for="business in businesses"
                                :key="business.slug"
                                class="p-6"
                            >
                                <div
                                    class="flex flex-col justify-between gap-5 lg:flex-row lg:items-center"
                                >
                                    <div class="flex min-w-0 items-start gap-4">
                                        <div
                                            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-50 text-lg font-black text-emerald-700"
                                        >
                                            {{ business.name.charAt(0).toUpperCase() }}
                                        </div>

                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h3 class="truncate text-base font-black text-slate-900">
                                                    {{ business.name }}
                                                </h3>

                                                <span
                                                    :class="[
                                                        'rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.08em]',
                                                        verificationClass(business.verificationStatus),
                                                    ]"
                                                >
                                                    {{ titleCase(business.verificationStatus) }}
                                                </span>
                                            </div>

                                            <p class="mt-1 text-xs font-semibold text-slate-500">
                                                {{ business.category || 'Category not assigned' }}
                                                <template v-if="business.location">
                                                    · {{ business.location }}
                                                </template>
                                            </p>

                                            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[11px] font-bold text-slate-400">
                                                <span>
                                                    Claim: {{ titleCase(business.claimStatus) }}
                                                </span>
                                                <span>
                                                    {{ business.isPublished ? 'Published' : 'Not public yet' }}
                                                </span>
                                                <span>
                                                    Updated {{ formatDate(business.updatedAt) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap gap-2">
                                        <a
                                            v-if="business.publicUrl"
                                            :href="business.publicUrl"
                                            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-black text-slate-700"
                                        >
                                            <ExternalLink class="h-4 w-4" />
                                            View listing
                                        </a>

                                        <span
                                            v-else
                                            class="inline-flex items-center gap-2 rounded-xl bg-slate-100 px-4 py-2.5 text-xs font-black text-slate-500"
                                        >
                                            <Clock3 class="h-4 w-4" />
                                            Awaiting publication
                                        </span>
                                    </div>
                                </div>
                            </article>
                        </div>

                        <div
                            v-else
                            class="px-6 py-12 text-center"
                        >
                            <Building2 class="mx-auto h-9 w-9 text-slate-300" />
                            <p class="mt-3 text-sm font-bold text-slate-500">
                                No connected businesses.
                            </p>
                        </div>
                    </section>

                    <section
                        class="mt-7 grid gap-4 md:grid-cols-3"
                    >
                        <div class="rounded-2xl border border-slate-200 bg-white p-5">
                            <div class="flex items-center gap-3">
                                <BadgeCheck class="h-5 w-5 text-emerald-600" />
                                <span class="text-sm font-black">
                                    {{ metrics.verifiedBusinesses }}
                                    verified
                                </span>
                            </div>
                            <p class="mt-2 text-xs leading-5 text-slate-500">
                                Verified status is shown only where approved verification evidence exists.
                            </p>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-white p-5">
                            <div class="flex items-center gap-3">
                                <Clock3 class="h-5 w-5 text-amber-600" />
                                <span class="text-sm font-black">
                                    {{ metrics.pendingVerification }}
                                    pending verification
                                </span>
                            </div>
                            <p class="mt-2 text-xs leading-5 text-slate-500">
                                Ownership approval and verification evidence are separate workflows.
                            </p>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-[#062c31] p-5 text-white">
                            <div class="flex items-center gap-3">
                                <BarChart3 class="h-5 w-5 text-emerald-300" />
                                <span class="text-sm font-black">
                                    Real activity only
                                </span>
                            </div>
                            <p class="mt-2 text-xs leading-5 text-slate-300">
                                BusinessFinder never inserts sample leads, reviews or performance numbers.
                            </p>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </div>
</template>
