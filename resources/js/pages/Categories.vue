<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    BriefcaseBusiness,
    Building2,
    CalendarDays,
    Factory,
    GraduationCap,
    HeartPulse,
    House,
    Image,
    Landmark,
    Laptop,
    Plane,
    ShoppingCart,
    Sparkles,
    Sprout,
    Truck,
    UtensilsCrossed,
    Wrench,
} from 'lucide-vue-next';

import PublicFooter from '@/components/public/PublicFooter.vue';
import PublicHeader from '@/components/public/PublicHeader.vue';
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
        icon: UtensilsCrossed,
        iconClass: 'text-emerald-600',
        boxClass: 'bg-emerald-50',
    },
    {
        name: 'Retail & Shopping',
        query: 'retail shopping',
        icon: ShoppingCart,
        iconClass: 'text-amber-600',
        boxClass: 'bg-amber-50',
    },
    {
        name: 'Home & Living',
        query: 'home living',
        icon: House,
        iconClass: 'text-rose-500',
        boxClass: 'bg-rose-50',
    },
    {
        name: 'Health & Medical',
        query: 'health medical',
        icon: HeartPulse,
        iconClass: 'text-blue-600',
        boxClass: 'bg-blue-50',
    },
    {
        name: 'Education',
        query: 'education',
        icon: GraduationCap,
        iconClass: 'text-violet-600',
        boxClass: 'bg-violet-50',
    },
    {
        name: 'Automotive',
        query: 'automotive',
        icon: Wrench,
        iconClass: 'text-emerald-700',
        boxClass: 'bg-emerald-50',
    },
    {
        name: 'Professional Services',
        query: 'professional services',
        icon: BriefcaseBusiness,
        iconClass: 'text-orange-600',
        boxClass: 'bg-orange-50',
    },
    {
        name: 'Travel & Tourism',
        query: 'travel tourism',
        icon: Plane,
        iconClass: 'text-cyan-600',
        boxClass: 'bg-cyan-50',
    },
    {
        name: 'Tech & Electronics',
        query: 'technology electronics',
        icon: Laptop,
        iconClass: 'text-violet-600',
        boxClass: 'bg-violet-50',
    },
    {
        name: 'Beauty & Personal Care',
        query: 'beauty personal care',
        icon: Sparkles,
        iconClass: 'text-pink-600',
        boxClass: 'bg-pink-50',
    },
    {
        name: 'Real Estate',
        query: 'real estate',
        icon: Building2,
        iconClass: 'text-emerald-600',
        boxClass: 'bg-emerald-50',
    },
    {
        name: 'Finance',
        query: 'finance',
        icon: Landmark,
        iconClass: 'text-amber-600',
        boxClass: 'bg-amber-50',
    },
    {
        name: 'Events',
        query: 'events',
        icon: CalendarDays,
        iconClass: 'text-violet-600',
        boxClass: 'bg-violet-50',
    },
    {
        name: 'Agriculture',
        query: 'agriculture',
        icon: Sprout,
        iconClass: 'text-green-600',
        boxClass: 'bg-green-50',
    },
    {
        name: 'Logistics',
        query: 'logistics',
        icon: Truck,
        iconClass: 'text-blue-600',
        boxClass: 'bg-blue-50',
    },
    {
        name: 'Manufacturing',
        query: 'manufacturing',
        icon: Factory,
        iconClass: 'text-red-500',
        boxClass: 'bg-red-50',
    },
    {
        name: 'Media & Printing',
        query: 'media printing',
        icon: Image,
        iconClass: 'text-orange-500',
        boxClass: 'bg-orange-50',
    },
];
</script>

