<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { dashboard, login, register } from '@/routes';

type StateOption = {
    name: string;
    slug: string;
    code: string;
};

defineProps<{
    states: StateOption[];
}>();

const categories = [
    {
        name: 'Restaurants & Food',
        query: 'restaurants',
        icon: '🍽️',
        description: 'Restaurants, catering and food services',
    },
    {
        name: 'Hotels & Travel',
        query: 'hotels',
        icon: '🏨',
        description: 'Hotels, travel and hospitality services',
    },
    {
        name: 'Auto Services',
        query: 'auto services',
        icon: '🚗',
        description: 'Repairs, rentals and automotive services',
    },
    {
        name: 'Health & Wellness',
        query: 'health',
        icon: '🏥',
        description: 'Clinics, pharmacies and wellness services',
    },
    {
        name: 'Home Services',
        query: 'home services',
        icon: '🏠',
        description: 'Repairs, cleaning and property services',
    },
    {
        name: 'Professional Services',
        query: 'professional services',
        icon: '💼',
        description: 'Legal, accounting and business support',
    },
    {
        name: 'Beauty & Personal Care',
        query: 'beauty',
        icon: '✨',
        description: 'Salons, spas and personal care',
    },
    {
        name: 'Education & Training',
        query: 'education',
        icon: '🎓',
        description: 'Schools, training and learning services',
    },
];
</script>

