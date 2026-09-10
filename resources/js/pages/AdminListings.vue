<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Building2,
    CheckCircle2,
    Clock3,
    FileCheck2,
    ShieldCheck,
    XCircle,
} from 'lucide-vue-next';
import { reactive } from 'vue';
import PublicFooter from '@/components/public/PublicFooter.vue';
import PublicHeader from '@/components/public/PublicHeader.vue';

type Readiness = { ready: boolean; missing: string[] };

type PendingRequest = {
    id: number;
    requestedAt: string | null;
    business: {
        id: number;
        name: string;
        slug: string;
        verificationStatus: string;
        isPublished: boolean;
        category: string | null;
        legalName: string | null;
        cacNumber: string | null;
        phone: string | null;
        whatsappPhone: string | null;
        email: string | null;
        websiteUrl: string | null;
        shortDescription: string | null;
        description: string | null;
        location: string | null;
        serviceAreaOnly: boolean;
    };
    owner: { id: number; name: string; email: string };
    readiness: Readiness;
};

type RecentRequest = {
    id: number;
    status: string;
    reviewNotes: string | null;
    reviewedAt: string | null;
    businessName: string;
    businessSlug: string;
    reviewerName: string | null;
};

defineProps<{
    metrics: {
        pending: number;
        approved: number;
        rejected: number;
        ownerPublished: number;
    };
    pendingRequests: PendingRequest[];
    recentRequests: RecentRequest[];
    status?: string | null;
}>();

const notes = reactive<Record<number, string>>({});

function approve(item: PendingRequest) {
    router.post(
        `/admin/listings/${item.id}/approve`,
        { review_notes: notes[item.id] || null },
        { preserveScroll: true },
    );
}

function reject(item: PendingRequest) {
    const note = (notes[item.id] || '').trim();
    if (! note) return;

    router.post(
        `/admin/listings/${item.id}/reject`,
        { review_notes: note },
        { preserveScroll: true },
    );
}