<template>
    <Head title="Explore Business Categories">
        <meta
            name="description"
            content="Browse BusinessFinder Nigeria categories and discover services across Nigeria."
        />
        <link
            rel="icon"
            type="image/svg+xml"
            href="/favicon.svg"
        />
    </Head>

    <div class="min-h-screen bg-white text-[#072c33]">
        <PublicHeader active="categories" />

        <main>
            <!-- Approved categories hero -->
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
                    class="absolute inset-0 -z-10 bg-gradient-to-r from-white via-white/90 to-sky-100/30"
                />

                <div
                    class="mx-auto max-w-[1500px] px-5 py-9 sm:px-7 lg:px-10"
                >
                    <p
                        class="text-xs font-black uppercase tracking-[0.25em] text-slate-600"
                    >
                        Discover
                        <span class="px-2">•</span>
                        Browse
                        <span class="px-2">•</span>
                        Connect
                    </p>

                    <h1
                        class="mt-4 text-4xl font-black tracking-[-0.045em] text-[#062c31] sm:text-5xl"
                    >
                        Explore Business Categories
                    </h1>

                    <p
                        class="mt-3 max-w-2xl text-base font-medium leading-7 text-slate-700"
                    >
                        Discover businesses across industries in
                        Nigeria. Browse by category, find what you
                        need, and connect with local businesses.
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
                                placeholder="Search categories or services..."
                                class="w-full border-0 bg-transparent text-sm font-semibold text-slate-800 outline-none placeholder:text-slate-400"
                            />
                        </label>

                        <label
                            class="flex min-h-[60px] items-center gap-3 border-t border-slate-200 px-4 md:border-l md:border-t-0"
                        >
                            <svg
                                class="h-6 w-6 text-slate-800"
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
                                <option value="">
                                    All Locations
                                </option>

                                <option
                                    v-for="state in states"
                                    :key="state.code"
                                    :value="state.slug"
                                >
                                    {{ state.name }}
                                </option>
                            </select>
                        </label>

                        <button
                            type="submit"
                            class="min-h-[58px] rounded-xl bg-emerald-600 px-10 text-sm font-black text-white hover:bg-emerald-700"
                        >
                            Search
                        </button>
                    </form>
                </div>

                <div
                    class="absolute bottom-4 right-10 hidden rounded-lg bg-slate-900/75 px-4 py-2 text-xs font-semibold text-white backdrop-blur md:block"
                >
                    ⌖ Lekki–Ikoyi Link Bridge, Lagos
                </div>
            </section>

            <!-- Category grid -->
            <section class="py-10 sm:py-12">
                <div
                    class="mx-auto max-w-[1500px] px-5 sm:px-7 lg:px-10"
                >
                    <div>
                        <h2
                            class="text-2xl font-black tracking-tight text-slate-900"
                        >
                            All Business Categories
                        </h2>

                        <p
                            class="mt-1 text-sm text-slate-500"
                        >
                            Browse categories to find the right
                            businesses for your needs.
                        </p>
                    </div>

                    <div
                        class="mt-7 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6"
                    >
                        <a
                            v-for="category in categories"
                            :key="category.name"
                            :href="
                                '/search?q=' +
                                encodeURIComponent(category.query)
                            "
                            :class="[
                                category.boxClass,
                                'group flex min-h-[92px] items-center gap-4 rounded-xl border border-slate-100 px-5 py-4 transition duration-200 hover:-translate-y-0.5 hover:border-emerald-200 hover:shadow-md',
                            ]"
                        >
                            <component
                                :is="category.icon"
                                :class="[
                                    category.iconClass,
                                    'h-7 w-7 shrink-0',
                                ]"
                            />

                            <span class="min-w-0">
                                <span
                                    class="block text-sm font-black leading-tight text-slate-900"
                                >
                                    {{ category.name }}
                                </span>

                                <span
                                    class="mt-1 block text-xs font-medium text-slate-500"
                                >
                                    Explore services
                                </span>
                            </span>

                            <span
                                class="ml-auto text-lg text-slate-400 transition group-hover:translate-x-1 group-hover:text-emerald-700"
                            >
                                ›
                            </span>
                        </a>
                    </div>
                </div>
            </section>

            <!-- Collections -->
            <section
                class="border-y border-slate-100 bg-slate-50/60 py-12"
            >
                <div
                    class="mx-auto max-w-[1500px] px-5 sm:px-7 lg:px-10"
                >
                    <div
                        class="grid gap-10 lg:grid-cols-2"
                    >
                        <div>
                            <h2
                                class="text-2xl font-black text-slate-900"
                            >
                                Top Categories
                            </h2>

                            <p
                                class="mt-1 text-sm text-slate-500"
                            >
                                Popular discovery areas on
                                BusinessFinder Nigeria.
                            </p>

                            <div
                                class="mt-6 grid gap-3 sm:grid-cols-2"
                            >
                                <a
                                    v-for="category in categories.slice(0, 6)"
                                    :key="'top-' + category.name"
                                    :href="
                                        '/search?q=' +
                                        encodeURIComponent(category.query)
                                    "
                                    class="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-5 py-4 transition hover:border-emerald-300"
                                >
                                    <span
                                        class="font-bold text-slate-800"
                                    >
                                        {{ category.name }}
                                    </span>

                                    <span class="text-emerald-700">
                                        →
                                    </span>
                                </a>
                            </div>
                        </div>

                        <div>
                            <h2
                                class="text-2xl font-black text-slate-900"
                            >
                                Featured Collections
                            </h2>

                            <p
                                class="mt-1 text-sm text-slate-500"
                            >
                                Curated collections will appear as
                                real business inventory grows.
                            </p>

                            <div
                                class="mt-6 rounded-2xl border border-emerald-100 bg-white p-8"
                            >
                                <div
                                    class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-50 text-2xl"
                                >
                                    ✦
                                </div>

                                <h3
                                    class="mt-5 text-lg font-black text-slate-900"
                                >
                                    Collections are being prepared
                                </h3>

                                <p
                                    class="mt-2 text-sm leading-6 text-slate-500"
                                >
                                    New & Notable, Top Rated,
                                    Trending and other collections
                                    will only be published when they
                                    are backed by real listings and
                                    real activity.
                                </p>

                                <p
                                    class="mt-4 text-xs font-bold text-emerald-700"
                                >
                                    Current public business inventory:
                                    {{ businessCount }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Browse states -->
            <section
                id="locations"
                class="py-12"
            >
                <div
                    class="mx-auto max-w-[1500px] px-5 sm:px-7 lg:px-10"
                >
                    <h2
                        class="text-2xl font-black text-slate-900"
                    >
                        Browse by Location
                    </h2>

                    <p
                        class="mt-1 text-sm text-slate-500"
                    >
                        Search services across Nigeria's real
                        geography foundation.
                    </p>

                    <div
                        class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-8"
                    >
                        <a
                            v-for="state in states.slice(0, 8)"
                            :key="state.code"
                            :href="
                                '/search?location=' +
                                encodeURIComponent(state.slug)
                            "
                            class="rounded-xl border border-slate-200 bg-white px-4 py-4 transition hover:border-emerald-300 hover:shadow-sm"
                        >
                            <span
                                class="block text-sm font-black text-slate-800"
                            >
                                ⌖ {{ state.name }}
                            </span>

                            <span
                                class="mt-1 block text-xs text-slate-400"
                            >
                                Browse businesses
                            </span>
                        </a>
                    </div>
                </div>
            </section>

            <!-- CTA -->
            <section class="pb-14">
                <div
                    class="mx-auto max-w-[1500px] px-5 sm:px-7 lg:px-10"
                >
                    <div
                        class="flex flex-col gap-5 rounded-2xl border border-emerald-100 bg-gradient-to-r from-emerald-50 to-teal-50 px-7 py-7 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <h2
                                class="text-xl font-black text-[#062c31]"
                            >
                                List Your Business on BusinessFinder Nigeria
                            </h2>

                            <p
                                class="mt-1 text-sm text-slate-600"
                            >
                                Build your presence and prepare to
                                reach customers across Nigeria.
                            </p>
                        </div>

                        <Link
                            href="/add-business"
                            class="rounded-xl bg-emerald-600 px-7 py-3.5 text-center text-sm font-black text-white hover:bg-emerald-700"
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
