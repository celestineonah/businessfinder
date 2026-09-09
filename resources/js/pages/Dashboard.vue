<script setup lang="ts">
import BrandLogo from '@/components/public/BrandLogo.vue';
import {
    Head,
    Link,
    router,
    usePage,
} from '@inertiajs/vue3';
import {
    BarChart3,
    Building2,
    CircleCheck,
    Clock3,
    CreditCard,
    ExternalLink,
    LayoutDashboard,
    LogOut,
    Menu,
    MessageSquare,
    Plus,
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

defineProps<{
    metrics: Metrics;
    businesses: DashboardBusiness[];
}>();

const page = usePage();
const mobileOpen = ref(false);

const userName = computed(
    () =>
        (page.props.auth as any)
            ?.user?.name
        ?? 'Business Owner',
);

const userEmail = computed(
    () =>
        (page.props.auth as any)
            ?.user?.email
        ?? '',
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

function verificationClass(
    value: string,
): string {
    if (value === 'verified') {
        return 'bg-emerald-50 text-emerald-700';
    }

    if (value === 'pending') {
        return 'bg-amber-50 text-amber-700';
    }

    return 'bg-slate-100 text-slate-600';
}

function titleCase(
    value: string,
): string {
    return value
        .replace(/_/g, ' ')
        .replace(
            /\b\w/g,
            (character) =>
                character.toUpperCase(),
        );
}
</script>

<template>
    <Head title="Business Dashboard" />

    <div
        class="min-h-screen bg-[#f5f7f6] text-[#062c31]"
    >
        <!-- Mobile top bar -->
        <header
            class="sticky top-0 z-40 flex h-16 items-center justify-between border-b border-slate-200 bg-white px-5 lg:hidden"
        >
            <a
                href="/"
                class="flex items-center"
            >
                <BrandLogo
                    class="h-8 w-auto max-w-[215px]"
                />
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
            <!-- Sidebar -->
            <aside
                :class="[
                    'fixed inset-y-0 left-0 z-50 w-[270px] border-r border-slate-200 bg-white transition-transform lg:translate-x-0',
                    mobileOpen
                        ? 'translate-x-0'
                        : '-translate-x-full',
                ]"
            >
                <div
                    class="flex h-full flex-col"
                >
                    <div
                        class="flex h-[76px] items-center border-b border-slate-100 px-6"
                    >
                        <a
                            href="/"
                            class="flex items-center"
                        >
                            <BrandLogo
                                class="h-8 w-auto max-w-[215px]"
                            />
                        </a>
                    </div>

                    <nav
                        class="flex-1 overflow-y-auto px-4 py-6"
                    >
                        <p
                            class="px-3 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400"
                        >
                            Business
                        </p>

                        <div class="mt-3 grid gap-1">
                            <a
                                href="/dashboard"
                                class="flex items-center gap-3 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-black text-emerald-800"
                            >
                                <LayoutDashboard
                                    class="h-5 w-5"
                                />
                                Overview
                            </a>

                            <a
                                href="#listings"
                                class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-bold text-slate-600 transition hover:bg-slate-50"
                            >
                                <Building2
                                    class="h-5 w-5"
                                />
                                My Listings
                            </a>

                            <a
                                href="/add-business"
                                class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-bold text-slate-600 transition hover:bg-slate-50"
                            >
                                <Plus
                                    class="h-5 w-5"
                                />
                                Add Business
                            </a>
                        </div>

                        <p
                            class="mt-8 px-3 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400"
                        >
                            Growth
                        </p>

                        <div class="mt-3 grid gap-1">
                            <button
                                type="button"
                                disabled
                                class="flex items-center gap-3 rounded-xl px-4 py-3 text-left text-sm font-bold text-slate-400"
                            >
                                <Star
                                    class="h-5 w-5"
                                />
                                Reviews
                                <span
                                    class="ml-auto text-[9px] font-black uppercase"
                                >
                                    Soon
                                </span>
                            </button>

                            <button
                                type="button"
                                disabled
                                class="flex items-center gap-3 rounded-xl px-4 py-3 text-left text-sm font-bold text-slate-400"
                            >
                                <MessageSquare
                                    class="h-5 w-5"
                                />
                                Enquiries
                                <span
                                    class="ml-auto text-[9px] font-black uppercase"
                                >
                                    Soon
                                </span>
                            </button>

                            <button
                                type="button"
                                disabled
                                class="flex items-center gap-3 rounded-xl px-4 py-3 text-left text-sm font-bold text-slate-400"
                            >
                                <BarChart3
                                    class="h-5 w-5"
                                />
                                Analytics
                                <span
                                    class="ml-auto text-[9px] font-black uppercase"
                                >
                                    Soon
                                </span>
                            </button>

                            <button
                                type="button"
                                disabled
                                class="flex items-center gap-3 rounded-xl px-4 py-3 text-left text-sm font-bold text-slate-400"
                            >
                                <CreditCard
                                    class="h-5 w-5"
                                />
                                Subscription
                                <span
                                    class="ml-auto text-[9px] font-black uppercase"
                                >
                                    Soon
                                </span>
                            </button>
                        </div>

                        <p
                            class="mt-8 px-3 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400"
                        >
                            Account
                        </p>

                        <div class="mt-3 grid gap-1">
                            <a
                                href="/settings/profile"
                                class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-bold text-slate-600 transition hover:bg-slate-50"
                            >
                                <Settings
                                    class="h-5 w-5"
                                />
                                Settings
                            </a>
                        </div>
                    </nav>

                    <div
                        class="border-t border-slate-100 p-4"
                    >
                        <div
                            class="flex items-center gap-3 rounded-xl bg-slate-50 p-3"
                        >
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-xs font-black text-white"
                            >
                                {{ initials }}
                            </div>

                            <div class="min-w-0 flex-1">
                                <p
                                    class="truncate text-sm font-black text-slate-800"
                                >
                                    {{ userName }}
                                </p>

                                <p
                                    class="truncate text-xs text-slate-400"
                                >
                                    {{ userEmail }}
                                </p>
                            </div>

                            <button
                                type="button"
                                aria-label="Log out"
                                class="text-slate-400 hover:text-red-600"
                                @click="logout"
                            >
                                <LogOut
                                    class="h-4 w-4"
                                />
                            </button>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- Overlay -->
            <button
                v-if="mobileOpen"
                type="button"
                class="fixed inset-0 z-40 bg-slate-950/30 lg:hidden"
                aria-label="Close menu"
                @click="mobileOpen = false"
            />

            <!-- Main -->
            <main
                class="min-w-0 flex-1 lg:ml-[270px]"
            >
                <header
                    class="hidden h-[76px] items-center justify-between border-b border-slate-200 bg-white px-8 lg:flex xl:px-10"
                >
                    <div>
                        <h1
                            class="text-xl font-black text-slate-900"
                        >
                            Business Dashboard
                        </h1>

                        <p
                            class="text-xs text-slate-500"
                        >
                            Manage your BusinessFinder presence.
                        </p>
                    </div>

                    <div class="flex items-center gap-3">
                        <a
                            href="/"
                            class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-black text-slate-600 hover:bg-slate-50"
                        >
                            View Directory
                        </a>

                        <Link
                            href="/add-business"
                            class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-black text-white hover:bg-emerald-700"
                        >
                            <Plus class="h-4 w-4" />
                            Add Business
                        </Link>
                    </div>
                </header>

                <div
                    class="mx-auto max-w-[1500px] p-5 sm:p-7 lg:p-8 xl:p-10"
                >
                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
                    >
                        <div>
                            <p
                                class="text-xs font-black uppercase tracking-[0.2em] text-emerald-700"
                            >
                                Overview
                            </p>

                            <h2
                                class="mt-2 text-3xl font-black tracking-[-0.04em] text-slate-900"
                            >
                                Welcome, {{ userName }}
                            </h2>

                            <p
                                class="mt-2 text-sm text-slate-500"
                            >
                                These numbers come directly from
                                your BusinessFinder records.
                            </p>
                        </div>
                    </div>

                    <!-- Real KPIs -->
                    <section
                        class="mt-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-5"
                    >
                        <article
                            class="rounded-2xl border border-slate-200 bg-white p-5"
                        >
                            <Building2
                                class="h-6 w-6 text-emerald-600"
                            />

                            <div
                                class="mt-5 text-3xl font-black text-slate-900"
                            >
                                {{ metrics.totalBusinesses }}
                            </div>

                            <p
                                class="mt-1 text-xs font-bold text-slate-500"
                            >
                                My Businesses
                            </p>
                        </article>

                        <article
                            class="rounded-2xl border border-slate-200 bg-white p-5"
                        >
                            <CircleCheck
                                class="h-6 w-6 text-emerald-600"
                            />

                            <div
                                class="mt-5 text-3xl font-black text-slate-900"
                            >
                                {{ metrics.publishedListings }}
                            </div>

                            <p
                                class="mt-1 text-xs font-bold text-slate-500"
                            >
                                Published
                            </p>
                        </article>

                        <article
                            class="rounded-2xl border border-slate-200 bg-white p-5"
                        >
                            <Clock3
                                class="h-6 w-6 text-amber-600"
                            />

                            <div
                                class="mt-5 text-3xl font-black text-slate-900"
                            >
                                {{ metrics.draftListings }}
                            </div>

                            <p
                                class="mt-1 text-xs font-bold text-slate-500"
                            >
                                Unpublished Drafts
                            </p>
                        </article>

                        <article
                            class="rounded-2xl border border-slate-200 bg-white p-5"
                        >
                            <ShieldCheck
                                class="h-6 w-6 text-blue-600"
                            />

                            <div
                                class="mt-5 text-3xl font-black text-slate-900"
                            >
                                {{ metrics.verifiedBusinesses }}
                            </div>

                            <p
                                class="mt-1 text-xs font-bold text-slate-500"
                            >
                                Verified
                            </p>
                        </article>

                        <article
                            class="rounded-2xl border border-slate-200 bg-white p-5"
                        >
                            <Clock3
                                class="h-6 w-6 text-violet-600"
                            />

                            <div
                                class="mt-5 text-3xl font-black text-slate-900"
                            >
                                {{ metrics.pendingVerification }}
                            </div>

                            <p
                                class="mt-1 text-xs font-bold text-slate-500"
                            >
                                Pending Verification
                            </p>
                        </article>
                    </section>

                    <!-- Analytics honest state -->
                    <section
                        class="mt-6 grid gap-6 xl:grid-cols-[1.45fr_0.55fr]"
                    >
                        <article
                            class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-7"
                        >
                            <div
                                class="flex items-center justify-between"
                            >
                                <div>
                                    <h3
                                        class="text-lg font-black text-slate-900"
                                    >
                                        Business Performance
                                    </h3>

                                    <p
                                        class="mt-1 text-xs text-slate-500"
                                    >
                                        Analytics will appear when
                                        real event tracking is enabled.
                                    </p>
                                </div>

                                <BarChart3
                                    class="h-6 w-6 text-emerald-600"
                                />
                            </div>

                            <div
                                class="mt-7 flex min-h-[220px] items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50"
                            >
                                <div
                                    class="max-w-md px-6 text-center"
                                >
                                    <BarChart3
                                        class="mx-auto h-10 w-10 text-slate-300"
                                    />

                                    <h4
                                        class="mt-4 font-black text-slate-800"
                                    >
                                        No analytics data yet
                                    </h4>

                                    <p
                                        class="mt-2 text-sm leading-6 text-slate-500"
                                    >
                                        We will not display invented
                                        views, enquiries or growth
                                        figures.
                                    </p>
                                </div>
                            </div>
                        </article>

                        <article
                            class="rounded-2xl border border-emerald-100 bg-emerald-50 p-6 sm:p-7"
                        >
                            <ShieldCheck
                                class="h-7 w-7 text-emerald-600"
                            />

                            <h3
                                class="mt-5 text-lg font-black text-slate-900"
                            >
                                Listing Trust
                            </h3>

                            <p
                                class="mt-2 text-sm leading-6 text-slate-600"
                            >
                                BusinessFinder only shows a
                                Verified status after actual
                                verification evidence succeeds.
                            </p>

                            <a
                                href="/add-business"
                                class="mt-6 inline-flex items-center gap-2 text-sm font-black text-emerald-800"
                            >
                                Add another business
                                <span>→</span>
                            </a>
                        </article>
                    </section>

                    <!-- Listings -->
                    <section
                        id="listings"
                        class="mt-6 rounded-2xl border border-slate-200 bg-white"
                    >
                        <div
                            class="flex flex-col gap-4 border-b border-slate-200 p-6 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <h3
                                    class="text-lg font-black text-slate-900"
                                >
                                    My Listings
                                </h3>

                                <p
                                    class="mt-1 text-xs text-slate-500"
                                >
                                    Real businesses owned by your account.
                                </p>
                            </div>

                            <Link
                                href="/add-business"
                                class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 py-3 text-sm font-black text-white hover:bg-emerald-700"
                            >
                                <Plus class="h-4 w-4" />
                                Add Business
                            </Link>
                        </div>

                        <div
                            v-if="businesses.length === 0"
                            class="px-6 py-16 text-center"
                        >
                            <div
                                class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-50"
                            >
                                <Building2
                                    class="h-8 w-8 text-emerald-600"
                                />
                            </div>

                            <h4
                                class="mt-5 text-xl font-black text-slate-900"
                            >
                                No business listings yet
                            </h4>

                            <p
                                class="mx-auto mt-2 max-w-lg text-sm leading-6 text-slate-500"
                            >
                                Add your first real business to
                                BusinessFinder Nigeria. New
                                submissions remain unpublished
                                and unverified until appropriate
                                review steps are completed.
                            </p>

                            <Link
                                href="/add-business"
                                class="mt-6 inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-6 py-3 text-sm font-black text-white"
                            >
                                <Plus class="h-4 w-4" />
                                Add Your First Business
                            </Link>
                        </div>

                        <div
                            v-else
                            class="overflow-x-auto"
                        >
                            <table
                                class="w-full min-w-[900px] text-left"
                            >
                                <thead
                                    class="bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-400"
                                >
                                    <tr>
                                        <th class="px-6 py-4">
                                            Business
                                        </th>

                                        <th class="px-5 py-4">
                                            Location
                                        </th>

                                        <th class="px-5 py-4">
                                            Publication
                                        </th>

                                        <th class="px-5 py-4">
                                            Verification
                                        </th>

                                        <th class="px-5 py-4">
                                            Updated
                                        </th>

                                        <th class="px-6 py-4 text-right">
                                            Action
                                        </th>
                                    </tr>
                                </thead>

                                <tbody
                                    class="divide-y divide-slate-100"
                                >
                                    <tr
                                        v-for="business in businesses"
                                        :key="business.slug"
                                    >
                                        <td class="px-6 py-5">
                                            <p
                                                class="font-black text-slate-900"
                                            >
                                                {{ business.name }}
                                            </p>

                                            <p
                                                v-if="business.category"
                                                class="mt-1 text-xs text-slate-400"
                                            >
                                                {{ business.category }}
                                            </p>
                                        </td>

                                        <td
                                            class="px-5 py-5 text-sm text-slate-600"
                                        >
                                            {{
                                                business.location
                                                || 'Not specified'
                                            }}
                                        </td>

                                        <td class="px-5 py-5">
                                            <span
                                                :class="[
                                                    'rounded-full px-3 py-1.5 text-xs font-black',
                                                    business.isPublished
                                                        ? 'bg-emerald-50 text-emerald-700'
                                                        : 'bg-amber-50 text-amber-700',
                                                ]"
                                            >
                                                {{
                                                    business.isPublished
                                                        ? 'Published'
                                                        : 'Draft'
                                                }}
                                            </span>
                                        </td>

                                        <td class="px-5 py-5">
                                            <span
                                                :class="[
                                                    'rounded-full px-3 py-1.5 text-xs font-black',
                                                    verificationClass(
                                                        business.verificationStatus,
                                                    ),
                                                ]"
                                            >
                                                {{
                                                    titleCase(
                                                        business.verificationStatus,
                                                    )
                                                }}
                                            </span>
                                        </td>

                                        <td
                                            class="px-5 py-5 text-xs font-semibold text-slate-500"
                                        >
                                            {{
                                                business.updatedAt
                                                || '—'
                                            }}
                                        </td>

                                        <td
                                            class="px-6 py-5 text-right"
                                        >
                                            <a
                                                v-if="business.publicUrl"
                                                :href="business.publicUrl"
                                                class="inline-flex items-center gap-2 text-xs font-black text-emerald-700"
                                            >
                                                View
                                                <ExternalLink
                                                    class="h-3.5 w-3.5"
                                                />
                                            </a>

                                            <span
                                                v-else
                                                class="text-xs font-bold text-slate-400"
                                            >
                                                Not public
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </div>
</template>
