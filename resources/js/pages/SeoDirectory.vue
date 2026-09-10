<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    BadgeCheck,
    Building2,
    ChevronLeft,
    ChevronRight,
    MapPin,
    Search,
    Star,
} from 'lucide-vue-next';
import PublicFooter from '@/components/public/PublicFooter.vue';
import PublicHeader from '@/components/public/PublicHeader.vue';

type LinkItem = {
    id?: number;
    name: string;
    slug?: string;
    code?: string;
    businessCount: number;
    url: string;
};

type BusinessCard = {
    name: string;
    slug: string;
    shortDescription: string | null;
    category: string | null;
    location: string | null;
    isVerified: boolean;
    listingLabel: string;
    reviewCount: number;
    ratingAverage: number | null;
    url: string;
};

type Breadcrumb = {
    name: string;
    url: string;
};

defineProps<{
    mode: 'locations' | 'state' | 'state-category' | 'lga-category';
    title: string;
    description: string;
    heading: string;
    subheading: string;
    canonicalUrl: string;
    indexable: boolean;
    total: number;
    breadcrumbs: Breadcrumb[];
    states: LinkItem[];
    categories: LinkItem[];
    lgas: LinkItem[];
    businesses: BusinessCard[];
    pagination: {
        currentPage: number;
        lastPage: number;
        total: number;
        from: number | null;
        to: number | null;
        prevUrl: string | null;
        nextUrl: string | null;
    } | null;
}>();

function countLabel(value: number): string {
    return `${value.toLocaleString('en-NG')} ${value === 1 ? 'business' : 'businesses'}`;
}
</script>

