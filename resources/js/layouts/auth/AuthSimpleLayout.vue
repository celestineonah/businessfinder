<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BadgeCheck,
    Building2,
    MapPin,
    Search,
    ShieldCheck,
} from 'lucide-vue-next';

import { home } from '@/routes';
import heroImage from '../../../images/lekki-ikoyi-link-bridge.jpg';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="min-h-screen bg-white text-[#062c31]">
        <div class="grid min-h-screen lg:grid-cols-[1.08fr_0.92fr]">
            <!-- Brand / visual side -->
            <section
                class="relative hidden overflow-hidden bg-[#063b35] lg:flex"
            >
                <div
                    class="absolute inset-0 bg-cover bg-center"
                    :style="{
                        backgroundImage: `url(${heroImage})`,
                    }"
                />

                <div
                    class="absolute inset-0 bg-gradient-to-br from-[#042f2b]/95 via-[#064e3b]/85 to-emerald-900/55"
                />

                <div
                    class="relative z-10 flex w-full flex-col justify-between p-10 xl:p-14"
                >
                    <Link
                        :href="home()"
                        class="inline-flex w-fit items-center gap-3"
                    >
                        <svg
                            viewBox="0 0 50 56"
                            class="h-12 w-11 shrink-0"
                            aria-hidden="true"
                        >
                            <ellipse
                                cx="25"
                                cy="50"
                                rx="18"
                                ry="4"
                                fill="#34d399"
                                opacity=".92"
                            />

                            <path
                                d="M25 3C13.95 3 5 11.95 5 23c0 15 20 27 20 27s20-12 20-27C45 11.95 36.05 3 25 3Z"
                                fill="#10b981"
                            />

                            <circle
                                cx="25"
                                cy="22"
                                r="8"
                                fill="white"
                            />
                        </svg>

                        <span
                            class="text-2xl font-black tracking-[-0.035em] text-white"
                        >
                            BusinessFinder
                            <span class="text-emerald-300">
                                Nigeria
                            </span>
                        </span>
                    </Link>

                    <div class="max-w-2xl">
                        <p
                            class="text-xs font-black uppercase tracking-[0.3em] text-emerald-200"
                        >
                            Discover
                            <span class="px-2">•</span>
                            Connect
                            <span class="px-2">•</span>
                            Grow
                        </p>

                        <h1
                            class="mt-5 text-5xl font-black leading-[1.02] tracking-[-0.05em] text-white xl:text-6xl"
                        >
                            Grow your business
                            presence across Nigeria.
                        </h1>

                        <p
                            class="mt-5 max-w-xl text-base leading-7 text-emerald-50/90"
                        >
                            Manage your listing, keep business
                            information accurate, and connect
                            with customers through
                            BusinessFinder Nigeria.
                        </p>

                        <div
                            class="mt-8 grid max-w-xl gap-3 sm:grid-cols-3"
                        >
                            <div
                                class="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur"
                            >
                                <Search
                                    class="h-5 w-5 text-emerald-300"
                                />

                                <p
                                    class="mt-3 text-sm font-black text-white"
                                >
                                    Be Discovered
                                </p>
                            </div>

                            <div
                                class="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur"
                            >
                                <ShieldCheck
                                    class="h-5 w-5 text-emerald-300"
                                />

                                <p
                                    class="mt-3 text-sm font-black text-white"
                                >
                                    Build Trust
                                </p>
                            </div>

                            <div
                                class="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur"
                            >
                                <Building2
                                    class="h-5 w-5 text-emerald-300"
                                />

                                <p
                                    class="mt-3 text-sm font-black text-white"
                                >
                                    Manage Listings
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="flex items-center justify-between gap-6 text-xs font-semibold text-emerald-100/80"
                    >
                        <span
                            class="inline-flex items-center gap-2"
                        >
                            <MapPin class="h-4 w-4" />
                            Built for Nigerian businesses
                        </span>

                        <span>
                            Lekki–Ikoyi Link Bridge, Lagos
                        </span>
                    </div>
                </div>
            </section>

            <!-- Form side -->
            <section
                class="flex min-h-screen flex-col bg-white"
            >
                <header
                    class="flex items-center justify-between border-b border-slate-100 px-5 py-5 sm:px-8 lg:px-10"
                >
                    <Link
                        :href="home()"
                        class="flex items-center gap-2.5 lg:hidden"
                    >
                        <svg
                            viewBox="0 0 50 56"
                            class="h-10 w-9"
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

                            <circle
                                cx="25"
                                cy="22"
                                r="8"
                                fill="white"
                            />
                        </svg>

                        <span
                            class="font-black tracking-tight text-[#062c31]"
                        >
                            BusinessFinder
                            <span class="text-emerald-600">
                                Nigeria
                            </span>
                        </span>
                    </Link>

                    <Link
                        :href="home()"
                        class="ml-auto text-sm font-black text-slate-600 transition hover:text-emerald-700"
                    >
                        ← Back to BusinessFinder
                    </Link>
                </header>

                <div
                    class="flex flex-1 items-center justify-center px-5 py-10 sm:px-8 lg:px-12"
                >
                    <div class="w-full max-w-[470px]">
                        <div
                            class="mb-8 flex items-center gap-3"
                        >
                            <div
                                class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50"
                            >
                                <BadgeCheck
                                    class="h-6 w-6 text-emerald-600"
                                />
                            </div>

                            <div>
                                <p
                                    class="text-xs font-black uppercase tracking-[0.2em] text-emerald-700"
                                >
                                    BusinessFinder Account
                                </p>
                            </div>
                        </div>

                        <div v-if="title">
                            <h1
                                class="text-3xl font-black tracking-[-0.04em] text-[#062c31] sm:text-4xl"
                            >
                                {{ title }}
                            </h1>

                            <p
                                v-if="description"
                                class="mt-3 text-sm leading-6 text-slate-500"
                            >
                                {{ description }}
                            </p>
                        </div>

                        <div class="mt-8">
                            <slot />
                        </div>

                        <p
                            class="mt-8 text-center text-xs leading-5 text-slate-400"
                        >
                            By using BusinessFinder Nigeria,
                            you agree to provide accurate
                            account and business information.
                        </p>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
