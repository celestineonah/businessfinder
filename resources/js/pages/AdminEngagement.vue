<script setup lang="ts">
import {
    Head,
    Link,
    router,
} from '@inertiajs/vue3';
import {
    ArrowLeft,
    BadgeCheck,
    Building2,
    Check,
    Inbox,
    Mail,
    MessageCircle,
    ShieldCheck,
    Star,
    Trash2,
    User,
    X,
} from 'lucide-vue-next';
import {
    computed,
    reactive,
    ref,
} from 'vue';

type Metrics = {
    pendingReviews: number;
    approvedReviews: number;
    rejectedReviews: number;
    newEnquiries: number;
    totalEnquiries: number;
    unassignedEnquiries: number;
};

type PendingReview = {
    id: number;
    rating: number;
    title: string | null;
    body: string;
    createdAt: string | null;
    business: {
        name: string;
        slug: string;
    };
    reviewer: {
        name: string;
        email: string;
    };
};

type AdminEnquiry = {
    id: number;
    requestType: string;
    contactName: string;
    contactEmail: string | null;
    contactPhone: string | null;
    preferredChannel: string;
    message: string;
    status: string;
    deliveryStatus: string;
    adminNotes: string | null;
    createdAt: string | null;
    accountName: string | null;
    business: {
        name: string;
        slug: string;
        claimed: boolean;
    };
};

const props = defineProps<{
    metrics: Metrics;
    pendingReviews: PendingReview[];
    enquiries: AdminEnquiry[];
    status?: string | null;
}>();

const activeTab = ref<'reviews' | 'enquiries'>(
    props.pendingReviews.length > 0 ? 'reviews' : 'enquiries',
);

const rejectOpen = ref<number | null>(null);
const spamOpen = ref<number | null>(null);

const rejectionNotes = reactive<Record<number, string>>({});
const spamNotes = reactive<Record<number, string>>({});

const visibleCount = computed(() =>
    activeTab.value === 'reviews'
        ? props.pendingReviews.length
        : props.enquiries.length,
);

function approveReview(review: PendingReview) {
    router.post(
        `/admin/reviews/${review.id}/approve`,
        {},
        {
            preserveScroll: true,
        },
    );
}

function rejectReview(review: PendingReview) {
    const notes = (rejectionNotes[review.id] ?? '').trim();

    if (! notes) {
        return;
    }

    router.post(
        `/admin/reviews/${review.id}/reject`,
        {
            moderation_notes: notes,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                rejectOpen.value = null;
                rejectionNotes[review.id] = '';
            },
        },
    );
}

function markSpam(enquiry: AdminEnquiry) {
    router.post(
        `/admin/enquiries/${enquiry.id}/spam`,
        {
            admin_notes: (spamNotes[enquiry.id] ?? '').trim() || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                spamOpen.value = null;
                spamNotes[enquiry.id] = '';
            },
        },
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
            hour: '2-digit',
            minute: '2-digit',
        },
    ).format(date);
}

function titleCase(value: string): string {
    return value
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (character) => character.toUpperCase());
}
</script>