function formatDate(value: string | null): string {
    if (! value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return new Intl.DateTimeFormat('en-NG', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(date);
}
</script>

<template>
    <Head title="Listing Publication Review" />

    <div class="min-h-screen bg-[#f5f7f6] text-[#062c31]">
        <PublicHeader />

        <main class="mx-auto max-w-[1360px] px-5 py-8 sm:px-7 lg:px-10 lg:py-12">
            <div class="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-emerald-700">
                        Administrator
                    </p>
                    <h1 class="mt-2 text-3xl font-black tracking-[-0.04em] sm:text-4xl">
                        Listing publication review
                    </h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                        Review owner-submitted listings before publication. Approval publishes
                        the listing only; it never grants BusinessFinder verification.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Link href="/admin/claims" class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-black text-slate-700">
                        Claims
                    </Link>
                    <Link href="/admin/engagement" class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-black text-slate-700">
                        Engagement
                    </Link>
                    <Link href="/dashboard" class="rounded-xl bg-[#062c31] px-4 py-3 text-sm font-black text-white">
                        Dashboard
                    </Link>
                </div>
            </div>

            <div
                v-if="status"
                class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-900"
            >
                {{ status }}
            </div>

            <section class="mt-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <Clock3 class="h-5 w-5 text-amber-600" />
                    <p class="mt-4 text-3xl font-black">{{ metrics.pending }}</p>
                    <p class="mt-1 text-xs font-bold text-slate-500">Pending publication reviews</p>
                </article>
                <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <CheckCircle2 class="h-5 w-5 text-emerald-600" />
                    <p class="mt-4 text-3xl font-black">{{ metrics.approved }}</p>
                    <p class="mt-1 text-xs font-bold text-slate-500">Approved requests</p>
                </article>
                <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <XCircle class="h-5 w-5 text-rose-600" />
                    <p class="mt-4 text-3xl font-black">{{ metrics.rejected }}</p>
                    <p class="mt-1 text-xs font-bold text-slate-500">Rejected requests</p>
                </article>
                <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <Building2 class="h-5 w-5 text-sky-600" />
                    <p class="mt-4 text-3xl font-black">{{ metrics.ownerPublished }}</p>
                    <p class="mt-1 text-xs font-bold text-slate-500">Published owner-connected listings</p>
                </article>
            </section>

            <section class="mt-7 rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-5">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-emerald-700">
                        Queue
                    </p>
                    <h2 class="mt-1 text-xl font-black">Pending requests</h2>
                </div>

                <div v-if="pendingRequests.length" class="divide-y divide-slate-100">
                    <article
                        v-for="item in pendingRequests"
                        :key="item.id"
                        class="p-6"
                    >
                        <div class="grid gap-6 lg:grid-cols-[1fr_360px]">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full bg-amber-50 px-3 py-1 text-[10px] font-black uppercase tracking-[0.08em] text-amber-700">
                                        Pending
                                    </span>
                                    <span class="text-xs font-semibold text-slate-400">
                                        {{ formatDate(item.requestedAt) }}
                                    </span>
                                </div>

                                <h3 class="mt-4 text-xl font-black text-slate-900">
                                    {{ item.business.name }}
                                </h3>
                                <p class="mt-1 text-sm text-slate-500">
                                    Owner: {{ item.owner.name }} · {{ item.owner.email }}
                                </p>

                                <div
                                    :class="[
                                        'mt-4 rounded-2xl border p-4 text-sm',
                                        item.readiness.ready
                                            ? 'border-emerald-200 bg-emerald-50'
                                            : 'border-rose-200 bg-rose-50',
                                    ]"
                                >
                                    <div class="flex items-center gap-2 font-black">
                                        <FileCheck2 class="h-4 w-4" />
                                        {{ item.readiness.ready ? 'Publication-ready' : 'Readiness failed' }}
                                    </div>
                                    <p
                                        v-if="! item.readiness.ready"
                                        class="mt-2 text-xs leading-5"
                                    >
                                        Missing: {{ item.readiness.missing.join(', ') }}
                                    </p>
                                </div>

                                <dl class="mt-4 grid gap-2 rounded-2xl bg-slate-50 p-4 text-xs sm:grid-cols-2">
                                    <div>
                                        <dt class="font-bold text-slate-400">Category</dt>
                                        <dd class="mt-0.5 font-black text-slate-700">
                                            {{ item.business.category || 'Not set' }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="font-bold text-slate-400">Location</dt>
                                        <dd class="mt-0.5 font-black text-slate-700">
                                            {{ item.business.location || (item.business.serviceAreaOnly ? 'Service area' : 'Not set') }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="font-bold text-slate-400">Phone</dt>
                                        <dd class="mt-0.5 font-black text-slate-700">
                                            {{ item.business.phone || item.business.whatsappPhone || 'Not set' }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="font-bold text-slate-400">Email / website</dt>
                                        <dd class="mt-0.5 break-words font-black text-slate-700">
                                            {{ item.business.email || item.business.websiteUrl || 'Not set' }}
                                        </dd>
                                    </div>
                                </dl>

                                <p
                                    v-if="item.business.shortDescription || item.business.description"
                                    class="mt-4 whitespace-pre-line rounded-2xl border border-slate-100 bg-white p-4 text-sm leading-6 text-slate-600"
                                >
                                    {{ item.business.shortDescription || item.business.description }}
                                </p>
                            </div>

                            <div>
                                <label class="grid gap-2 text-sm font-bold text-slate-700">
                                    Review note
                                    <textarea
                                        v-model="notes[item.id]"
                                        rows="5"
                                        class="rounded-xl border border-slate-200 px-4 py-3 text-sm leading-6 outline-none focus:border-emerald-500"
                                        placeholder="Optional for approval; required for rejection."
                                    />
                                </label>

                                <div class="mt-4 grid grid-cols-2 gap-3">
                                    <button
                                        type="button"
                                        :disabled="! item.readiness.ready"
                                        class="rounded-xl bg-emerald-600 px-4 py-3 text-sm font-black text-white disabled:cursor-not-allowed disabled:opacity-40"
                                        @click="approve(item)"
                                    >
                                        Approve
                                    </button>

                                    <button
                                        type="button"
                                        :disabled="! (notes[item.id] || '').trim()"
                                        class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-black text-rose-700 disabled:cursor-not-allowed disabled:opacity-40"
                                        @click="reject(item)"
                                    >
                                        Reject
                                    </button>
                                </div>

                                <p class="mt-3 flex gap-2 text-xs leading-5 text-slate-500">
                                    <ShieldCheck class="mt-0.5 h-4 w-4 shrink-0" />
                                    Publication approval never changes verification status.
                                </p>
                            </div>
                        </div>
                    </article>
                </div>

                <div v-else class="px-6 py-12 text-center">
                    <CheckCircle2 class="mx-auto h-9 w-9 text-emerald-300" />
                    <h3 class="mt-3 font-black text-slate-700">
                        Publication queue is clear
                    </h3>
                    <p class="mt-2 text-sm text-slate-500">
                        No owner listing is waiting for publication review.
                    </p>
                </div>
            </section>

            <section class="mt-7 rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-5">
                    <h2 class="text-xl font-black">Recent decisions</h2>
                </div>

                <div v-if="recentRequests.length" class="divide-y divide-slate-100">
                    <article
                        v-for="item in recentRequests"
                        :key="item.id"
                        class="flex flex-col justify-between gap-3 px-6 py-4 sm:flex-row sm:items-center"
                    >
                        <div>
                            <p class="font-black text-slate-800">{{ item.businessName }}</p>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ item.reviewNotes || 'No review note.' }}
                            </p>
                        </div>
                        <div class="text-right">
                            <span
                                :class="[
                                    'rounded-full px-3 py-1 text-[10px] font-black uppercase',
                                    item.status === 'approved'
                                        ? 'bg-emerald-50 text-emerald-700'
                                        : 'bg-rose-50 text-rose-700',
                                ]"
                            >
                                {{ item.status }}
                            </span>
                            <p class="mt-2 text-[11px] text-slate-400">
                                {{ formatDate(item.reviewedAt) }}
                            </p>
                        </div>
                    </article>
                </div>

                <div v-else class="px-6 py-10 text-center text-sm text-slate-500">
                    No publication decisions yet.
                </div>
            </section>
        </main>

        <PublicFooter />
    </div>
</template>
