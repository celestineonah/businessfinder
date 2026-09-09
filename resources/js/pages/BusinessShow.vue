<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    BadgeCheck,
    Building2,
    CheckCircle2,
    Clock3,
    ExternalLink,
    Mail,
    MapPin,
    MessageCircle,
    Phone,
    ShieldCheck,
    Tag,
    UserCheck,
} from 'lucide-vue-next';
import { computed } from 'vue';

import PublicFooter from '@/components/public/PublicFooter.vue';
import PublicHeader from '@/components/public/PublicHeader.vue';
import { register } from '@/routes';

type Category = {
    name: string;
    slug: string;
    singularName: string | null;
    isPrimary: boolean;
};

type LocationItem = {
    name: string;
    slug: string;
};

type BusinessLocation = {
    name: string | null;
    addressLine1: string | null;
    addressLine2: string | null;
    landmark: string | null;
    postalCode: string | null;
    latitude: string | number | null;
    longitude: string | number | null;
    serviceAreaOnly: boolean;
    state: LocationItem | null;
    city: LocationItem | null;
    lga: LocationItem | null;
    area: LocationItem | null;
    openingHours: Record<string, unknown> | null;
};

type BusinessProfile = {
    name: string;
    slug: string;
    legalName: string | null;
    shortDescription: string | null;
    description: string | null;
    phone: string | null;
    whatsappPhone: string | null;
    whatsappUrl: string | null;
    email: string | null;
    websiteUrl: string | null;
    listingLabel: string;
    isVerified: boolean;
    claimStatus: string;
    categories: Category[];
    verificationTypes: string[];
    sourceCount: number;
    sourceNames: string[];
    publishedAt: string | null;
    lastCheckedAt: string | null;
    location: BusinessLocation | null;
};

const props = defineProps<{
    business: BusinessProfile;
    canonicalUrl: string;
}>();

const primaryCategory = computed(
    () =>
        props.business.categories.find(
            (category) =>
                category.isPrimary,
        )
        ?? props.business.categories[0]
        ?? null,
);

const locationLabel = computed(() => {
    if (! props.business.location) {
        return null;
    }

    const values = [
        props.business.location.area?.name,
        props.business.location.city?.name,
        props.business.location.lga?.name,
        props.business.location.state?.name,
    ].filter(
        (value): value is string =>
            Boolean(value),
    );

    return [
        ...new Set(values),
    ].join(', ');
});

const address = computed(() => {
    const location =
        props.business.location;

    if (! location) {
        return null;
    }

    const values = [
        location.addressLine1,
        location.addressLine2,
        location.landmark,
        location.area?.name,
        location.city?.name,
        location.state?.name,
    ].filter(
        (value): value is string =>
            Boolean(value),
    );

    return [
        ...new Set(values),
    ].join(', ');
});

const phoneHref = computed(() =>
    props.business.phone
        ? 'tel:' +
          props.business.phone.replace(
              /\s+/g,
              '',
          )
        : null,
);

const emailHref = computed(() =>
    props.business.email
        ? 'mailto:' +
          props.business.email
        : null,
);

function formatHours(
    value: unknown,
): string {
    if (
        value === null
        || value === undefined
    ) {
        return 'Not published';
    }

    if (typeof value === 'string') {
        return value;
    }

    if (
        Array.isArray(value)
    ) {
        return value
            .map(String)
            .join(', ');
    }

    if (
        typeof value === 'object'
    ) {
        return Object.values(
            value as Record<
                string,
                unknown
            >,
        )
            .map(String)
            .join(' – ');
    }

    return String(value);
}

function verificationLabel(
    type: string,
): string {
    const labels: Record<
        string,
        string
    > = {
        phone: 'Phone',
        email: 'Email',
        cac: 'CAC',
        address: 'Address',
        website: 'Website',
        representative:
            'Representative',
    };

    return (
        labels[type]
        ?? type
    );
}
</script>

