<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PublicFooter from '@/components/public/PublicFooter.vue';
import PublicHeader from '@/components/public/PublicHeader.vue';
import { register } from '@/routes';
import heroImage from '../../images/lekki-ikoyi-link-bridge.jpg';

type StateOption = {
    name: string;
    slug: string;
    code: string;
};

defineProps<{
    states: StateOption[];
    businessCount: number;
}>();

const categories = [
    {
        name: 'Food & Drinks',
        query: 'food drinks',
        icon: '🍴',
        box: 'bg-emerald-50',
    },
    {
        name: 'Retail & Shopping',
        query: 'retail shopping',
        icon: '🛒',
        box: 'bg-amber-50',
    },
    {
        name: 'Home & Living',
        query: 'home services',
        icon: '⌂',
        box: 'bg-rose-50',
    },
    {
        name: 'Health & Medical',
        query: 'health medical',
        icon: '♡',
        box: 'bg-blue-50',
    },
    {
        name: 'Education',
        query: 'education',
        icon: '🎓',
        box: 'bg-violet-50',
    },
    {
        name: 'Automotive',
        query: 'automotive',
        icon: '🔧',
        box: 'bg-emerald-50',
    },
    {
        name: 'Professional Services',
        query: 'professional services',
        icon: '▥',
        box: 'bg-amber-50',
    },
    {
        name: 'Travel & Tourism',
        query: 'travel tourism',
        icon: '✈',
        box: 'bg-cyan-50',
    },
    {
        name: 'Tech & Electronics',
        query: 'technology electronics',
        icon: '▱',
        box: 'bg-violet-50',
    },
];
</script>

