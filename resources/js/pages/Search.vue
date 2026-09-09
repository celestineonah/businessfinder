<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    BadgeCheck,
    Building2,
    ChevronLeft,
    ChevronRight,
    MapPin,
    Search as SearchIcon,
    Sparkles,
} from 'lucide-vue-next';
import PublicFooter from '@/components/public/PublicFooter.vue';
import PublicHeader from '@/components/public/PublicHeader.vue';
import heroImage from '../../images/lekki-ikoyi-link-bridge.jpg';

type StateOption = {
    name: string;
    slug: string;
    code: string;
};

type BusinessResult = {
    name: string;
    slug: string;
    shortDescription: string | null;
    category: string | null;
    location: string | null;
    state: string | null;
    lga: string | null;
    claimStatus: string;
    verificationStatus: string;
    isVerified: boolean;
    listingLabel: string;
};

type Pagination = {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
    from: number | null;
    to: number | null;
    prevUrl: string | null;
    nextUrl: string | null;
};

const props = defineProps<{
    query: string;
    location: string;
    locationLabel: string | null;
    states: StateOption[];
    businessCount: number;
    results: BusinessResult[];
    pagination: Pagination;
}>();
</script>

<template>
    <Head title="Find Nigerian Businesses">
        <meta
            name="description"
            content="Search real Nigerian businesses and services by name, category and location."
        />
        <link rel="icon" type="image/png" href="/brand/favicon-192.png" />
    </Head>

    <div class="min-h-screen bg-white text-[#072c33]">
        <PublicHeader active="businesses" />

        <main>
            <section class="relative isolate overflow-hidden border-b border-slate-200">
                <div
                    class="absolute inset-0 -z-20 bg-cover bg-center"
                    :style="{ backgroundImage: `url(${heroImage})` }"
                />
                <div class="absolute inset-0 -z-10 bg-gradient-to-r from-white via-white/92 to-sky-100/35" />

                <div class="mx-auto max-w-[1500px] px-5 py-9 sm:px-7 lg:px-10">
                    <div class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-white/90 px-3 py-1.5 text-[11px] font-black uppercase tracking-[0.16em] text-emerald-700">
                        <Sparkles class="h-3.5 w-3.5" />
                        AI-powered discovery coming soon
                    </div>

                    <h1 class="mt-4 text-4xl font-black tracking-[-0.04em] text-[#062c31] sm:text-5xl">
                        Find Nigerian Businesses, Smarter
                    </h1>
                    <p class="mt-2 text-base font-medium text-slate-700">
                        Search real business listings across Nigeria by name, service, category or location.
                    </p>

                    <form
                        action="/search"
                        method="get"
                        class="mt-6 grid max-w-[1050px] gap-2 rounded-2xl bg-white p-2.5 shadow-xl md:grid-cols-[1.25fr_0.85fr_auto]"
                    >
                        <label class="flex min-h-[60px] items-center gap-3 px-4">
                            <SearchIcon class="h-6 w-6 shrink-0 text-slate-800" />
                            <input
                                name="q"
                                type="search"
                                :value="query"
                                placeholder="Business, service, category or LGA"
                                class="w-full border-0 bg-transparent text-sm font-semibold text-slate-800 outline-none placeholder:text-slate-400"
                            />
                        </label>

                        <label class="flex min-h-[60px] items-center gap-3 border-t border-slate-200 px-4 md:border-l md:border-t-0">
                            <MapPin class="h-6 w-6 shrink-0 text-slate-800" />
                            <select
                                name="location"
                                class="w-full cursor-pointer border-0 bg-transparent text-sm font-semibold text-slate-700 outline-none"
                            >
                                <option value="" :selected="location === ''">All Nigeria</option>
                                <option
                                    v-for="state in states"
                                    :key="state.code"
                                    :value="state.slug"
                                    :selected="state.slug === location"
                                >
                                    {{ state.name }}
                                </option>
                            </select>
                        </label>

                        <button
                            type="submit"
                            class="min-h-[58px] rounded-xl bg-emerald-600 px-10 text-sm font-black text-white transition hover:bg-emerald-700"
                        >
                            Search
                        </button>
                    </form>
                </div>
            </section>

            <section class="bg-[#fbfcfc] py-8">
                <div class="mx-auto grid max-w-[1500px] gap-7 px-5 sm:px-7 lg:grid-cols-[280px_1fr] lg:px-10">
                    <aside class="h-fit rounded-2xl border border-slate-200 bg-white p-5">
                        <div class="flex items-center justify-between border-b border-slate-200 pb-4">
                            <h2 class="text-lg font-black text-slate-900">Search Summary</h2>
                            <a href="/search" class="text-xs font-bold text-emerald-700">Reset</a>
                        </div>

                        <div class="py-5">
                            <div class="text-3xl font-black text-emerald-700">{{ businessCount }}</div>
                            <p class="mt-1 text-xs font-semibold text-slate-500">Public business listings</p>
                        </div>

                        <div class="border-t border-slate-200 py-5">
                            <h3 class="text-sm font-black text-slate-900">Location</h3>
                            <div
                                v-if="locationLabel"
                                class="mt-3 flex items-center gap-2 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800"
                            >
                                <MapPin class="h-4 w-4" />
                                {{ locationLabel }}
                            </div>
                            <div v-else class="mt-3 text-sm text-slate-500">All Nigerian locations</div>
                        </div>

                        <div class="border-t border-slate-200 py-5">
                            <h3 class="text-sm font-black text-slate-900">Trust labels</h3>
                            <p class="mt-2 text-xs leading-5 text-slate-500">
                                Imported records remain Listed Business until genuine verification evidence succeeds.
                            </p>
                        </div>
                    </aside>

                    <div>
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <p class="text-sm font-semibold text-slate-600">
                                    <template v-if="query">
                                        Results for <strong class="text-slate-900">“{{ query }}”</strong>
                                    </template>
                                    <template v-else>Browse businesses</template>
                                    <template v-if="locationLabel">
                                        in <strong class="text-slate-900">{{ locationLabel }}</strong>
                                    </template>
                                </p>
                                <p v-if="pagination.total > 0" class="mt-1 text-xs text-slate-400">
                                    Showing {{ pagination.from }}–{{ pagination.to }} of {{ pagination.total }} matching businesses
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="results.length > 0"
                            class="mt-6 grid gap-5 md:grid-cols-2 xl:grid-cols-3"
                        >
                            <article
                                v-for="business in results"
                                :key="business.slug"
                                class="flex min-h-[300px] flex-col rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-50">
                                        <Building2 class="h-6 w-6 text-emerald-700" />
                                    </div>

                                    <span
                                        v-if="business.isVerified"
                                        class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide text-emerald-700"
                                    >
                                        <BadgeCheck class="h-3.5 w-3.5" />
                                        Verified
                                    </span>
                                    <span
                                        v-else
                                        class="rounded-full bg-slate-100 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide text-slate-600"
                                    >
                                        Listed Business
                                    </span>
                                </div>

                                <h2 class="mt-5 text-xl font-black tracking-[-0.025em] text-slate-900">
                                    {{ business.name }}
                                </h2>

                                <p v-if="business.category" class="mt-2 text-xs font-black uppercase tracking-wide text-emerald-700">
                                    {{ business.category }}
                                </p>

                                <p v-if="business.location" class="mt-3 flex items-start gap-2 text-sm leading-6 text-slate-500">
                                    <MapPin class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
                                    {{ business.location }}
                                </p>

                                <p class="mt-4 line-clamp-3 text-sm leading-6 text-slate-500">
                                    {{ business.shortDescription || 'View this listing for available business and contact information.' }}
                                </p>

                                <div class="mt-auto flex items-center justify-between gap-3 border-t border-slate-100 pt-5">
                                    <span class="text-xs font-bold text-slate-400">
                                        {{ business.claimStatus === 'claimed' ? 'Claimed' : business.claimStatus === 'pending' ? 'Claim under review' : 'Unclaimed' }}
                                    </span>
                                    <a
                                        :href="`/business/${business.slug}`"
                                        class="rounded-xl bg-[#062c31] px-4 py-2.5 text-xs font-black text-white transition hover:bg-emerald-700"
                                    >
                                        View Business
                                    </a>
                                </div>
                            </article>
                        </div>

                        <div
                            v-else-if="businessCount === 0"
                            class="mt-6 rounded-2xl border border-slate-200 bg-white px-6 py-16 text-center shadow-sm"
                        >
                            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-50">
                                <SearchIcon class="h-8 w-8 text-emerald-700" />
                            </div>
                            <h2 class="mt-6 text-2xl font-black text-slate-900">Real business listings are being prepared</h2>
                            <p class="mx-auto mt-3 max-w-2xl text-sm leading-7 text-slate-500">
                                BusinessFinder Nigeria publishes real sourced businesses only. No fabricated listings, ratings, reviews or verification badges are created.
                            </p>
                        </div>

                        <div
                            v-else
                            class="mt-6 rounded-2xl border border-slate-200 bg-white px-6 py-16 text-center shadow-sm"
                        >
                            <SearchIcon class="mx-auto h-10 w-10 text-slate-300" />
                            <h2 class="mt-5 text-xl font-black text-slate-900">No matching businesses found</h2>
                            <p class="mt-2 text-sm text-slate-500">Try another business name, service, category, LGA or state.</p>
                            <a href="/search" class="mt-6 inline-flex rounded-xl bg-emerald-600 px-5 py-3 text-sm font-black text-white">Browse all businesses</a>
                        </div>

                        <div
                            v-if="pagination.lastPage > 1"
                            class="mt-7 flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-4"
                        >
                            <a
                                v-if="pagination.prevUrl"
                                :href="pagination.prevUrl"
                                class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-black text-slate-700"
                            >
                                <ChevronLeft class="h-4 w-4" /> Previous
                            </a>
                            <span v-else />

                            <span class="text-xs font-bold text-slate-500">
                                Page {{ pagination.currentPage }} of {{ pagination.lastPage }}
                            </span>

                            <a
                                v-if="pagination.nextUrl"
                                :href="pagination.nextUrl"
                                class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-black text-slate-700"
                            >
                                Next <ChevronRight class="h-4 w-4" />
                            </a>
                            <span v-else />
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <PublicFooter />
    </div>
</template>
