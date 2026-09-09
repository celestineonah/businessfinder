<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PublicFooter from '@/components/public/PublicFooter.vue';
import PublicHeader from '@/components/public/PublicHeader.vue';
import heroImage from '../../images/lekki-ikoyi-link-bridge.jpg';

type StateOption = {
    name: string;
    slug: string;
    code: string;
};

defineProps<{
    query: string;
    location: string;
    locationLabel: string | null;
    states: StateOption[];
    businessCount: number;
    results: unknown[];
}>();
</script>

<template>
    <Head title="Find Nigerian Businesses">
        <meta
            name="description"
            content="Search Nigerian businesses and services by category and location."
        />
        <link
            rel="icon"
            type="image/svg+xml"
            href="/favicon.svg"
        />
    </Head>

    <div class="min-h-screen bg-white text-[#072c33]">
        <PublicHeader active="businesses" />

        <main>
            <!-- Search hero -->
            <section
                class="relative isolate overflow-hidden border-b border-slate-200"
            >
                <div
                    class="absolute inset-0 -z-20 bg-cover bg-center"
                    :style="{
                        backgroundImage: `url(${heroImage})`,
                    }"
                />

                <div
                    class="absolute inset-0 -z-10 bg-gradient-to-r from-white via-white/90 to-sky-100/35"
                />

                <div
                    class="mx-auto max-w-[1500px] px-5 py-9 sm:px-7 lg:px-10"
                >
                    <nav
                        class="text-xs font-semibold text-slate-600"
                    >
                        <a
                            href="/"
                            class="hover:text-emerald-700"
                        >
                            Home
                        </a>
                        <span class="mx-2">›</span>
                        <span>Businesses</span>
                        <span class="mx-2">›</span>
                        <span>Search Results</span>
                    </nav>

                    <h1
                        class="mt-4 text-4xl font-black tracking-[-0.04em] text-[#062c31] sm:text-5xl"
                    >
                        Find Nigerian Businesses, Easily
                    </h1>

                    <p
                        class="mt-2 text-base font-medium text-slate-700"
                    >
                        Discover businesses and services across Nigeria.
                    </p>

                    <form
                        action="/search"
                        method="get"
                        class="mt-6 grid max-w-[1050px] gap-2 rounded-2xl bg-white p-2.5 shadow-xl md:grid-cols-[1.25fr_0.85fr_auto]"
                    >
                        <label
                            class="flex min-h-[60px] items-center gap-3 px-4"
                        >
                            <svg
                                class="h-6 w-6 shrink-0 text-slate-800"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <circle cx="11" cy="11" r="7" />
                                <path d="m20 20-3.5-3.5" />
                            </svg>

                            <input
                                name="q"
                                type="search"
                                :value="query"
                                placeholder="What are you looking for?"
                                class="w-full border-0 bg-transparent text-sm font-semibold text-slate-800 outline-none placeholder:text-slate-400"
                            />
                        </label>

                        <label
                            class="flex min-h-[60px] items-center gap-3 border-t border-slate-200 px-4 md:border-l md:border-t-0"
                        >
                            <svg
                                class="h-6 w-6 shrink-0 text-slate-800"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"
                                />
                                <circle cx="12" cy="10" r="2.5" />
                            </svg>

                            <select
                                name="location"
                                class="w-full cursor-pointer border-0 bg-transparent text-sm font-semibold text-slate-700 outline-none"
                            >
                                <option
                                    value=""
                                    :selected="location === ''"
                                >
                                    All Nigeria
                                </option>

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

            <!-- Results -->
            <section class="bg-[#fbfcfc] py-8">
                <div
                    class="mx-auto grid max-w-[1500px] gap-7 px-5 sm:px-7 lg:grid-cols-[280px_1fr] lg:px-10"
                >
                    <!-- Filters -->
                    <aside
                        class="h-fit rounded-2xl border border-slate-200 bg-white p-5"
                    >
                        <div
                            class="flex items-center justify-between border-b border-slate-200 pb-4"
                        >
                            <h2
                                class="text-lg font-black text-slate-900"
                            >
                                Filter Results
                            </h2>

                            <a
                                href="/search"
                                class="text-xs font-bold text-emerald-700"
                            >
                                Reset All
                            </a>
                        </div>

                        <div class="py-5">
                            <h3
                                class="text-sm font-black text-slate-900"
                            >
                                Category
                            </h3>

                            <div
                                class="mt-3 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-500"
                            >
                                Category filtering activates as real
                                taxonomy inventory is published.
                            </div>
                        </div>

                        <div
                            class="border-t border-slate-200 py-5"
                        >
                            <h3
                                class="text-sm font-black text-slate-900"
                            >
                                Location
                            </h3>

                            <div
                                v-if="locationLabel"
                                class="mt-3 flex items-center gap-2 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800"
                            >
                                <span>⌖</span>
                                {{ locationLabel }}
                            </div>

                            <div
                                v-else
                                class="mt-3 text-sm text-slate-500"
                            >
                                All Nigerian locations
                            </div>
                        </div>

                        <div
                            class="border-t border-slate-200 py-5"
                        >
                            <h3
                                class="text-sm font-black text-slate-900"
                            >
                                Verification
                            </h3>

                            <p
                                class="mt-2 text-xs leading-5 text-slate-500"
                            >
                                Verified-only filtering will become
                                available when verified businesses are
                                published.
                            </p>
                        </div>
                    </aside>

                    <!-- Main -->
                    <div>
                        <div
                            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <p
                                    class="text-sm font-semibold text-slate-600"
                                >
                                    <template v-if="query">
                                        Results for
                                        <strong class="text-slate-900">
                                            “{{ query }}”
                                        </strong>
                                    </template>

                                    <template v-else>
                                        Browse businesses
                                    </template>

                                    <template v-if="locationLabel">
                                        in
                                        <strong class="text-slate-900">
                                            {{ locationLabel }}
                                        </strong>
                                    </template>
                                </p>
                            </div>

                            <div
                                class="flex items-center gap-2"
                            >
                                <button
                                    type="button"
                                    class="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-black text-white"
                                >
                                    ▦ Grid
                                </button>

                                <button
                                    type="button"
                                    class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-xs font-black text-slate-600"
                                >
                                    ☷ List
                                </button>
                            </div>
                        </div>

                        <!-- Honest empty state -->
                        <div
                            v-if="businessCount === 0"
                            class="mt-6 rounded-2xl border border-slate-200 bg-white px-6 py-16 text-center shadow-sm"
                        >
                            <div
                                class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-50 text-3xl"
                            >
                                🔎
                            </div>

                            <h2
                                class="mt-6 text-2xl font-black text-slate-900"
                            >
                                Real business listings are being prepared
                            </h2>

                            <p
                                class="mx-auto mt-3 max-w-2xl text-sm leading-7 text-slate-500"
                            >
                                BusinessFinder Nigeria is validating
                                real businesses before publishing them
                                in search. We do not create fake
                                listings, ratings, reviews, phone
                                numbers or verification badges.
                            </p>

                            <div
                                class="mx-auto mt-7 grid max-w-2xl gap-3 sm:grid-cols-3"
                            >
                                <div
                                    class="rounded-xl bg-slate-50 p-4"
                                >
                                    <div
                                        class="text-xl font-black text-emerald-700"
                                    >
                                        37
                                    </div>
                                    <div
                                        class="mt-1 text-xs font-semibold text-slate-500"
                                    >
                                        States & FCT
                                    </div>
                                </div>

                                <div
                                    class="rounded-xl bg-slate-50 p-4"
                                >
                                    <div
                                        class="text-xl font-black text-emerald-700"
                                    >
                                        774
                                    </div>
                                    <div
                                        class="mt-1 text-xs font-semibold text-slate-500"
                                    >
                                        LGAs & Area Councils
                                    </div>
                                </div>

                                <div
                                    class="rounded-xl bg-slate-50 p-4"
                                >
                                    <div
                                        class="text-xl font-black text-emerald-700"
                                    >
                                        0
                                    </div>
                                    <div
                                        class="mt-1 text-xs font-semibold text-slate-500"
                                    >
                                        Fabricated Listings
                                    </div>
                                </div>
                            </div>

                            <a
                                href="/"
                                class="mt-8 inline-flex rounded-xl bg-emerald-600 px-6 py-3 text-sm font-black text-white hover:bg-emerald-700"
                            >
                                Back to Homepage
                            </a>
                        </div>

                        <div
                            v-else
                            class="mt-6 rounded-2xl border border-slate-200 bg-white p-8"
                        >
                            <p class="text-slate-600">
                                {{ businessCount }} business records are
                                currently being prepared for public
                                search presentation.
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <PublicFooter />
    </div>
</template>