<template>
    <Head :title="business.name">
        <meta
            v-if="
                business.shortDescription
                || business.description
            "
            name="description"
            :content="
                business.shortDescription
                || business.description
                || ''
            "
        />

        <link
            rel="canonical"
            :href="canonicalUrl"
        />

        <link
            rel="icon"
            type="image/png"
            href="/brand/favicon-192.png"
        />
    </Head>

    <div
        class="min-h-screen bg-[#f8faf9] text-[#072c33]"
    >
        <PublicHeader active="businesses" />

        <main>
            <!-- Breadcrumb -->
            <div
                class="border-b border-slate-200 bg-white"
            >
                <div
                    class="mx-auto max-w-[1500px] px-5 py-4 text-xs font-semibold text-slate-500 sm:px-7 lg:px-10"
                >
                    <a
                        href="/"
                        class="hover:text-emerald-700"
                    >
                        Home
                    </a>

                    <span class="mx-2">
                        ›
                    </span>

                    <a
                        href="/search"
                        class="hover:text-emerald-700"
                    >
                        Businesses
                    </a>

                    <template
                        v-if="
                            primaryCategory
                        "
                    >
                        <span class="mx-2">
                            ›
                        </span>

                        <a
                            :href="
                                '/search?q=' +
                                encodeURIComponent(
                                    primaryCategory.name,
                                )
                            "
                            class="hover:text-emerald-700"
                        >
                            {{
                                primaryCategory.name
                            }}
                        </a>
                    </template>

                    <span class="mx-2">
                        ›
                    </span>

                    <span
                        class="text-slate-800"
                    >
                        {{ business.name }}
                    </span>
                </div>
            </div>

            <!-- Identity -->
            <section
                class="bg-white py-8"
            >
                <div
                    class="mx-auto grid max-w-[1500px] gap-7 px-5 sm:px-7 lg:grid-cols-[1fr_360px] lg:px-10"
                >
                    <div>
                        <div
                            class="flex flex-wrap items-center gap-2"
                        >
                            <span
                                v-if="
                                    business.isVerified
                                "
                                class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-black text-emerald-700"
                            >
                                <BadgeCheck
                                    class="h-4 w-4"
                                />
                                Verified Business
                            </span>

                            <span
                                v-else
                                class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1.5 text-xs font-black text-slate-700"
                            >
                                <Building2
                                    class="h-4 w-4"
                                />
                                Listed Business
                            </span>

${indent}<span
${indent}    v-if="
${indent}        business.claimStatus
${indent}        === 'pending'
${indent}    "
${indent}    class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1.5 text-xs font-black text-amber-700"
${indent}>
${indent}    <UserCheck
${indent}        class="h-4 w-4"
${indent}    />
${indent}    Claim Under Review
${indent}</span>