<template>
    <Head :title="title">
        <meta name="description" :content="description" />
        <meta
            name="robots"
            :content="indexable ? 'index,follow' : 'noindex,follow'"
        />
        <link rel="canonical" :href="canonicalUrl" />
        <link rel="icon" type="image/png" href="/brand/favicon-192.png" />
    </Head>

    <div class="min-h-screen bg-[#f8faf9] text-[#062c31]">
        <PublicHeader active="locations" />

        <main>
            <section class="border-b border-slate-200 bg-white">
                <div class="mx-auto max-w-[1450px] px-5 py-9 sm:px-7 lg:px-10 lg:py-12">
                    <nav class="flex flex-wrap items-center gap-2 text-xs font-bold text-slate-400">
                        <template
                            v-for="(item, index) in breadcrumbs"
                            :key="item.url"
                        >
                            <a
                                :href="item.url"
                                class="hover:text-emerald-700"
                            >
                                {{ item.name }}
                            </a>
                            <span v-if="index < breadcrumbs.length - 1">›</span>
                        </template>
                    </nav>

                    <div class="mt-6 flex flex-col justify-between gap-6 lg:flex-row lg:items-end">
                        <div>
                            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-emerald-700">
                                AI-POWERED • LOCAL • TRUSTED
                            </p>
                            <h1 class="mt-3 max-w-4xl text-3xl font-black tracking-[-0.045em] sm:text-5xl">
                                {{ heading }}
                            </h1>
                            <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-500 sm:text-base">
                                {{ subheading }}
                            </p>
                        </div>

                        <div class="rounded-2xl border border-emerald-100 bg-emerald-50 px-5 py-4">
                            <p class="text-2xl font-black text-emerald-800">
                                {{ total.toLocaleString('en-NG') }}
                            </p>
                            <p class="text-xs font-bold text-emerald-700">
                                published {{ total === 1 ? 'business' : 'businesses' }}
                            </p>
                        </div>
                    </div>

                    <form
                        action="/search"
                        method="get"
                        class="mt-7 flex max-w-2xl gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-2"
                    >
                        <label class="flex min-w-0 flex-1 items-center gap-3 px-3">
                            <Search class="h-5 w-5 shrink-0 text-slate-400" />
                            <input
                                name="q"
                                type="search"
                                class="w-full border-0 bg-transparent py-2 text-sm outline-none"
                                placeholder="Search Nigerian businesses..."
                            />
                        </label>
                        <button
                            type="submit"
                            class="rounded-xl bg-emerald-600 px-5 py-3 text-sm font-black text-white"
                        >
                            Search
                        </button>
                    </form>
                </div>
            </section>

            <section
                v-if="states.length"
                class="mx-auto max-w-[1450px] px-5 py-10 sm:px-7 lg:px-10"
            >
                <h2 class="text-2xl font-black">Nigerian locations</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Counts are based only on currently published listings.
                </p>

                <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <a
                        v-for="state in states"
                        :key="state.url"
                        :href="state.url"
                        class="group flex items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white px-5 py-4 transition hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md"
                    >
                        <span class="flex min-w-0 items-center gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700">
                                <MapPin class="h-5 w-5" />
                            </span>
                            <span>
                                <span class="block font-black text-slate-800">
                                    {{ state.name }}
                                </span>
                                <span class="mt-0.5 block text-xs text-slate-400">
                                    {{ countLabel(state.businessCount) }}
                                </span>
                            </span>
                        </span>
                        <span class="font-black text-emerald-600 transition group-hover:translate-x-1">
                            →
                        </span>
                    </a>
                </div>
            </section>

            <section
                v-if="categories.length"
                class="mx-auto max-w-[1450px] px-5 py-10 sm:px-7 lg:px-10"
            >
                <h2 class="text-2xl font-black">
                    Browse categories in this location
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    Only categories attached to real published businesses are shown.
                </p>

                <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <a
                        v-for="category in categories"
                        :key="category.url"
                        :href="category.url"
                        class="rounded-2xl border border-slate-200 bg-white p-5 transition hover:border-emerald-300 hover:shadow-md"
                    >
                        <p class="font-black text-slate-800">{{ category.name }}</p>
                        <p class="mt-1 text-xs font-semibold text-slate-400">
                            {{ countLabel(category.businessCount) }}
                        </p>
                    </a>
                </div>
            </section>

            <section
                v-if="lgas.length"
                class="mx-auto max-w-[1450px] px-5 py-10 sm:px-7 lg:px-10"
            >
                <h2 class="text-2xl font-black">Browse by LGA / Area Council</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Drill into local inventory without generating empty SEO pages.
                </p>

                <div class="mt-6 flex flex-wrap gap-3">
                    <a
                        v-for="lga in lgas"
                        :key="lga.url"
                        :href="lga.url"
                        class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-black text-slate-700 transition hover:border-emerald-300 hover:text-emerald-700"
                    >
                        {{ lga.name }}
                        <span class="ml-1 text-xs text-slate-400">
                            {{ lga.businessCount }}
                        </span>
                    </a>
                </div>
            </section>

            <section
                v-if="mode !== 'locations'"
                class="border-t border-slate-100 bg-slate-50/50"
            >
                <div class="mx-auto max-w-[1450px] px-5 py-10 sm:px-7 lg:px-10">
                    <div
                        v-if="businesses.length"
                        class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3"
                    >
                        <article
                            v-for="business in businesses"
                            :key="business.slug"
                            class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <span
                                        :class="[
                                            'inline-flex items-center gap-1 rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-[0.08em]',
                                            business.isVerified
                                                ? 'bg-emerald-50 text-emerald-700'
                                                : 'bg-slate-100 text-slate-600',
                                        ]"
                                    >
                                        <BadgeCheck
                                            v-if="business.isVerified"
                                            class="h-3 w-3"
                                        />
                                        {{ business.listingLabel }}
                                    </span>

                                    <h2 class="mt-4 text-xl font-black tracking-[-0.025em] text-slate-900">
                                        <a :href="business.url" class="hover:text-emerald-700">
                                            {{ business.name }}
                                        </a>
                                    </h2>
                                </div>

                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700">
                                    <Building2 class="h-5 w-5" />
                                </span>
                            </div>

                            <p
                                v-if="business.category"
                                class="mt-2 text-sm font-bold text-emerald-700"
                            >
                                {{ business.category }}
                            </p>

                            <p
                                v-if="business.location"
                                class="mt-2 flex items-center gap-1.5 text-xs font-semibold text-slate-500"
                            >
                                <MapPin class="h-3.5 w-3.5" />
                                {{ business.location }}
                            </p>

                            <p
                                v-if="business.shortDescription"
                                class="mt-4 line-clamp-3 text-sm leading-6 text-slate-600"
                            >
                                {{ business.shortDescription }}
                            </p>

                            <div
                                v-if="business.reviewCount > 0 && business.ratingAverage !== null"
                                class="mt-4 flex items-center gap-2 text-sm"
                            >
                                <span class="inline-flex items-center gap-1 font-black text-amber-600">
                                    <Star class="h-4 w-4 fill-current" />
                                    {{ business.ratingAverage }}
                                </span>
                                <span class="text-xs text-slate-400">
                                    {{ business.reviewCount }}
                                    {{ business.reviewCount === 1 ? 'review' : 'reviews' }}
                                </span>
                            </div>

                            <a
                                :href="business.url"
                                class="mt-5 inline-flex text-sm font-black text-emerald-700"
                            >
                                View business →
                            </a>
                        </article>
                    </div>

                    <div
                        v-else
                        class="rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center"
                    >
                        <Building2 class="mx-auto h-10 w-10 text-slate-300" />
                        <h2 class="mt-4 text-lg font-black text-slate-700">
                            No published businesses on this page yet
                        </h2>
                        <p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-500">
                            BusinessFinder does not fabricate listings to fill thin location or category pages.
                        </p>
                    </div>

                    <div
                        v-if="pagination && pagination.lastPage > 1"
                        class="mt-8 flex items-center justify-between gap-4"
                    >
                        <a
                            v-if="pagination.prevUrl"
                            :href="pagination.prevUrl"
                            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-black text-slate-700"
                        >
                            <ChevronLeft class="h-4 w-4" />
                            Previous
                        </a>
                        <span v-else />

                        <p class="text-xs font-bold text-slate-400">
                            Page {{ pagination.currentPage }} of {{ pagination.lastPage }}
                        </p>

                        <a
                            v-if="pagination.nextUrl"
                            :href="pagination.nextUrl"
                            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-black text-slate-700"
                        >
                            Next
                            <ChevronRight class="h-4 w-4" />
                        </a>
                        <span v-else />
                    </div>
                </div>
            </section>

            <section
                v-if="! indexable && mode !== 'locations'"
                class="border-t border-amber-100 bg-amber-50/60"
            >
                <div class="mx-auto max-w-[1450px] px-5 py-5 text-xs leading-5 text-amber-800 sm:px-7 lg:px-10">
                    This directory page remains available for users but is marked
                    <strong>noindex</strong> until it has at least 10 legitimate published businesses.
                </div>
            </section>
        </main>

        <PublicFooter />
    </div>
</template>
