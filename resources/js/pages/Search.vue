<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

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
    <Head title="Search Businesses" />

    <div class="min-h-screen bg-slate-50 text-slate-950">
        <header class="border-b border-slate-200 bg-white">
            <div
                class="mx-auto flex max-w-7xl items-center justify-between px-5 py-4 sm:px-6 lg:px-8"
            >
                <a
                    href="/"
                    class="flex items-center gap-3"
                >
                    <span
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-600 text-sm font-black text-white"
                    >
                        BF
                    </span>

                    <span>
                        <span class="block font-black">
                            BusinessFinder
                        </span>
                        <span
                            class="block text-[10px] font-bold uppercase tracking-[0.2em] text-emerald-600"
                        >
                            Nigeria
                        </span>
                    </span>
                </a>

                <a
                    href="/"
                    class="text-sm font-bold text-slate-600 hover:text-slate-950"
                >
                    Back home
                </a>
            </div>
        </header>

        <main
            class="mx-auto max-w-7xl px-5 py-10 sm:px-6 lg:px-8"
        >
            <form
                action="/search"
                method="get"
                class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm md:grid-cols-[1.5fr_1fr_auto]"
            >
                <input
                    name="q"
                    type="search"
                    :value="query"
                    placeholder="Business or service"
                    class="min-h-13 rounded-xl border-0 bg-slate-50 px-4 font-medium outline-none"
                />

                <select
                    name="location"
                    class="min-h-13 rounded-xl border-0 bg-slate-50 px-4 font-semibold outline-none"
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

                <button
                    type="submit"
                    class="min-h-13 rounded-xl bg-emerald-600 px-7 font-black text-white hover:bg-emerald-700"
                >
                    Search
                </button>
            </form>

            <div class="mt-10">
                <p
                    class="text-sm font-black uppercase tracking-[0.18em] text-emerald-600"
                >
                    Search results
                </p>

                <h1
                    class="mt-2 text-3xl font-black tracking-tight"
                >
                    <template v-if="query">
                        Results for “{{ query }}”
                    </template>
                    <template v-else>
                        Browse businesses
                    </template>
                </h1>

                <p
                    v-if="locationLabel"
                    class="mt-2 text-slate-500"
                >
                    Location: {{ locationLabel }}
                </p>
            </div>

            <section
                class="mt-10 rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center"
            >
                <div
                    class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 text-2xl"
                >
                    🔎
                </div>

                <h2
                    class="mt-5 text-xl font-black text-slate-950"
                >
                    Real business listings are being prepared
                </h2>

                <p
                    class="mx-auto mt-3 max-w-xl leading-7 text-slate-500"
                >
                    BusinessFinder is currently importing and
                    validating real Nigerian business data. We do not
                    publish fabricated businesses just to populate
                    search results.
                </p>

                <p
                    v-if="businessCount > 0"
                    class="mt-4 text-sm font-semibold text-slate-500"
                >
                    Existing inventory is being prepared for public
                    search.
                </p>

                <a
                    href="/"
                    class="mt-7 inline-flex rounded-xl bg-slate-950 px-5 py-3 text-sm font-black text-white hover:bg-emerald-700"
                >
                    Return to homepage
                </a>
            </section>
        </main>
    </div>
</template>