<template>
    <Head title="Engagement Administration" />

    <div class="min-h-screen bg-[#f5f7f6] text-[#062c31]">
        <header class="border-b border-slate-200 bg-white">
            <div
                class="mx-auto flex min-h-[76px] max-w-[1500px] flex-wrap items-center justify-between gap-4 px-5 py-4 sm:px-7 lg:px-10"
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

                    <div>
                        <p class="text-lg font-black tracking-[-0.03em]">
                            BusinessFinder
                            <span class="text-emerald-600">
                                Nigeria
                            </span>
                        </p>
                        <p
                            class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400"
                        >
                            Administration
                        </p>
                    </div>
                </a>

                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        href="/admin/claims"
                        class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-black text-slate-700 transition hover:border-emerald-300 hover:text-emerald-700"
                    >
                        <ShieldCheck class="h-4 w-4" />
                        Claims & verification
                    </Link>

                    <Link
                        href="/dashboard"
                        class="inline-flex items-center gap-2 rounded-xl bg-[#062c31] px-4 py-2.5 text-xs font-black text-white"
                    >
                        <ArrowLeft class="h-4 w-4" />
                        Owner dashboard
                    </Link>
                </div>
            </div>
        </header>

        <main
            class="mx-auto max-w-[1500px] px-5 py-8 sm:px-7 lg:px-10 lg:py-10"
        >
            <div
                v-if="status"
                class="mb-6 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-900"
            >
                <BadgeCheck class="mt-0.5 h-5 w-5 shrink-0" />
                {{ status }}
            </div>

            <div
                class="flex flex-col justify-between gap-5 lg:flex-row lg:items-end"
            >
                <div>
                    <p
                        class="text-[11px] font-black uppercase tracking-[0.18em] text-emerald-700"
                    >
                        Trust & lead operations
                    </p>

                    <h1
                        class="mt-2 text-3xl font-black tracking-[-0.045em] sm:text-4xl"
                    >
                        Engagement administration
                    </h1>

                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                        Moderate real reviews and inspect genuine customer enquiries.
                        Nothing is pre-populated or fabricated.
                    </p>
                </div>

                <div
                    class="rounded-2xl bg-white px-4 py-3 text-xs font-black text-slate-500 shadow-sm ring-1 ring-slate-200"
                >
                    {{ visibleCount }} records in current view
                </div>
            </div>

            <section
                class="mt-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-6"
            >
                <article
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                >
                    <p class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">
                        Pending reviews
                    </p>
                    <p class="mt-3 text-2xl font-black">
                        {{ metrics.pendingReviews }}
                    </p>
                </article>

                <article
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                >
                    <p class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">
                        Published
                    </p>
                    <p class="mt-3 text-2xl font-black text-emerald-700">
                        {{ metrics.approvedReviews }}
                    </p>
                </article>

                <article
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                >
                    <p class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">
                        Rejected
                    </p>
                    <p class="mt-3 text-2xl font-black text-rose-700">
                        {{ metrics.rejectedReviews }}
                    </p>
                </article>

                <article
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                >
                    <p class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">
                        New enquiries
                    </p>
                    <p class="mt-3 text-2xl font-black text-amber-700">
                        {{ metrics.newEnquiries }}
                    </p>
                </article>

                <article
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                >
                    <p class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">
                        Real leads
                    </p>
                    <p class="mt-3 text-2xl font-black">
                        {{ metrics.totalEnquiries }}
                    </p>
                </article>

                <article
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                >
                    <p class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">
                        Unassigned
                    </p>
                    <p class="mt-3 text-2xl font-black">
                        {{ metrics.unassignedEnquiries }}
                    </p>
                </article>
            </section>

            <div
                class="mt-7 inline-flex rounded-2xl border border-slate-200 bg-white p-1.5 shadow-sm"
            >
                <button
                    type="button"
                    :class="[
                        'rounded-xl px-5 py-2.5 text-xs font-black transition',
                        activeTab === 'reviews'
                            ? 'bg-[#062c31] text-white'
                            : 'text-slate-500 hover:bg-slate-50',
                    ]"
                    @click="activeTab = 'reviews'"
                >
                    Review moderation
                    <span
                        class="ml-1 rounded-full bg-white/10 px-2 py-0.5"
                    >
                        {{ metrics.pendingReviews }}
                    </span>
                </button>

                <button
                    type="button"
                    :class="[
                        'rounded-xl px-5 py-2.5 text-xs font-black transition',
                        activeTab === 'enquiries'
                            ? 'bg-[#062c31] text-white'
                            : 'text-slate-500 hover:bg-slate-50',
                    ]"
                    @click="activeTab = 'enquiries'"
                >
                    Enquiry queue
                    <span
                        class="ml-1 rounded-full bg-white/10 px-2 py-0.5"
                    >
                        {{ metrics.totalEnquiries }}
                    </span>
                </button>
            </div>

            <section
                v-if="activeTab === 'reviews'"
                class="mt-5"
            >
                <div
                    v-if="pendingReviews.length"
                    class="grid gap-5 xl:grid-cols-2"
                >
                    <article
                        v-for="review in pendingReviews"
                        :key="review.id"
                        class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"
                    >
                        <div
                            class="flex flex-wrap items-start justify-between gap-4"
                        >
                            <div>
                                <a
                                    :href="`/business/${review.business.slug}`"
                                    class="text-base font-black text-slate-900 hover:text-emerald-700"
                                >
                                    {{ review.business.name }}
                                </a>

                                <div
                                    class="mt-2 flex flex-wrap items-center gap-3 text-xs font-semibold text-slate-400"
                                >
                                    <span class="inline-flex items-center gap-1.5">
                                        <User class="h-3.5 w-3.5" />
                                        {{ review.reviewer.name }}
                                    </span>

                                    <a
                                        :href="`mailto:${review.reviewer.email}`"
                                        class="inline-flex items-center gap-1.5 hover:text-emerald-700"
                                    >
                                        <Mail class="h-3.5 w-3.5" />
                                        {{ review.reviewer.email }}
                                    </a>
                                </div>
                            </div>

                            <div class="flex gap-0.5 text-amber-500">
                                <Star
                                    v-for="index in 5"
                                    :key="index"
                                    class="h-4 w-4"
                                    :fill="index <= review.rating ? 'currentColor' : 'none'"
                                />
                            </div>
                        </div>

                        <p class="mt-3 text-xs font-semibold text-slate-400">
                            Submitted {{ formatDate(review.createdAt) }}
                        </p>

                        <h3
                            v-if="review.title"
                            class="mt-4 text-sm font-black text-slate-900"
                        >
                            {{ review.title }}
                        </h3>

                        <p
                            class="mt-2 whitespace-pre-line rounded-2xl bg-slate-50 p-4 text-sm leading-6 text-slate-600"
                        >
                            {{ review.body }}
                        </p>

                        <div class="mt-5 flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-xl bg-[#078844] px-4 py-2.5 text-xs font-black text-white"
                                @click="approveReview(review)"
                            >
                                <Check class="h-4 w-4" />
                                Approve & publish
                            </button>

                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-xs font-black text-rose-700"
                                @click="rejectOpen = rejectOpen === review.id ? null : review.id"
                            >
                                <X class="h-4 w-4" />
                                Reject
                            </button>
                        </div>

                        <div
                            v-if="rejectOpen === review.id"
                            class="mt-4 rounded-2xl border border-rose-200 bg-rose-50/50 p-4"
                        >
                            <label class="grid gap-2">
                                <span class="text-xs font-black text-rose-800">
                                    Rejection reason
                                </span>

                                <textarea
                                    v-model="rejectionNotes[review.id]"
                                    rows="3"
                                    maxlength="2000"
                                    class="rounded-xl border border-rose-200 bg-white px-3.5 py-3 text-sm outline-none focus:border-rose-400"
                                    placeholder="Give the reviewer a useful moderation reason."
                                />
                            </label>

                            <button
                                type="button"
                                :disabled="!(rejectionNotes[review.id] || '').trim()"
                                class="mt-3 rounded-xl bg-rose-700 px-4 py-2.5 text-xs font-black text-white disabled:cursor-not-allowed disabled:opacity-50"
                                @click="rejectReview(review)"
                            >
                                Confirm rejection
                            </button>
                        </div>
                    </article>
                </div>

                <div
                    v-else
                    class="rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center shadow-sm"
                >
                    <Star class="mx-auto h-10 w-10 text-slate-300" />
                    <h2 class="mt-4 text-lg font-black">
                        No reviews awaiting moderation
                    </h2>
                    <p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-500">
                        New customer reviews appear here before any public rating changes.
                    </p>
                </div>
            </section>

            <section
                v-else
                class="mt-5"
            >
                <div
                    v-if="enquiries.length"
                    class="grid gap-5"
                >
                    <article
                        v-for="enquiry in enquiries"
                        :key="enquiry.id"
                        class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"
                    >
                        <div
                            class="flex flex-col justify-between gap-5 xl:flex-row xl:items-start"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black uppercase tracking-[0.08em] text-slate-600"
                                    >
                                        {{ enquiry.requestType === 'quote' ? 'Quote request' : 'General enquiry' }}
                                    </span>

                                    <span
                                        :class="[
                                            'rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-[0.08em]',
                                            enquiry.status === 'spam'
                                                ? 'bg-rose-50 text-rose-700'
                                                : enquiry.status === 'new'
                                                    ? 'bg-amber-50 text-amber-700'
                                                    : 'bg-emerald-50 text-emerald-700',
                                        ]"
                                    >
                                        {{ titleCase(enquiry.status) }}
                                    </span>

                                    <span
                                        class="rounded-full bg-sky-50 px-3 py-1 text-[10px] font-black uppercase tracking-[0.08em] text-sky-700"
                                    >
                                        {{ titleCase(enquiry.deliveryStatus) }}
                                    </span>
                                </div>

                                <h2 class="mt-4 text-lg font-black text-slate-900">
                                    {{ enquiry.contactName }}
                                    <span class="font-semibold text-slate-400">
                                        · {{ enquiry.business.name }}
                                    </span>
                                </h2>

                                <p class="mt-1 text-xs font-semibold text-slate-400">
                                    {{ formatDate(enquiry.createdAt) }}
                                    · preferred reply {{ titleCase(enquiry.preferredChannel) }}
                                    · {{ enquiry.business.claimed ? 'claimed listing' : 'unclaimed listing' }}
                                </p>

                                <p
                                    class="mt-4 whitespace-pre-line rounded-2xl bg-slate-50 p-4 text-sm leading-6 text-slate-600"
                                >
                                    {{ enquiry.message }}
                                </p>

                                <div class="mt-4 flex flex-wrap gap-2">
                                    <a
                                        v-if="enquiry.contactEmail"
                                        :href="`mailto:${enquiry.contactEmail}`"
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-3 py-2 text-xs font-black text-slate-700"
                                    >
                                        <Mail class="h-3.5 w-3.5" />
                                        {{ enquiry.contactEmail }}
                                    </a>

                                    <a
                                        v-if="enquiry.contactPhone"
                                        :href="`tel:${enquiry.contactPhone}`"
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-3 py-2 text-xs font-black text-slate-700"
                                    >
                                        <MessageCircle class="h-3.5 w-3.5" />
                                        {{ enquiry.contactPhone }}
                                    </a>

                                    <a
                                        :href="`/business/${enquiry.business.slug}`"
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 px-3 py-2 text-xs font-black text-emerald-700"
                                    >
                                        <Building2 class="h-3.5 w-3.5" />
                                        Open listing
                                    </a>
                                </div>

                                <p
                                    v-if="enquiry.accountName"
                                    class="mt-3 text-[11px] font-semibold text-slate-400"
                                >
                                    Signed-in account: {{ enquiry.accountName }}
                                </p>
                            </div>

                            <div class="shrink-0 xl:w-[260px]">
                                <button
                                    v-if="enquiry.status !== 'spam'"
                                    type="button"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-xs font-black text-rose-700"
                                    @click="spamOpen = spamOpen === enquiry.id ? null : enquiry.id"
                                >
                                    <Trash2 class="h-4 w-4" />
                                    Mark as spam
                                </button>

                                <div
                                    v-if="spamOpen === enquiry.id"
                                    class="mt-3 rounded-2xl border border-rose-200 bg-rose-50/50 p-3"
                                >
                                    <textarea
                                        v-model="spamNotes[enquiry.id]"
                                        rows="3"
                                        maxlength="2000"
                                        class="w-full rounded-xl border border-rose-200 bg-white px-3 py-2.5 text-xs outline-none"
                                        placeholder="Optional admin note"
                                    />

                                    <button
                                        type="button"
                                        class="mt-2 w-full rounded-xl bg-rose-700 px-3 py-2.5 text-xs font-black text-white"
                                        @click="markSpam(enquiry)"
                                    >
                                        Confirm spam
                                    </button>
                                </div>

                                <div
                                    v-if="enquiry.adminNotes"
                                    class="mt-3 rounded-xl bg-slate-50 px-3 py-2.5 text-xs font-semibold leading-5 text-slate-500"
                                >
                                    {{ enquiry.adminNotes }}
                                </div>
                            </div>
                        </div>
                    </article>
                </div>

                <div
                    v-else
                    class="rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center shadow-sm"
                >
                    <Inbox class="mx-auto h-10 w-10 text-slate-300" />
                    <h2 class="mt-4 text-lg font-black">
                        No customer enquiries yet
                    </h2>
                    <p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-500">
                        Real quote requests and enquiries will appear here when visitors submit them.
                    </p>
                </div>
            </section>
        </main>
    </div>
</template>