${indent}<span
${indent}    v-if="
${indent}        business.claimStatus
${indent}        === 'claimed'
${indent}    "
${indent}    class="${class}"
${indent}>
                                <UserCheck
                                    class="h-4 w-4"
                                />
                                Claimed
                            </span>
                        </div>

                        <h1
                            class="mt-4 text-4xl font-black tracking-[-0.045em] text-[#062c31] sm:text-5xl"
                        >
                            {{ business.name }}
                        </h1>

                        <div
                            class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-sm font-semibold text-slate-600"
                        >
                            <span
                                v-if="
                                    primaryCategory
                                "
                                class="inline-flex items-center gap-2"
                            >
                                <Tag
                                    class="h-4 w-4 text-emerald-600"
                                />
                                {{
                                    primaryCategory.name
                                }}
                            </span>

                            <span
                                v-if="
                                    locationLabel
                                "
                                class="inline-flex items-center gap-2"
                            >
                                <MapPin
                                    class="h-4 w-4 text-emerald-600"
                                />
                                {{
                                    locationLabel
                                }}
                            </span>
                        </div>

                        <p
                            v-if="
                                business.shortDescription
                            "
                            class="mt-5 max-w-3xl text-base leading-7 text-slate-600"
                        >
                            {{
                                business.shortDescription
                            }}
                        </p>

                        <!-- No fabricated gallery -->
                        <div
                            class="mt-7 flex min-h-[280px] items-center justify-center overflow-hidden rounded-2xl border border-emerald-100 bg-gradient-to-br from-emerald-50 via-white to-teal-50"
                        >
                            <div
                                class="max-w-md p-8 text-center"
                            >
                                <div
                                    class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-white shadow-sm"
                                >
                                    <Building2
                                        class="h-8 w-8 text-emerald-600"
                                    />
                                </div>

                                <h2
                                    class="mt-5 text-lg font-black text-slate-900"
                                >
                                    Business photos
                                    not published
                                </h2>

                                <p
                                    class="mt-2 text-sm leading-6 text-slate-500"
                                >
                                    We only display
                                    genuine business
                                    photographs when
                                    they are supplied
                                    or sourced with
                                    appropriate
                                    provenance.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Contact card -->
                    <aside
                        class="h-fit rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                    >
                        <h2
                            class="text-lg font-black text-slate-900"
                        >
                            Contact Business
                        </h2>

                        <div
                            class="mt-5 grid gap-3"
                        >
                            <a
                                v-if="
                                    phoneHref
                                "
                                :href="phoneHref"
                                class="flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 py-3.5 text-sm font-black text-white hover:bg-emerald-700"
                            >
                                <Phone
                                    class="h-5 w-5"
                                />
                                Call Business
                            </a>

                            <a
                                v-if="
                                    business.whatsappUrl
                                "
                                :href="
                                    business.whatsappUrl
                                "
                                target="_blank"
                                rel="noopener noreferrer"
                                class="flex items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-3.5 text-sm font-black text-emerald-800 hover:bg-emerald-100"
                            >
                                <MessageCircle
                                    class="h-5 w-5"
                                />
                                WhatsApp
                            </a>

                            <a
                                v-if="
                                    emailHref
                                "
                                :href="emailHref"
                                class="flex items-center justify-center gap-2 rounded-xl border border-slate-200 px-5 py-3.5 text-sm font-black text-slate-700 hover:bg-slate-50"
                            >
                                <Mail
                                    class="h-5 w-5"
                                />
                                Send Email
                            </a>

                            <a
                                v-if="
                                    business.websiteUrl
                                "
                                :href="
                                    business.websiteUrl
                                "
                                target="_blank"
                                rel="noopener noreferrer"
                                class="flex items-center justify-center gap-2 rounded-xl border border-slate-200 px-5 py-3.5 text-sm font-black text-slate-700 hover:bg-slate-50"
                            >
                                <ExternalLink
                                    class="h-5 w-5"
                                />
                                Visit Website
                            </a>

                            <div
                                v-if="
                                    !phoneHref
                                    && !business.whatsappUrl
                                    && !emailHref
                                    && !business.websiteUrl
                                "
                                class="rounded-xl bg-slate-50 px-4 py-4 text-sm leading-6 text-slate-500"
                            >
                                Public contact
                                details have not
                                been published for
                                this listing.
                            </div>
                        </div>

                        <div
                            v-if="
                                address
                            "
                            class="mt-6 border-t border-slate-200 pt-5"
                        >
                            <div
                                class="flex gap-3"
                            >
                                <MapPin
                                    class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600"
                                />

                                <div>
                                    <h3
                                        class="text-sm font-black text-slate-900"
                                    >
                                        Location
                                    </h3>

                                    <p
                                        class="mt-1 text-sm leading-6 text-slate-500"
                                    >
                                        {{
                                            address
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="
                                business.claimStatus
                                === 'unclaimed'
                            "
                            class="mt-6 border-t border-slate-200 pt-5"
                        >
                            <p
                                class="text-xs font-semibold leading-5 text-slate-500"
                            >
                                Own or represent
                                this business?
                            </p>

                            <Link
                                :href="`/business/${business.slug}/claim`"
                                class="mt-3 flex items-center justify-center rounded-xl bg-[#062c31] px-5 py-3 text-sm font-black text-white hover:bg-emerald-800"
                            >
                                Claim This Business
                            </Link>
                        </div>
                    </aside>
                </div>
            </section>

            <!-- Detail sections -->
            <section
                class="border-t border-slate-200 py-10"
            >
                <div
                    class="mx-auto grid max-w-[1500px] gap-7 px-5 sm:px-7 lg:grid-cols-[1fr_360px] lg:px-10"
                >
                    <div class="space-y-6">
                        <!-- About -->
                        <article
                            class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8"
                        >
                            <h2
                                class="text-2xl font-black text-slate-900"
                            >
                                About
                            </h2>

                            <p
                                v-if="
                                    business.description
                                "
                                class="mt-4 whitespace-pre-line text-sm leading-7 text-slate-600"
                            >
                                {{
                                    business.description
                                }}
                            </p>

                            <p
                                v-else-if="
                                    business.shortDescription
                                "
                                class="mt-4 text-sm leading-7 text-slate-600"
                            >
                                {{
                                    business.shortDescription
                                }}
                            </p>

                            <p
                                v-else
                                class="mt-4 text-sm leading-7 text-slate-500"
                            >
                                A detailed
                                description has not
                                yet been published
                                for this business.
                            </p>

                            <div
                                v-if="
                                    business.categories.length
                                "
                                class="mt-7 border-t border-slate-200 pt-6"
                            >
                                <h3
                                    class="text-sm font-black text-slate-900"
                                >
                                    Categories
                                </h3>

                                <div
                                    class="mt-3 flex flex-wrap gap-2"
                                >
                                    <a
                                        v-for="
                                            category
                                            in business.categories
                                        "
                                        :key="
                                            category.slug
                                        "
                                        :href="
                                            '/search?q=' +
                                            encodeURIComponent(
                                                category.name,
                                            )
                                        "
                                        class="rounded-full bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-800"
                                    >
                                        {{
                                            category.name
                                        }}
                                    </a>
                                </div>
                            </div>
                        </article>

                        <!-- Hours -->
                        <article
                            v-if="
                                business.location
                                ?.openingHours
                            "
                            class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8"
                        >
                            <div
                                class="flex items-center gap-3"
                            >
                                <Clock3
                                    class="h-6 w-6 text-emerald-600"
                                />

                                <h2
                                    class="text-2xl font-black text-slate-900"
                                >
                                    Opening Hours
                                </h2>
                            </div>

                            <dl
                                class="mt-6 divide-y divide-slate-100"
                            >
                                <div
                                    v-for="
                                        (
                                            hours,
                                            day
                                        )
                                        in business
                                            .location
                                            .openingHours
                                    "
                                    :key="
                                        String(day)
                                    "
                                    class="flex items-center justify-between gap-5 py-3 text-sm"
                                >
                                    <dt
                                        class="font-bold capitalize text-slate-700"
                                    >
                                        {{ day }}
                                    </dt>

                                    <dd
                                        class="text-right text-slate-500"
                                    >
                                        {{
                                            formatHours(
                                                hours,
                                            )
                                        }}
                                    </dd>
                                </div>
                            </dl>
                        </article>
                    </div>

                    <!-- Trust -->
                    <aside class="space-y-6">
                        <section
                            class="rounded-2xl border border-slate-200 bg-white p-6"
                        >
                            <div
                                class="flex items-center gap-3"
                            >
                                <ShieldCheck
                                    class="h-6 w-6 text-emerald-600"
                                />

                                <h2
                                    class="text-lg font-black text-slate-900"
                                >
                                    Listing Status
                                </h2>
                            </div>

                            <div
                                class="mt-5 rounded-xl bg-slate-50 p-4"
                            >
                                <div
                                    class="flex items-center gap-2 text-sm font-black"
                                    :class="
                                        business.isVerified
                                            ? 'text-emerald-700'
                                            : 'text-slate-700'
                                    "
                                >
                                    <CheckCircle2
                                        class="h-5 w-5"
                                    />

                                    {{
                                        business.listingLabel
                                    }}
                                </div>

                                <p
                                    class="mt-2 text-xs leading-5 text-slate-500"
                                >
                                    Verification
                                    is only shown
                                    after a real
                                    verification
                                    process has been
                                    completed.
                                </p>
                            </div>

                            <div
                                v-if="
                                    business.verificationTypes.length
                                "
                                class="mt-4"
                            >
                                <h3
                                    class="text-xs font-black uppercase tracking-wide text-slate-500"
                                >
                                    Verified signals
                                </h3>

                                <div
                                    class="mt-3 flex flex-wrap gap-2"
                                >
                                    <span
                                        v-for="
                                            type
                                            in business.verificationTypes
                                        "
                                        :key="type"
                                        class="rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700"
                                    >
                                        {{
                                            verificationLabel(
                                                type,
                                            )
                                        }}
                                    </span>
                                </div>
                            </div>
                        </section>

                        <section
                            class="rounded-2xl border border-slate-200 bg-white p-6"
                        >
                            <h2
                                class="text-lg font-black text-slate-900"
                            >
                                Listing Information
                            </h2>

                            <dl
                                class="mt-5 space-y-4 text-sm"
                            >
                                <div
                                    class="flex justify-between gap-5"
                                >
                                    <dt
                                        class="text-slate-500"
                                    >
                                        Data sources
                                    </dt>

                                    <dd
                                        class="font-black text-slate-800"
                                    >
                                        {{
                                            business.sourceCount
                                        }}
                                    </dd>
                                </div>

                                <div
                                    v-if="
                                        business.lastCheckedAt
                                    "
                                    class="flex justify-between gap-5"
                                >
                                    <dt
                                        class="text-slate-500"
                                    >
                                        Last checked
                                    </dt>

                                    <dd
                                        class="font-semibold text-slate-800"
                                    >
                                        {{
                                            business.lastCheckedAt
                                        }}
                                    </dd>
                                </div>

                                <div
                                    v-if="
                                        business.publishedAt
                                    "
                                    class="flex justify-between gap-5"
                                >
                                    <dt
                                        class="text-slate-500"
                                    >
                                        Published
                                    </dt>

                                    <dd
                                        class="font-semibold text-slate-800"
                                    >
                                        {{
                                            business.publishedAt
                                        }}
                                    </dd>
                                </div>
                            </dl>

                            <div
                                v-if="
                                    business.sourceNames.length
                                "
                                class="mt-5 border-t border-slate-100 pt-4"
                            >
                                <p
                                    class="text-xs font-semibold leading-5 text-slate-500"
                                >
                                    Sources:
                                    {{
                                        business.sourceNames.join(
                                            ', ',
                                        )
                                    }}
                                </p>
                            </div>
                        </section>
                    </aside>
                </div>
            </section>
        </main>

        <PublicFooter />
    </div>
</template>