<template>
    <Head title="Find Nigerian Businesses, Easily">
        <meta
            name="description"
            content="Discover businesses and services across Nigeria with BusinessFinder Nigeria."
        />
        <link
            rel="icon"
            type="image/svg+xml"
            href="/favicon.svg"
        />
    </Head>

    <div class="min-h-screen bg-white text-[#072c33]">
        <PublicHeader active="home" />

        <main>
            <!-- Approved hero -->
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
                    class="absolute inset-0 -z-10 bg-gradient-to-r from-white via-white/90 to-white/10"
                />

                <div
                    class="absolute inset-0 -z-10 bg-gradient-to-t from-slate-100/60 via-transparent to-sky-100/20"
                />

                <div
                    class="mx-auto flex min-h-[500px] max-w-[1500px] flex-col justify-between px-5 py-10 sm:px-7 lg:px-10 lg:py-12"
                >
                    <div class="grid lg:grid-cols-12">
                        <div
                            class="max-w-3xl lg:col-span-7"
                        >
                            <p
                                class="text-xs font-black uppercase tracking-[0.28em] text-slate-600 sm:text-sm"
                            >
                                Discover
                                <span class="px-2">•</span>
                                Connect
                                <span class="px-2">•</span>
                                Grow
                            </p>

                            <h1
                                class="mt-5 max-w-3xl text-[44px] font-black leading-[0.98] tracking-[-0.055em] text-[#052d33] sm:text-6xl lg:text-[70px]"
                            >
                                Find Nigerian
                                <br />
                                Businesses, Easily
                            </h1>

                            <p
                                class="mt-5 max-w-2xl text-base font-medium leading-7 text-slate-700 sm:text-xl"
                            >
                                Your trusted directory for local
                                businesses across Nigeria.
                                Search, discover and connect with
                                businesses in your area.
                            </p>
                        </div>

                        <div
                            class="hidden justify-end pt-4 lg:col-span-5 lg:flex"
                        >
                            <p
                                class="max-w-[290px] rotate-[-5deg] text-right text-2xl font-semibold italic leading-tight text-emerald-900"
                            >
                                Supporting
                                <br />
                                Nigerian Businesses
                                <br />
                                Stronger Together.
                                <span
                                    class="mt-2 block h-1 w-32 rounded-full bg-emerald-500"
                                />
                            </p>
                        </div>
                    </div>

                    <!-- Search bar -->
                    <form
                        action="/search"
                        method="get"
                        class="mt-10 grid max-w-[1000px] gap-2 rounded-2xl bg-white p-2.5 shadow-[0_18px_50px_rgba(15,23,42,0.18)] md:grid-cols-[1.25fr_0.85fr_auto]"
                    >
                        <label
                            class="flex min-h-[66px] items-center gap-4 rounded-xl px-4"
                        >
                            <svg
                                class="h-7 w-7 shrink-0 text-slate-800"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <circle
                                    cx="11"
                                    cy="11"
                                    r="7"
                                />
                                <path
                                    d="m20 20-3.5-3.5"
                                />
                            </svg>

                            <span class="min-w-0 flex-1">
                                <span
                                    class="block text-sm font-black text-slate-800"
                                >
                                    What are you looking for?
                                </span>

                                <input
                                    type="search"
                                    name="q"
                                    class="mt-0.5 w-full border-0 bg-transparent p-0 text-sm text-slate-600 outline-none placeholder:text-slate-400"
                                    placeholder="e.g. restaurant, salon, mechanic..."
                                />
                            </span>
                        </label>

                        <label
                            class="flex min-h-[66px] items-center gap-4 border-t border-slate-200 px-4 md:border-l md:border-t-0"
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
                                <circle
                                    cx="12"
                                    cy="10"
                                    r="2.5"
                                />
                            </svg>

                            <span class="min-w-0 flex-1">
                                <span
                                    class="block text-sm font-black text-slate-800"
                                >
                                    Location
                                </span>

                                <select
                                    name="location"
                                    class="mt-0.5 w-full cursor-pointer border-0 bg-transparent p-0 text-sm text-slate-500 outline-none"
                                >
                                    <option value="">
                                        All Nigeria
                                    </option>

                                    <option
                                        v-for="state in states"
                                        :key="state.code"
                                        :value="state.slug"
                                    >
                                        {{ state.name }}
                                    </option>
                                </select>
                            </span>
                        </label>

                        <button
                            type="submit"
                            class="flex min-h-[62px] items-center justify-center gap-3 rounded-xl bg-emerald-600 px-10 text-base font-black text-white shadow-sm transition hover:bg-emerald-700 active:scale-[0.99]"
                        >
                            <svg
                                class="h-5 w-5"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.4"
                            >
                                <circle
                                    cx="11"
                                    cy="11"
                                    r="7"
                                />
                                <path
                                    d="m20 20-3.5-3.5"
                                />
                            </svg>
                            Search
                        </button>
                    </form>

                    <div
                        class="mt-7 flex flex-wrap items-center gap-x-8 gap-y-3 text-sm font-bold text-slate-700"
                    >
                        <span
                            class="inline-flex items-center gap-2"
                        >
                            <span
                                class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-xs text-white"
                            >
                                ✓
                            </span>
                            Real business data only
                        </span>

                        <span
                            class="inline-flex items-center gap-2"
                        >
                            <span class="text-emerald-700">
                                ◉
                            </span>
                            37 States &amp; FCT
                        </span>

                        <span
                            class="inline-flex items-center gap-2"
                        >
                            <span class="text-emerald-700">
                                ▥
                            </span>
                            774 LGAs &amp; Area Councils
                        </span>
                    </div>
                </div>

                <div
                    class="absolute bottom-4 right-5 hidden rounded-lg bg-slate-900/75 px-4 py-2 text-xs font-semibold text-white backdrop-blur md:block lg:right-10"
                >
                    ⌖ Lekki–Ikoyi Link Bridge, Lagos
                </div>
            </section>

            <!-- Categories -->
            <section
                id="categories"
                class="bg-white py-10 sm:py-14"
            >
                <div
                    class="mx-auto max-w-[1500px] px-5 sm:px-7 lg:px-10"
                >
                    <div
                        class="flex items-end justify-between gap-6"
                    >
                        <div>
                            <h2
                                class="text-2xl font-black tracking-tight text-[#062c31] sm:text-3xl"
                            >
                                Browse by Category
                            </h2>

                            <p
                                class="mt-1 text-sm text-slate-500"
                            >
                                Start with the type of business
                                or service you need.
                            </p>
                        </div>

                        <a
                            href="/search"
                            class="hidden items-center gap-2 text-sm font-black text-emerald-700 sm:flex"
                        >
                            View All Categories
                            <span>→</span>
                        </a>
                    </div>

                    <div
                        class="mt-8 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5 xl:grid-cols-10"
                    >
                        <a
                            v-for="category in categories"
                            :key="category.name"
                            :href="
                                '/search?q=' +
                                encodeURIComponent(
                                    category.query
                                )
                            "
                            class="group text-center"
                        >
                            <span
                                :class="[
                                    category.box,
                                    'mx-auto flex h-[72px] w-full items-center justify-center rounded-xl border border-slate-100 text-3xl transition duration-200 group-hover:-translate-y-1 group-hover:shadow-md',
                                ]"
                            >
                                {{ category.icon }}
                            </span>

                            <span
                                class="mt-3 block text-sm font-bold leading-tight text-slate-800"
                            >
                                {{ category.name }}
                            </span>
                        </a>

                        <a
                            href="/search"
                            class="group text-center"
                        >
                            <span
                                class="mx-auto flex h-[72px] w-full items-center justify-center rounded-xl border border-slate-100 bg-slate-50 text-3xl transition duration-200 group-hover:-translate-y-1 group-hover:shadow-md"
                            >
                                •••
                            </span>

                            <span
                                class="mt-3 block text-sm font-bold text-slate-800"
                            >
                                More Categories
                            </span>
                        </a>
                    </div>
                </div>
            </section>

            <!-- Featured business section, real-data safe -->
            <section
                class="border-y border-slate-100 bg-slate-50/60 py-12"
            >
                <div
                    class="mx-auto max-w-[1500px] px-5 sm:px-7 lg:px-10"
                >
                    <div
                        class="flex items-end justify-between gap-5"
                    >
                        <div>
                            <h2
                                class="text-2xl font-black tracking-tight text-[#062c31] sm:text-3xl"
                            >
                                Featured Businesses
                            </h2>

                            <p
                                class="mt-1 text-sm text-slate-500"
                            >
                                Discover businesses published
                                on BusinessFinder Nigeria.
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="businessCount === 0"
                        class="mt-7 overflow-hidden rounded-2xl border border-emerald-100 bg-white"
                    >
                        <div
                            class="grid lg:grid-cols-[1fr_auto]"
                        >
                            <div class="p-8 sm:p-10">
                                <div
                                    class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-50 text-2xl"
                                >
                                    ⌖
                                </div>

                                <h3
                                    class="mt-5 text-xl font-black text-slate-900"
                                >
                                    Real business listings are
                                    being prepared
                                </h3>

                                <p
                                    class="mt-3 max-w-2xl text-sm leading-6 text-slate-500"
                                >
                                    We are validating real Nigerian
                                    business data before publishing
                                    public listings. BusinessFinder
                                    does not create fake businesses,
                                    ratings or reviews to fill the
                                    directory.
                                </p>
                            </div>

                            <div
                                class="flex items-center border-t border-emerald-100 bg-emerald-50/60 p-8 lg:border-l lg:border-t-0"
                            >
                                <Link
                                    :href="register()"
                                    class="rounded-xl bg-emerald-600 px-6 py-3 text-sm font-black text-white hover:bg-emerald-700"
                                >
                                    Add Your Business →
                                </Link>
                            </div>
                        </div>
                    </div>

                    <div
                        v-else
                        class="mt-7 rounded-2xl border border-slate-200 bg-white p-8 text-slate-600"
                    >
                        {{ businessCount }} business records are
                        currently being prepared for public
                        directory presentation.
                    </div>
                </div>
            </section>

            <!-- Real location browsing -->
            <section
                id="locations"
                class="bg-white py-14"
            >
                <div
                    class="mx-auto max-w-[1500px] px-5 sm:px-7 lg:px-10"
                >
                    <div>
                        <h2
                            class="text-2xl font-black tracking-tight text-[#062c31] sm:text-3xl"
                        >
                            Browse by Location
                        </h2>

                        <p
                            class="mt-1 text-sm text-slate-500"
                        >
                            Explore BusinessFinder's real
                            nationwide geography foundation.
                        </p>
                    </div>

                    <div
                        class="mt-7 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-8"
                    >
                        <a
                            v-for="state in states.slice(0, 8)"
                            :key="state.code"
                            :href="
                                '/search?location=' +
                                encodeURIComponent(
                                    state.slug
                                )
                            "
                            class="flex min-h-[72px] items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 transition hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md"
                        >
                            <span
                                class="text-xl text-emerald-700"
                            >
                                ⌖
                            </span>

                            <span>
                                <span
                                    class="block text-sm font-black text-slate-800"
                                >
                                    {{ state.name }}
                                </span>

                                <span
                                    class="mt-0.5 block text-xs text-slate-400"
                                >
                                    Explore location
                                </span>
                            </span>
                        </a>
                    </div>

                    <div class="mt-7">
                        <a
                            href="/search"
                            class="inline-flex items-center gap-2 text-sm font-black text-emerald-700"
                        >
                            View all Nigerian locations
                            <span>→</span>
                        </a>
                    </div>
                </div>
            </section>

            <!-- Business-owner CTA -->
            <section
                class="pb-14"
            >
                <div
                    class="mx-auto max-w-[1500px] px-5 sm:px-7 lg:px-10"
                >
                    <div
                        class="flex flex-col gap-6 rounded-2xl border border-emerald-100 bg-gradient-to-r from-emerald-50 to-teal-50 px-6 py-7 sm:flex-row sm:items-center sm:justify-between sm:px-9"
                    >
                        <div class="flex items-center gap-5">
                            <div
                                class="flex h-14 w-14 items-center justify-center rounded-xl bg-white text-3xl shadow-sm"
                            >
                                🏪
                            </div>

                            <div>
                                <h2
                                    class="text-xl font-black text-[#062c31]"
                                >
                                    List Your Business on
                                    BusinessFinder Nigeria
                                </h2>

                                <p
                                    class="mt-1 text-sm text-slate-600"
                                >
                                    Build your presence and get
                                    ready to connect with customers.
                                </p>
                            </div>
                        </div>

                        <Link
                            :href="register()"
                            class="inline-flex shrink-0 items-center justify-center rounded-xl bg-emerald-600 px-7 py-3.5 text-sm font-black text-white hover:bg-emerald-700"
                        >
                            Add Your Business →
                        </Link>
                    </div>
                </div>
            </section>
        </main>

        <PublicFooter />
    </div>
</template>