<template>
    <Head title="Find Trusted Businesses Across Nigeria">
        <meta
            name="description"
            content="BusinessFinder Nigeria helps you discover trusted businesses, services and professionals across Nigeria."
        />
    </Head>

    <div class="min-h-screen bg-white text-slate-950">
        <!-- Header -->
        <header
            class="sticky top-0 z-50 border-b border-slate-200/80 bg-white/95 backdrop-blur"
        >
            <div
                class="mx-auto flex h-18 max-w-7xl items-center justify-between px-5 py-4 sm:px-6 lg:px-8"
            >
                <a
                    href="/"
                    class="flex items-center gap-3"
                >
                    <span
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-600 text-sm font-black tracking-tight text-white shadow-sm"
                    >
                        BF
                    </span>

                    <span>
                        <span
                            class="block text-lg font-black leading-none tracking-tight text-slate-950"
                        >
                            BusinessFinder
                        </span>
                        <span
                            class="mt-1 block text-[10px] font-bold uppercase tracking-[0.22em] text-emerald-600"
                        >
                            Nigeria
                        </span>
                    </span>
                </a>

                <nav class="flex items-center gap-2 sm:gap-3">
                    <a
                        href="#categories"
                        class="hidden rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-950 md:block"
                    >
                        Categories
                    </a>

                    <a
                        href="#locations"
                        class="hidden rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-950 md:block"
                    >
                        Locations
                    </a>

                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-700"
                    >
                        Dashboard
                    </Link>

                    <template v-else>
                        <Link
                            :href="login()"
                            class="hidden rounded-lg px-3 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-slate-100 sm:block"
                        >
                            Log in
                        </Link>

                        <Link
                            :href="register()"
                            class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-700"
                        >
                            List your business
                        </Link>
                    </template>
                </nav>
            </div>
        </header>

        <main>
            <!-- Hero -->
            <section
                class="relative overflow-hidden bg-slate-950"
            >
                <div
                    class="pointer-events-none absolute -left-32 top-20 h-96 w-96 rounded-full bg-emerald-500/20 blur-3xl"
                />

                <div
                    class="pointer-events-none absolute -right-40 -top-20 h-[34rem] w-[34rem] rounded-full bg-blue-500/15 blur-3xl"
                />

                <div
                    class="relative mx-auto max-w-7xl px-5 py-20 sm:px-6 sm:py-24 lg:px-8 lg:py-32"
                >
                    <div class="max-w-4xl">
                        <div
                            class="mb-6 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-bold uppercase tracking-[0.18em] text-emerald-300"
                        >
                            <span
                                class="h-2 w-2 rounded-full bg-emerald-400"
                            />
                            Nationwide Nigerian business discovery
                        </div>

                        <h1
                            class="max-w-4xl text-4xl font-black tracking-tight text-white sm:text-6xl lg:text-7xl"
                        >
                            Find Trusted Businesses
                            <span class="text-emerald-400">
                                Across Nigeria
                            </span>
                        </h1>

                        <p
                            class="mt-6 max-w-2xl text-base leading-7 text-slate-300 sm:text-lg"
                        >
                            Discover local businesses,
                            professionals and services with
                            transparent listing, verification and
                            location information.
                        </p>

                        <!-- Search -->
                        <form
                            action="/search"
                            method="get"
                            class="mt-9 grid gap-3 rounded-2xl bg-white p-3 shadow-2xl shadow-black/30 md:grid-cols-[1.4fr_1fr_auto]"
                        >
                            <label
                                class="flex min-h-14 items-center gap-3 rounded-xl bg-slate-50 px-4"
                            >
                                <svg
                                    class="h-5 w-5 shrink-0 text-slate-400"
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
                                    <path d="m20 20-3.5-3.5" />
                                </svg>

                                <input
                                    name="q"
                                    type="search"
                                    placeholder="What business or service do you need?"
                                    class="w-full border-0 bg-transparent text-sm font-medium text-slate-950 outline-none placeholder:text-slate-400 sm:text-base"
                                />
                            </label>

                            <label
                                class="flex min-h-14 items-center gap-3 rounded-xl bg-slate-50 px-4"
                            >
                                <svg
                                    class="h-5 w-5 shrink-0 text-slate-400"
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

                                <select
                                    name="location"
                                    class="w-full cursor-pointer border-0 bg-transparent text-sm font-semibold text-slate-700 outline-none sm:text-base"
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
                            </label>

                            <button
                                type="submit"
                                class="min-h-14 rounded-xl bg-emerald-600 px-7 text-sm font-black text-white shadow-sm transition hover:bg-emerald-700 active:scale-[0.98] sm:text-base"
                            >
                                Search
                            </button>
                        </form>

                        <div
                            class="mt-5 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-slate-400"
                        >
                            <span class="font-semibold text-slate-300">
                                Popular:
                            </span>

                            <a
                                href="/search?q=restaurants"
                                class="transition hover:text-emerald-300"
                            >
                                Restaurants
                            </a>

                            <a
                                href="/search?q=hotels"
                                class="transition hover:text-emerald-300"
                            >
                                Hotels
                            </a>

                            <a
                                href="/search?q=car%20rentals"
                                class="transition hover:text-emerald-300"
                            >
                                Car Rentals
                            </a>

                            <a
                                href="/search?q=plumbers"
                                class="transition hover:text-emerald-300"
                            >
                                Plumbers
                            </a>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Coverage strip -->
            <section class="border-b border-slate-200 bg-white">
                <div
                    class="mx-auto grid max-w-7xl grid-cols-1 divide-y divide-slate-200 px-5 sm:grid-cols-3 sm:divide-x sm:divide-y-0 sm:px-6 lg:px-8"
                >
                    <div class="py-7 text-center">
                        <div class="text-2xl font-black text-slate-950">
                            37
                        </div>
                        <div class="mt-1 text-sm font-medium text-slate-500">
                            States &amp; FCT
                        </div>
                    </div>

                    <div class="py-7 text-center">
                        <div class="text-2xl font-black text-slate-950">
                            774
                        </div>
                        <div class="mt-1 text-sm font-medium text-slate-500">
                            LGAs &amp; Area Councils
                        </div>
                    </div>

                    <div class="py-7 text-center">
                        <div class="text-2xl font-black text-emerald-600">
                            Nationwide
                        </div>
                        <div class="mt-1 text-sm font-medium text-slate-500">
                            Search coverage foundation
                        </div>
                    </div>
                </div>
            </section>

            <!-- Categories -->
            <section
                id="categories"
                class="bg-slate-50 py-20"
            >
                <div
                    class="mx-auto max-w-7xl px-5 sm:px-6 lg:px-8"
                >
                    <div
                        class="flex flex-col justify-between gap-5 md:flex-row md:items-end"
                    >
                        <div>
                            <p
                                class="text-sm font-black uppercase tracking-[0.18em] text-emerald-600"
                            >
                                Discover services
                            </p>

                            <h2
                                class="mt-2 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl"
                            >
                                Browse popular categories
                            </h2>

                            <p
                                class="mt-3 max-w-2xl text-slate-500"
                            >
                                Start with a service and narrow your
                                search by location.
                            </p>
                        </div>
                    </div>

                    <div
                        class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
                    >
                        <a
                            v-for="category in categories"
                            :key="category.name"
                            :href="
                                '/search?q=' +
                                encodeURIComponent(category.query)
                            "
                            class="group rounded-2xl border border-slate-200 bg-white p-6 transition duration-300 hover:-translate-y-1 hover:border-emerald-200 hover:shadow-xl hover:shadow-slate-200/60"
                        >
                            <span
                                class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-50 text-2xl transition group-hover:scale-105"
                            >
                                {{ category.icon }}
                            </span>

                            <h3
                                class="mt-5 font-black text-slate-950"
                            >
                                {{ category.name }}
                            </h3>

                            <p
                                class="mt-2 text-sm leading-6 text-slate-500"
                            >
                                {{ category.description }}
                            </p>

                            <span
                                class="mt-5 inline-flex items-center gap-2 text-sm font-bold text-emerald-700"
                            >
                                Explore
                                <span
                                    class="transition group-hover:translate-x-1"
                                >
                                    →
                                </span>
                            </span>
                        </a>
                    </div>
                </div>
            </section>

            <!-- Locations -->
            <section
                id="locations"
                class="py-20"
            >
                <div
                    class="mx-auto max-w-7xl px-5 sm:px-6 lg:px-8"
                >
                    <div class="max-w-2xl">
                        <p
                            class="text-sm font-black uppercase tracking-[0.18em] text-emerald-600"
                        >
                            Search by place
                        </p>

                        <h2
                            class="mt-2 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl"
                        >
                            Find businesses by location
                        </h2>

                        <p class="mt-3 text-slate-500">
                            Our location foundation covers every
                            Nigerian state, the FCT and all 774 local
                            government areas and area councils.
                        </p>
                    </div>

                    <div
                        class="mt-10 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4"
                    >
                        <a
                            v-for="state in states.slice(0, 12)"
                            :key="state.code"
                            :href="
                                '/search?location=' +
                                encodeURIComponent(state.slug)
                            "
                            class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-4 font-bold text-slate-700 transition hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-800"
                        >
                            <span>{{ state.name }}</span>
                            <span class="text-slate-300">→</span>
                        </a>
                    </div>

                    <a
                        href="/search"
                        class="mt-7 inline-flex items-center gap-2 rounded-xl border border-slate-300 px-5 py-3 text-sm font-black text-slate-800 transition hover:border-slate-950"
                    >
                        Browse all Nigeria
                        <span>→</span>
                    </a>
                </div>
            </section>

            <!-- Trust -->
            <section class="bg-emerald-950 py-20 text-white">
                <div
                    class="mx-auto grid max-w-7xl gap-12 px-5 sm:px-6 lg:grid-cols-2 lg:px-8"
                >
                    <div>
                        <p
                            class="text-sm font-black uppercase tracking-[0.18em] text-emerald-300"
                        >
                            Built for trust
                        </p>

                        <h2
                            class="mt-3 text-3xl font-black tracking-tight sm:text-4xl"
                        >
                            Business discovery with clearer signals.
                        </h2>

                        <p
                            class="mt-5 max-w-xl leading-7 text-emerald-100/80"
                        >
                            BusinessFinder distinguishes listed
                            businesses from verified businesses and
                            keeps provenance, claim and verification
                            information separate.
                        </p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div
                            class="rounded-2xl border border-white/10 bg-white/5 p-6"
                        >
                            <div class="text-xl">✓</div>
                            <h3 class="mt-4 font-black">
                                Transparent status
                            </h3>
                            <p
                                class="mt-2 text-sm leading-6 text-emerald-100/70"
                            >
                                Imported businesses are not labelled
                                verified unless verification has
                                actually taken place.
                            </p>
                        </div>

                        <div
                            class="rounded-2xl border border-white/10 bg-white/5 p-6"
                        >
                            <div class="text-xl">⌖</div>
                            <h3 class="mt-4 font-black">
                                Local discovery
                            </h3>
                            <p
                                class="mt-2 text-sm leading-6 text-emerald-100/70"
                            >
                                Search is being structured around real
                                Nigerian geography, categories and
                                service areas.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Business owners -->
            <section class="py-20">
                <div
                    class="mx-auto max-w-7xl px-5 sm:px-6 lg:px-8"
                >
                    <div
                        class="overflow-hidden rounded-3xl bg-slate-950 px-6 py-10 sm:px-10 lg:flex lg:items-center lg:justify-between lg:px-14"
                    >
                        <div>
                            <p
                                class="text-sm font-black uppercase tracking-[0.18em] text-emerald-400"
                            >
                                For business owners
                            </p>

                            <h2
                                class="mt-3 max-w-2xl text-3xl font-black tracking-tight text-white"
                            >
                                Build your presence where Nigerian
                                customers are searching.
                            </h2>

                            <p
                                class="mt-4 max-w-2xl text-slate-400"
                            >
                                Create an account now. Business claims,
                                verification and profile tools are
                                being rolled out progressively.
                            </p>
                        </div>

                        <Link
                            :href="register()"
                            class="mt-7 inline-flex rounded-xl bg-emerald-500 px-6 py-3.5 text-sm font-black text-emerald-950 transition hover:bg-emerald-400 lg:mt-0"
                        >
                            Get started
                        </Link>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-slate-200 bg-white">
            <div
                class="mx-auto flex max-w-7xl flex-col gap-5 px-5 py-8 text-sm text-slate-500 sm:px-6 md:flex-row md:items-center md:justify-between lg:px-8"
            >
                <div class="font-semibold">
                    © 2026 BusinessFinder Nigeria
                </div>

                <div class="flex flex-wrap gap-5">
                    <a
                        href="#categories"
                        class="hover:text-slate-950"
                    >
                        Categories
                    </a>
                    <a
                        href="#locations"
                        class="hover:text-slate-950"
                    >
                        Locations
                    </a>
                    <Link
                        :href="login()"
                        class="hover:text-slate-950"
                    >
                        Business login
                    </Link>
                </div>
            </div>
        </footer>
    </div>
</template>
