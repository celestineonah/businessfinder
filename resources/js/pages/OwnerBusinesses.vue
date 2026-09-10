<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Building2,
    CircleCheck,
    ExternalLink,
    MapPin,
    Plus,
    ShieldCheck,
} from 'lucide-vue-next';
import PublicFooter from '@/components/public/PublicFooter.vue';
import PublicHeader from '@/components/public/PublicHeader.vue';

type Readiness = {
    ready: boolean;
    missing: string[];
};

type PublicationRequest = {
    status: string;
    requestedAt: string | null;
    reviewNotes: string | null;
};

type OwnerBusiness = {
    id: number;
    name: string;
    slug: string;
    category: string | null;
    location: string | null;
    claimStatus: string;
    verificationStatus: string;
    listingStatus: string;
    isPublished: boolean;
    updatedAt: string | null;
    publicUrl: string | null;
    manageUrl: string;
    readiness: Readiness;
    publicationRequest: PublicationRequest | null;
};

defineProps<{
    businesses: OwnerBusiness[];
    status?: string | null;
}>();

function label(value: string): string {
    return value
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (character) => character.toUpperCase());
}

function requestClass(status: string): string {
    if (status === 'approved') {
        return 'bg-emerald-50 text-emerald-700';
    }

    if (status === 'rejected') {
        return 'bg-rose-50 text-rose-700';
    }

    return 'bg-amber-50 text-amber-700';
}
</script>

<template>
    <Head title="Manage Businesses" />

    <div class="min-h-screen bg-[#f6f8f7] text-[#062c31]">
        <PublicHeader active="businesses" />

        <main class="mx-auto max-w-[1320px] px-5 py-8 sm:px-7 lg:px-10 lg:py-12">
            <div
                v-if="status"
                class="mb-6 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-900"
            >
                <CircleCheck class="mt-0.5 h-5 w-5 shrink-0" />
                {{ status }}
            </div>

            <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-emerald-700">
                        Owner workspace
                    </p>
                    <h1 class="mt-2 text-3xl font-black tracking-[-0.04em] sm:text-4xl">
                        Manage your businesses
                    </h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                        Keep genuine business details current, check publication readiness,
                        and request review when a draft is ready to go live.
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <Link
                        href="/dashboard"
                        class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-black text-slate-700"
                    >
                        Dashboard
                    </Link>
                    <Link
                        href="/add-business"
                        class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-3 text-sm font-black text-white"
                    >
                        <Plus class="h-4 w-4" />
                        Add business
                    </Link>
                </div>
            </div>

            <section
                v-if="businesses.length"
                class="mt-8 grid gap-5 lg:grid-cols-2"
            >
                <article
                    v-for="business in businesses"
                    :key="business.id"
                    class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    :class="[
                                        'rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-[0.08em]',
                                        business.isPublished
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : 'bg-slate-100 text-slate-600',
                                    ]"
                                >
                                    {{ business.isPublished ? 'Public' : 'Draft' }}
                                </span>

                                <span
                                    v-if="business.verificationStatus === 'verified'"
                                    class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-3 py-1 text-[10px] font-black uppercase tracking-[0.08em] text-emerald-700"
                                >
                                    <ShieldCheck class="h-3 w-3" />
                                    Verified
                                </span>

                                <span
                                    v-if="business.publicationRequest"
                                    :class="[
                                        'rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-[0.08em]',
                                        requestClass(business.publicationRequest.status),
                                    ]"
                                >
                                    Review {{ label(business.publicationRequest.status) }}
                                </span>
                            </div>

                            <h2 class="mt-4 text-xl font-black tracking-[-0.025em] text-slate-900">
                                {{ business.name }}
                            </h2>

                            <p class="mt-1 text-sm font-semibold text-slate-500">
                                {{ business.category || 'Category not set' }}
                            </p>

                            <p
                                v-if="business.location"
                                class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500"
                            >
                                <MapPin class="h-3.5 w-3.5" />
                                {{ business.location }}
                            </p>
                        </div>

                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700">
                            <Building2 class="h-6 w-6" />
                        </div>
                    </div>

                    <div
                        :class="[
                            'mt-5 rounded-2xl border p-4',
                            business.readiness.ready
                                ? 'border-emerald-200 bg-emerald-50/60'
                                : 'border-amber-200 bg-amber-50/60',
                        ]"
                    >
                        <p class="text-xs font-black uppercase tracking-[0.1em]">
                            {{
                                business.readiness.ready
                                    ? 'Publication-ready'
                                    : 'Publication checklist'
                            }}
                        </p>

                        <p
                            v-if="! business.readiness.ready"
                            class="mt-2 text-xs leading-5 text-slate-600"
                        >
                            Missing: {{ business.readiness.missing.join(', ') }}
                        </p>

                        <p
                            v-else
                            class="mt-2 text-xs leading-5 text-emerald-800"
                        >
                            Required profile information is present. Publication still requires
                            BusinessFinder review and does not automatically grant verification.
                        </p>
                    </div>

                    <p
                        v-if="business.publicationRequest?.reviewNotes"
                        class="mt-4 rounded-xl bg-slate-50 px-4 py-3 text-xs leading-5 text-slate-600"
                    >
                        Review note: {{ business.publicationRequest.reviewNotes }}
                    </p>

                    <div class="mt-5 flex flex-wrap gap-3">
                        <Link
                            :href="business.manageUrl"
                            class="rounded-xl bg-[#062c31] px-4 py-3 text-sm font-black text-white"
                        >
                            Manage profile
                        </Link>

                        <a
                            v-if="business.publicUrl"
                            :href="business.publicUrl"
                            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-3 text-sm font-black text-slate-700"
                        >
                            <ExternalLink class="h-4 w-4" />
                            View listing
                        </a>
                    </div>
                </article>
            </section>

            <section
                v-else
                class="mt-8 rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center"
            >
                <Building2 class="mx-auto h-10 w-10 text-slate-300" />
                <h2 class="mt-4 text-xl font-black">
                    No owned businesses yet
                </h2>
                <p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-500">
                    Add a genuine business you own or claim an existing published listing.
                    BusinessFinder does not seed owner accounts with sample businesses.
                </p>
                <Link
                    href="/add-business"
                    class="mt-5 inline-flex rounded-xl bg-emerald-600 px-5 py-3 text-sm font-black text-white"
                >
                    Add your business
                </Link>
            </section>
        </main>

        <PublicFooter />
    </div>
</template>
