<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { dashboard, login, register } from '@/routes';

defineProps<{
    active?: 'home' | 'businesses' | 'categories' | 'locations' | 'about';
}>();

const mobileOpen = ref(false);
</script>

<template>
    <header
        class="relative z-50 border-b border-slate-200/80 bg-white"
    >
        <div
            class="mx-auto flex h-[72px] max-w-[1500px] items-center justify-between px-5 sm:px-7 lg:px-10"
        >
            <a
                href="/"
                class="flex shrink-0 items-center gap-2.5"
                aria-label="BusinessFinder Nigeria home"
            >
                <svg
                    viewBox="0 0 50 56"
                    class="h-11 w-10 shrink-0"
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
                    <path
                        d="M25 3C13.95 3 5 11.95 5 23c0 10.9 10.5 20.2 16.2 24.6 2.5-8.1 5.9-14.3 9.8-19C36.6 21.9 38 12.7 32.5 5.2A19.8 19.8 0 0 0 25 3Z"
                        fill="#059669"
                    />
                    <circle
                        cx="25"
                        cy="22"
                        r="8"
                        fill="white"
                    />
                </svg>

                <span
                    class="whitespace-nowrap text-[19px] font-black tracking-[-0.035em] text-[#062c31] sm:text-[22px]"
                >
                    BusinessFinder
                    <span class="text-[#0b9b4d]">
                        Nigeria
                    </span>
                </span>
            </a>

            <nav
                class="hidden h-full items-center gap-9 lg:flex"
                aria-label="Primary navigation"
            >
                <a
                    href="/"
                    :class="[
                        'flex h-full items-center border-b-2 px-1 text-sm font-bold transition',
                        active === 'home'
                            ? 'border-emerald-600 text-emerald-700'
                            : 'border-transparent text-slate-700 hover:text-emerald-700',
                    ]"
                >
                    Home
                </a>

                <a
                    href="/search"
                    :class="[
                        'flex h-full items-center border-b-2 px-1 text-sm font-bold transition',
                        active === 'businesses'
                            ? 'border-emerald-600 text-emerald-700'
                            : 'border-transparent text-slate-700 hover:text-emerald-700',
                    ]"
                >
                    Businesses
                </a>

                <a
                    href="/#categories"
                    :class="[
                        'flex h-full items-center border-b-2 px-1 text-sm font-bold transition',
                        active === 'categories'
                            ? 'border-emerald-600 text-emerald-700'
                            : 'border-transparent text-slate-700 hover:text-emerald-700',
                    ]"
                >
                    Categories
                </a>

                <a
                    href="/#locations"
                    :class="[
                        'flex h-full items-center border-b-2 px-1 text-sm font-bold transition',
                        active === 'locations'
                            ? 'border-emerald-600 text-emerald-700'
                            : 'border-transparent text-slate-700 hover:text-emerald-700',
                    ]"
                >
                    Locations
                </a>

                <a
                    href="/#about"
                    class="flex h-full items-center border-b-2 border-transparent px-1 text-sm font-bold text-slate-700 transition hover:text-emerald-700"
                >
                    About
                </a>
            </nav>

            <div class="hidden items-center gap-3 lg:flex">
                <a
                    href="/search"
                    class="flex h-10 w-10 items-center justify-center rounded-full text-slate-800 transition hover:bg-slate-100"
                    aria-label="Search businesses"
                >
                    <svg
                        class="h-5 w-5"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <circle cx="11" cy="11" r="7" />
                        <path d="m20 20-3.5-3.5" />
                    </svg>
                </a>

                <template v-if="$page.props.auth.user">
                    <Link
                        :href="dashboard()"
                        class="rounded-xl px-4 py-2.5 text-sm font-bold text-slate-800 transition hover:bg-slate-100"
                    >
                        Dashboard
                    </Link>
                </template>

                <template v-else>
                    <Link
                        :href="login()"
                        class="rounded-xl px-4 py-2.5 text-sm font-bold text-slate-800 transition hover:bg-slate-100"
                    >
                        Login
                    </Link>
                </template>

                <Link
                    :href="register()"
                    class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-5 py-3 text-sm font-black text-white shadow-sm transition hover:bg-emerald-700"
                >
                    <span
                        class="flex h-5 w-5 items-center justify-center rounded-full bg-white text-sm font-black text-emerald-600"
                    >
                        +
                    </span>
                    Add Your Business
                </Link>
            </div>

            <button
                type="button"
                class="flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 lg:hidden"
                aria-label="Toggle menu"
                @click="mobileOpen = !mobileOpen"
            >
                <svg
                    v-if="!mobileOpen"
                    class="h-6 w-6"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path d="M4 7h16M4 12h16M4 17h16" />
                </svg>

                <svg
                    v-else
                    class="h-6 w-6"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path d="M6 6l12 12M18 6 6 18" />
                </svg>
            </button>
        </div>

        <div
            v-if="mobileOpen"
            class="border-t border-slate-200 bg-white px-5 py-5 shadow-xl lg:hidden"
        >
            <nav class="grid gap-1">
                <a
                    href="/"
                    class="rounded-xl px-4 py-3 font-bold hover:bg-emerald-50"
                >
                    Home
                </a>

                <a
                    href="/search"
                    class="rounded-xl px-4 py-3 font-bold hover:bg-emerald-50"
                >
                    Businesses
                </a>

                <a
                    href="/#categories"
                    class="rounded-xl px-4 py-3 font-bold hover:bg-emerald-50"
                >
                    Categories
                </a>

                <a
                    href="/#locations"
                    class="rounded-xl px-4 py-3 font-bold hover:bg-emerald-50"
                >
                    Locations
                </a>

                <a
                    href="/#about"
                    class="rounded-xl px-4 py-3 font-bold hover:bg-emerald-50"
                >
                    About
                </a>

                <Link
                    v-if="$page.props.auth.user"
                    :href="dashboard()"
                    class="rounded-xl px-4 py-3 font-bold hover:bg-emerald-50"
                >
                    Dashboard
                </Link>

                <Link
                    v-else
                    :href="login()"
                    class="rounded-xl px-4 py-3 font-bold hover:bg-emerald-50"
                >
                    Login
                </Link>

                <Link
                    :href="register()"
                    class="mt-2 rounded-xl bg-emerald-600 px-4 py-3 text-center font-black text-white"
                >
                    + Add Your Business
                </Link>
            </nav>
        </div>
    </header>
</template>
