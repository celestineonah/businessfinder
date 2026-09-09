<script setup lang="ts">
const year = new Date().getFullYear();
</script>

<template>
    <footer
        id="about"
        class="border-t border-slate-200 bg-white"
    >
        <div
            class="mx-auto max-w-[1500px] px-5 py-10 sm:px-7 lg:px-10"
        >
            <div
                class="flex flex-col gap-8 lg:flex-row lg:items-start lg:justify-between"
            >
                <div class="max-w-md">
                    <a
                        href="/"
                        class="flex items-center gap-2.5"
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
                            class="text-lg font-black tracking-tight text-[#062c31]"
                        >
                            BusinessFinder
                            <span class="text-emerald-600">
                                Nigeria
                            </span>
                        </span>
                    </a>

                    <p
                        class="mt-4 text-sm leading-6 text-slate-500"
                    >
                        Discover, connect and grow with businesses
                        across Nigeria. Built around real geography,
                        transparent listing status and trusted
                        business information.
                    </p>
                </div>

                <div
                    class="grid grid-cols-2 gap-x-12 gap-y-3 text-sm font-semibold text-slate-600 sm:grid-cols-5"
                >
                    <a href="/">Home</a>
                    <a href="/search">Businesses</a>
                    <a href="/categories">Categories</a>
                    <a href="/#locations">Locations</a>
                    <a href="/#about">About</a>
                </div>
            </div>

            <div
                class="mt-9 flex flex-col gap-3 border-t border-slate-200 pt-6 text-xs text-slate-400 sm:flex-row sm:items-center sm:justify-between"
            >
                <p>
                    © {{ year }} BusinessFinder Nigeria.
                    All rights reserved.
                </p>

                <p>
                    Lagos bridge photograph:
                    Chippla / Wikimedia Commons,
                    CC BY-SA 3.0.
                </p>
            </div>
        </div>
    </footer>
</template>
