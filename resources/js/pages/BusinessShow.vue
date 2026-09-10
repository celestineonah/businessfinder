<script setup lang="ts">
import {
    Head,
    Link,
    useForm,
    usePage,
} from '@inertiajs/vue3';
import {
    BadgeCheck,
    Building2,
    CheckCircle2,
    ChevronRight,
    Clock3,
    ExternalLink,
    MapPin,
    MessageCircle,
    Phone,
    Send,
    ShieldCheck,
    Sparkles,
    Star,
    Tag,
    UserCheck,
} from 'lucide-vue-next';
import {
    computed,
    ref,
} from 'vue';

import PublicFooter from '@/components/public/PublicFooter.vue';
import PublicHeader from '@/components/public/PublicHeader.vue';

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

type RatingDistribution = {
    rating: number;
    count: number;
    percent: number;
};

type PublicReview = {
    id: number;
    rating: number;
    title: string | null;
    body: string;
    publishedAt: string | null;
    reviewerName: string;
};

type UserReview = {
    rating: number;
    title: string | null;
    body: string;
    status: string;
    moderationNotes: string | null;
    updatedAt: string | null;
};

type BusinessEngagement = {
    summary: {
        count: number;
        average: number | null;
        distribution: RatingDistribution[];
    };
    reviews: PublicReview[];
    userReview: UserReview | null;
    canReview: boolean;
    reviewReason: string | null;
    isOwner: boolean;
    managedEnquiries: boolean;
};

const props = defineProps<{
    business: BusinessProfile;
    canonicalUrl: string;
    businessEngagement?: BusinessEngagement | null;
}>();

const page = usePage();
const reviewPanelOpen = ref(props.businessEngagement?.userReview?.status === 'rejected');

const emptyEngagement: BusinessEngagement = {
    summary: {
        count: 0,
        average: null,
        distribution: [5, 4, 3, 2, 1].map((rating) => ({
            rating,
            count: 0,
            percent: 0,
        })),
    },
    reviews: [],
    userReview: null,
    canReview: false,
    reviewReason: null,
    isOwner: false,
    managedEnquiries: false,
};

const engagement = computed(
    () => props.businessEngagement ?? emptyEngagement,
);

const authUser = computed(
    () => (page.props.auth as any)?.user ?? null,
);

const flashStatus = computed(
    () => (page.props.flash as any)?.status ?? null,
);

const flashWhatsappUrl = computed(
    () => (page.props.flash as any)?.enquiryWhatsAppUrl ?? null,
);

const primaryCategory = computed(
    () =>
        props.business.categories.find(
            (category) => category.isPrimary,
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
        (value): value is string => Boolean(value),
    );

    return [...new Set(values)].join(', ');
});

const address = computed(() => {
    const location = props.business.location;

    if (! location) {
        return null;
    }

    const values = [
        location.addressLine1,
        location.addressLine2,
        location.landmark,
        location.area?.name,
        location.city?.name,
        location.lga?.name,
        location.state?.name,
    ].filter(
        (value): value is string => Boolean(value),
    );

    return [...new Set(values)].join(', ');
});

const phoneHref = computed(() =>
    props.business.phone
        ? 'tel:' + props.business.phone.replace(/\s+/g, '')
        : null,
);

const emailHref = computed(() =>
    props.business.email
        ? 'mailto:' + props.business.email
        : null,
);

const directWhatsappUrl = computed(() => {
    if (! props.business.whatsappUrl) {
        return null;
    }

    const text = encodeURIComponent(
        'Hello, I found your business on BusinessFinder Nigeria and would like to make an enquiry.',
    );

    return `${props.business.whatsappUrl}?text=${text}`;
});

const directionsUrl = computed(() => {
    const location = props.business.location;

    if (
        location?.latitude !== null
        && location?.latitude !== undefined
        && location?.longitude !== null
        && location?.longitude !== undefined
    ) {
        return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(
            `${location.latitude},${location.longitude}`,
        )}`;
    }

    if (address.value) {
        return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(
            address.value,
        )}`;
    }

    return null;
});

const reviewForm = useForm({
    rating: props.businessEngagement?.userReview?.rating ?? 5,
    title: props.businessEngagement?.userReview?.title ?? '',
    body: props.businessEngagement?.userReview?.body ?? '',
});

const enquiryForm = useForm({
    request_type: 'quote',
    contact_name: authUser.value?.name ?? '',
    contact_email: authUser.value?.email ?? '',
    contact_phone: '',
    preferred_channel: 'whatsapp',
    message: '',
    consent: false,
    website: '',
});

function submitReview() {
    reviewForm.post(
        `/business/${props.business.slug}/review`,
        {
            preserveScroll: true,
            onSuccess: () => {
                reviewPanelOpen.value = true;
            },
        },
    );
}

function submitEnquiry() {
    enquiryForm.post(
        `/business/${props.business.slug}/enquiry`,
        {
            preserveScroll: true,
            onSuccess: () => {
                enquiryForm.message = '';
                enquiryForm.consent = false;
            },
        },
    );
}

function formatDate(value: string | null): string {
    if (! value) {
        return '';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat(
        'en-NG',
        {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        },
    ).format(date);
}

function formatHours(value: unknown): string {
    if (
        value === null
        || value === undefined
    ) {
        return 'Not published';
    }

    if (typeof value === 'string') {
        return value;
    }

    if (Array.isArray(value)) {
        return value.map(String).join(', ');
    }

    if (typeof value === 'object') {
        return Object.values(
            value as Record<string, unknown>,
        )
            .map(String)
            .join(' – ');
    }

    return String(value);
}

function verificationLabel(type: string): string {
    const labels: Record<string, string> = {
        phone: 'Phone',
        email: 'Email',
        cac: 'CAC',
        address: 'Address',
        website: 'Website',
        representative: 'Representative',
    };

    return labels[type] ?? type;
}

function reviewStatusLabel(value: string): string {
    if (value === 'approved') {
        return 'Published';
    }

    if (value === 'rejected') {
        return 'Needs changes';
    }

    return 'Under moderation';
}
</script>

<template>
    <Head :title="business.name">
        <meta
            v-if="business.shortDescription || business.description"
            name="description"
            :content="business.shortDescription || business.description || ''"
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

    <div class="min-h-screen bg-[#f7f9f8] text-[#062c31]">
        <PublicHeader active="businesses" />

        <main>
            <div class="border-b border-slate-200 bg-white">
                <div
                    class="mx-auto flex max-w-[1500px] items-center gap-2 overflow-hidden px-5 py-4 text-xs font-semibold text-slate-500 sm:px-7 lg:px-10"
                >
                    <a
                        href="/"
                        class="shrink-0 hover:text-emerald-700"
                    >
                        Home
                    </a>

                    <ChevronRight class="h-3.5 w-3.5 shrink-0" />

                    <a
                        href="/search"
                        class="shrink-0 hover:text-emerald-700"
                    >
                        Businesses
                    </a>

                    <ChevronRight class="h-3.5 w-3.5 shrink-0" />

                    <span class="truncate text-slate-800">
                        {{ business.name }}
                    </span>
                </div>
            </div>

            <section class="border-b border-slate-200 bg-white">
                <div
                    class="mx-auto max-w-[1500px] px-5 py-8 sm:px-7 lg:px-10 lg:py-11"
                >
                    <div
                        v-if="flashStatus"
                        class="mb-7 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-900"
                    >
                        <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0" />
                        <div class="flex-1">
                            <p>{{ flashStatus }}</p>

                            <a
                                v-if="flashWhatsappUrl"
                                :href="flashWhatsappUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mt-3 inline-flex items-center gap-2 rounded-xl bg-[#078844] px-4 py-2.5 text-xs font-black text-white transition hover:bg-emerald-700"
                            >
                                <MessageCircle class="h-4 w-4" />
                                Continue on WhatsApp
                            </a>
                        </div>
                    </div>

                    <div
                        class="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_auto]"
                    >
                        <div class="flex min-w-0 gap-5 sm:gap-7">
                            <div
                                class="flex h-20 w-20 shrink-0 items-center justify-center rounded-3xl bg-gradient-to-br from-emerald-100 to-emerald-50 text-3xl font-black text-emerald-700 ring-1 ring-emerald-200 sm:h-24 sm:w-24"
                            >
                                {{ business.name.charAt(0).toUpperCase() }}
                            </div>

                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        v-if="business.isVerified"
                                        class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-[11px] font-black uppercase tracking-[0.08em] text-emerald-700 ring-1 ring-emerald-200"
                                    >
                                        <BadgeCheck class="h-4 w-4" />
                                        Verified Business
                                    </span>

                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-[11px] font-black uppercase tracking-[0.08em] text-slate-600"
                                    >
                                        <Building2 class="h-4 w-4" />
                                        Listed Business
                                    </span>

                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full bg-[#eefaf3] px-3 py-1 text-[11px] font-black uppercase tracking-[0.08em] text-[#078844]"
                                    >
                                        <Sparkles class="h-3.5 w-3.5" />
                                        AI discovery coming soon
                                    </span>
                                </div>

                                <h1
                                    class="mt-4 max-w-4xl text-3xl font-black tracking-[-0.045em] text-[#062c31] sm:text-4xl lg:text-[46px] lg:leading-[1.06]"
                                >
                                    {{ business.name }}
                                </h1>

                                <div
                                    class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm font-semibold text-slate-600"
                                >
                                    <span
                                        v-if="primaryCategory"
                                        class="inline-flex items-center gap-2"
                                    >
                                        <Tag class="h-4 w-4 text-emerald-600" />
                                        {{ primaryCategory.name }}
                                    </span>

                                    <span
                                        v-if="locationLabel"
                                        class="inline-flex items-center gap-2"
                                    >
                                        <MapPin class="h-4 w-4 text-emerald-600" />
                                        {{ locationLabel }}
                                    </span>

                                    <span
                                        v-if="engagement.summary.count > 0"
                                        class="inline-flex items-center gap-2"
                                    >
                                        <Star
                                            class="h-4 w-4 text-amber-500"
                                            fill="currentColor"
                                        />
                                        <strong class="text-slate-900">
                                            {{ engagement.summary.average }}
                                        </strong>
                                        · {{ engagement.summary.count }}
                                        {{ engagement.summary.count === 1 ? 'review' : 'reviews' }}
                                    </span>
                                </div>

                                <p
                                    v-if="business.shortDescription"
                                    class="mt-5 max-w-3xl text-[15px] leading-7 text-slate-600"
                                >
                                    {{ business.shortDescription }}
                                </p>
                            </div>
                        </div>

                        <div
                            class="grid min-w-[260px] grid-cols-2 gap-3 sm:flex sm:flex-wrap lg:max-w-[430px] lg:justify-end"
                        >
                            <a
                                v-if="phoneHref"
                                :href="phoneHref"
                                class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-black text-slate-800 shadow-sm transition hover:border-emerald-300 hover:text-emerald-700"
                            >
                                <Phone class="h-4 w-4" />
                                Call
                            </a>

                            <a
                                v-if="directWhatsappUrl"
                                :href="directWhatsappUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#078844] px-4 py-3 text-sm font-black text-white shadow-sm transition hover:bg-emerald-700"
                            >
                                <MessageCircle class="h-4 w-4" />
                                WhatsApp
                            </a>

                            <a
                                v-if="business.websiteUrl"
                                :href="business.websiteUrl"
                                target="_blank"
                                rel="noopener noreferrer nofollow"
                                class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-black text-slate-800 shadow-sm transition hover:border-emerald-300 hover:text-emerald-700"
                            >
                                <ExternalLink class="h-4 w-4" />
                                Website
                            </a>

                            <a
                                href="#enquiry"
                                class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#062c31] px-4 py-3 text-sm font-black text-white shadow-sm transition hover:bg-[#0a3e45]"
                            >
                                <Send class="h-4 w-4" />
                                Request quote
                            </a>
                        </div>
                    </div>
                </div>
            </section>

            <section>
                <div
                    class="mx-auto grid max-w-[1500px] gap-7 px-5 py-8 sm:px-7 lg:grid-cols-[minmax(0,1fr)_390px] lg:px-10 lg:py-10"
                >
                    <div class="space-y-7">
                        <article
                            class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700"
                                >
                                    <Building2 class="h-5 w-5" />
                                </div>

                                <div>
                                    <p
                                        class="text-[10px] font-black uppercase tracking-[0.18em] text-emerald-700"
                                    >
                                        Business profile
                                    </p>

                                    <h2
                                        class="text-xl font-black tracking-[-0.025em]"
                                    >
                                        About {{ business.name }}
                                    </h2>
                                </div>
                            </div>

                            <div
                                v-if="business.description || business.shortDescription"
                                class="mt-6 whitespace-pre-line text-[15px] leading-7 text-slate-600"
                            >
                                {{ business.description || business.shortDescription }}
                            </div>

                            <p
                                v-else
                                class="mt-6 rounded-2xl bg-slate-50 px-5 py-4 text-sm leading-6 text-slate-500"
                            >
                                This listing does not currently have a published business description.
                                BusinessFinder does not invent business details.
                            </p>

                            <div
                                v-if="business.categories.length"
                                class="mt-7 border-t border-slate-100 pt-6"
                            >
                                <p
                                    class="text-xs font-black uppercase tracking-[0.12em] text-slate-400"
                                >
                                    Categories
                                </p>

                                <div class="mt-3 flex flex-wrap gap-2">
                                    <span
                                        v-for="category in business.categories"
                                        :key="category.slug"
                                        class="rounded-full bg-slate-100 px-3.5 py-2 text-xs font-bold text-slate-700"
                                    >
                                        {{ category.name }}
                                    </span>
                                </div>
                            </div>
                        </article>

                        <article
                            class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700"
                                    >
                                        <MapPin class="h-5 w-5" />
                                    </div>

                                    <div>
                                        <p
                                            class="text-[10px] font-black uppercase tracking-[0.18em] text-emerald-700"
                                        >
                                            Location & contact
                                        </p>

                                        <h2
                                            class="text-xl font-black tracking-[-0.025em]"
                                        >
                                            Business information
                                        </h2>
                                    </div>
                                </div>

                                <a
                                    v-if="directionsUrl"
                                    :href="directionsUrl"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-black text-slate-700 transition hover:border-emerald-300 hover:text-emerald-700"
                                >
                                    <MapPin class="h-4 w-4" />
                                    Directions
                                </a>
                            </div>

                            <dl class="mt-7 grid gap-5 sm:grid-cols-2">
                                <div
                                    v-if="address"
                                    class="rounded-2xl bg-slate-50 p-5"
                                >
                                    <dt
                                        class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400"
                                    >
                                        Address
                                    </dt>

                                    <dd class="mt-2 text-sm font-bold leading-6 text-slate-800">
                                        {{ address }}
                                    </dd>
                                </div>

                                <div
                                    v-if="business.phone"
                                    class="rounded-2xl bg-slate-50 p-5"
                                >
                                    <dt
                                        class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400"
                                    >
                                        Phone
                                    </dt>

                                    <dd class="mt-2">
                                        <a
                                            :href="phoneHref || undefined"
                                            class="text-sm font-black text-emerald-700 hover:underline"
                                        >
                                            {{ business.phone }}
                                        </a>
                                    </dd>
                                </div>

                                <div
                                    v-if="business.email"
                                    class="rounded-2xl bg-slate-50 p-5"
                                >
                                    <dt
                                        class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400"
                                    >
                                        Email
                                    </dt>

                                    <dd class="mt-2">
                                        <a
                                            :href="emailHref || undefined"
                                            class="break-all text-sm font-black text-emerald-700 hover:underline"
                                        >
                                            {{ business.email }}
                                        </a>
                                    </dd>
                                </div>

                                <div
                                    v-if="business.location?.openingHours"
                                    class="rounded-2xl bg-slate-50 p-5"
                                >
                                    <dt
                                        class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.14em] text-slate-400"
                                    >
                                        <Clock3 class="h-3.5 w-3.5" />
                                        Opening hours
                                    </dt>

                                    <dd class="mt-2 text-sm font-bold leading-6 text-slate-800">
                                        {{ formatHours(business.location.openingHours) }}
                                    </dd>
                                </div>
                            </dl>

                            <p
                                v-if="!address && !business.phone && !business.email && !business.location?.openingHours"
                                class="mt-6 rounded-2xl bg-slate-50 px-5 py-4 text-sm text-slate-500"
                            >
                                No additional contact or location details are currently published.
                            </p>
                        </article>

                        <article
                            id="reviews"
                            class="scroll-mt-24 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8"
                        >
                            <div
                                class="flex flex-col justify-between gap-5 sm:flex-row sm:items-start"
                            >
                                <div>
                                    <p
                                        class="text-[10px] font-black uppercase tracking-[0.18em] text-emerald-700"
                                    >
                                        Real customer feedback
                                    </p>

                                    <h2
                                        class="mt-1 text-2xl font-black tracking-[-0.035em]"
                                    >
                                        Reviews
                                    </h2>

                                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                                        Only approved BusinessFinder community reviews are included
                                        in the public score. Pending and rejected reviews are never counted.
                                    </p>
                                </div>

                                <button
                                    v-if="engagement.canReview"
                                    type="button"
                                    class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-[#062c31] px-5 py-3 text-sm font-black text-white transition hover:bg-[#0a3e45]"
                                    @click="reviewPanelOpen = !reviewPanelOpen"
                                >
                                    <Star class="h-4 w-4" />
                                    {{ engagement.userReview ? 'Edit your review' : 'Write a review' }}
                                </button>
                            </div>

                            <div
                                v-if="engagement.summary.count > 0"
                                class="mt-7 grid gap-6 rounded-2xl bg-[#f7faf8] p-5 sm:grid-cols-[170px_minmax(0,1fr)] sm:p-6"
                            >
                                <div
                                    class="flex flex-col items-center justify-center rounded-2xl bg-white p-5 text-center shadow-sm"
                                >
                                    <div
                                        class="text-5xl font-black tracking-[-0.06em] text-[#062c31]"
                                    >
                                        {{ engagement.summary.average }}
                                    </div>

                                    <div class="mt-2 flex gap-0.5 text-amber-500">
                                        <Star
                                            v-for="index in 5"
                                            :key="index"
                                            class="h-4 w-4"
                                            :fill="index <= Math.round(engagement.summary.average || 0) ? 'currentColor' : 'none'"
                                        />
                                    </div>

                                    <p class="mt-2 text-xs font-bold text-slate-500">
                                        {{ engagement.summary.count }}
                                        {{ engagement.summary.count === 1 ? 'review' : 'reviews' }}
                                    </p>
                                </div>

                                <div class="space-y-2.5">
                                    <div
                                        v-for="row in engagement.summary.distribution"
                                        :key="row.rating"
                                        class="grid grid-cols-[30px_minmax(0,1fr)_40px] items-center gap-3 text-xs"
                                    >
                                        <span class="font-black text-slate-600">
                                            {{ row.rating }}
                                        </span>

                                        <div class="h-2 overflow-hidden rounded-full bg-slate-200">
                                            <div
                                                class="h-full rounded-full bg-amber-400"
                                                :style="{ width: `${row.percent}%` }"
                                            />
                                        </div>

                                        <span class="text-right font-bold text-slate-400">
                                            {{ row.count }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-else
                                class="mt-7 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-8 text-center"
                            >
                                <Star class="mx-auto h-8 w-8 text-slate-300" />
                                <h3 class="mt-3 text-base font-black text-slate-700">
                                    No published reviews yet
                                </h3>
                                <p class="mx-auto mt-2 max-w-lg text-sm leading-6 text-slate-500">
                                    BusinessFinder starts every listing at zero. Ratings appear only
                                    after real users submit reviews and moderation approves them.
                                </p>
                            </div>

                            <div
                                v-if="engagement.userReview"
                                class="mt-6 rounded-2xl border border-slate-200 bg-white p-5"
                            >
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <p class="text-xs font-black uppercase tracking-[0.12em] text-slate-400">
                                            Your review
                                        </p>
                                        <p class="mt-1 text-sm font-black text-slate-800">
                                            {{ reviewStatusLabel(engagement.userReview.status) }}
                                        </p>
                                    </div>

                                    <span
                                        :class="[
                                            'rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-[0.08em]',
                                            engagement.userReview.status === 'approved'
                                                ? 'bg-emerald-50 text-emerald-700'
                                                : engagement.userReview.status === 'rejected'
                                                    ? 'bg-rose-50 text-rose-700'
                                                    : 'bg-amber-50 text-amber-700',
                                        ]"
                                    >
                                        {{ engagement.userReview.status }}
                                    </span>
                                </div>

                                <p
                                    v-if="engagement.userReview.moderationNotes"
                                    class="mt-3 rounded-xl bg-rose-50 px-4 py-3 text-xs font-semibold leading-5 text-rose-700"
                                >
                                    Moderation note: {{ engagement.userReview.moderationNotes }}
                                </p>
                            </div>

                            <form
                                v-if="engagement.canReview && reviewPanelOpen"
                                class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50/40 p-5 sm:p-6"
                                @submit.prevent="submitReview"
                            >
                                <div class="flex flex-wrap items-center justify-between gap-4">
                                    <div>
                                        <h3 class="text-base font-black">
                                            {{ engagement.userReview ? 'Update your review' : 'Write your review' }}
                                        </h3>
                                        <p class="mt-1 text-xs font-semibold text-slate-500">
                                            Any edit returns the review to moderation.
                                        </p>
                                    </div>

                                    <div class="flex items-center gap-1">
                                        <button
                                            v-for="index in 5"
                                            :key="index"
                                            type="button"
                                            class="rounded-lg p-1 text-amber-500 transition hover:bg-white"
                                            :aria-label="`Rate ${index} out of 5`"
                                            @click="reviewForm.rating = index"
                                        >
                                            <Star
                                                class="h-7 w-7"
                                                :fill="index <= reviewForm.rating ? 'currentColor' : 'none'"
                                            />
                                        </button>
                                    </div>
                                </div>

                                <p
                                    v-if="reviewForm.errors.rating"
                                    class="mt-2 text-xs font-bold text-rose-600"
                                >
                                    {{ reviewForm.errors.rating }}
                                </p>

                                <div class="mt-5 grid gap-4">
                                    <label class="grid gap-2">
                                        <span class="text-xs font-black text-slate-700">
                                            Review title
                                            <span class="font-semibold text-slate-400">(optional)</span>
                                        </span>
                                        <input
                                            v-model="reviewForm.title"
                                            type="text"
                                            maxlength="120"
                                            class="h-12 rounded-xl border border-slate-200 bg-white px-4 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                                            placeholder="Summarise your experience"
                                        />
                                        <span
                                            v-if="reviewForm.errors.title"
                                            class="text-xs font-bold text-rose-600"
                                        >
                                            {{ reviewForm.errors.title }}
                                        </span>
                                    </label>

                                    <label class="grid gap-2">
                                        <span class="text-xs font-black text-slate-700">
                                            Your experience
                                        </span>
                                        <textarea
                                            v-model="reviewForm.body"
                                            rows="5"
                                            maxlength="3000"
                                            class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm leading-6 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                                            placeholder="Share useful, first-hand details. Do not include private information."
                                        />
                                        <span
                                            v-if="reviewForm.errors.body"
                                            class="text-xs font-bold text-rose-600"
                                        >
                                            {{ reviewForm.errors.body }}
                                        </span>
                                    </label>
                                </div>

                                <div class="mt-5 flex flex-wrap items-center gap-3">
                                    <button
                                        type="submit"
                                        :disabled="reviewForm.processing"
                                        class="inline-flex items-center gap-2 rounded-xl bg-[#078844] px-5 py-3 text-sm font-black text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                                    >
                                        <Send class="h-4 w-4" />
                                        {{ reviewForm.processing ? 'Submitting…' : 'Submit for moderation' }}
                                    </button>

                                    <button
                                        type="button"
                                        class="rounded-xl px-4 py-3 text-sm font-black text-slate-500 hover:bg-white"
                                        @click="reviewPanelOpen = false"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </form>

                            <div
                                v-else-if="engagement.reviewReason && !engagement.userReview"
                                class="mt-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-slate-50 px-5 py-4"
                            >
                                <p class="text-sm font-semibold text-slate-600">
                                    {{ engagement.reviewReason }}
                                </p>

                                <Link
                                    v-if="!authUser"
                                    href="/login"
                                    class="rounded-xl bg-[#062c31] px-4 py-2.5 text-xs font-black text-white"
                                >
                                    Sign in
                                </Link>

                                <Link
                                    v-else-if="!authUser.email_verified_at"
                                    href="/email/verify"
                                    class="rounded-xl bg-[#062c31] px-4 py-2.5 text-xs font-black text-white"
                                >
                                    Verify email
                                </Link>
                            </div>

                            <div
                                v-if="engagement.reviews.length"
                                class="mt-7 grid gap-4"
                            >
                                <article
                                    v-for="review in engagement.reviews"
                                    :key="review.id"
                                    class="rounded-2xl border border-slate-200 p-5 sm:p-6"
                                >
                                    <div
                                        class="flex flex-wrap items-start justify-between gap-3"
                                    >
                                        <div>
                                            <p class="font-black text-slate-800">
                                                {{ review.reviewerName }}
                                            </p>

                                            <p class="mt-1 text-xs font-semibold text-slate-400">
                                                {{ formatDate(review.publishedAt) }}
                                            </p>
                                        </div>

                                        <div class="flex gap-0.5 text-amber-500">
                                            <Star
                                                v-for="index in 5"
                                                :key="index"
                                                class="h-4 w-4"
                                                :fill="index <= review.rating ? 'currentColor' : 'none'"
                                            />
                                        </div>
                                    </div>

                                    <h3
                                        v-if="review.title"
                                        class="mt-4 text-sm font-black text-slate-900"
                                    >
                                        {{ review.title }}
                                    </h3>

                                    <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-600">
                                        {{ review.body }}
                                    </p>
                                </article>
                            </div>
                        </article>

                        <article
                            class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-100 text-slate-700"
                                >
                                    <ShieldCheck class="h-5 w-5" />
                                </div>

                                <div>
                                    <p
                                        class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400"
                                    >
                                        Listing transparency
                                    </p>

                                    <h2
                                        class="text-xl font-black tracking-[-0.025em]"
                                    >
                                        Trust & source information
                                    </h2>
                                </div>
                            </div>

                            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                                <div class="rounded-2xl bg-slate-50 p-5">
                                    <p class="text-xs font-black text-slate-700">
                                        Listing status
                                    </p>

                                    <div class="mt-2 flex items-center gap-2 text-sm font-bold text-slate-600">
                                        <CheckCircle2 class="h-4 w-4 text-emerald-600" />
                                        {{ business.listingLabel }}
                                    </div>
                                </div>

                                <div class="rounded-2xl bg-slate-50 p-5">
                                    <p class="text-xs font-black text-slate-700">
                                        Data sources
                                    </p>

                                    <p class="mt-2 text-sm font-bold text-slate-600">
                                        {{ business.sourceCount }}
                                        active
                                        {{ business.sourceCount === 1 ? 'source' : 'sources' }}
                                    </p>
                                </div>
                            </div>

                            <div
                                v-if="business.isVerified && business.verificationTypes.length"
                                class="mt-5"
                            >
                                <p class="text-xs font-black text-slate-700">
                                    Verified evidence
                                </p>

                                <div class="mt-3 flex flex-wrap gap-2">
                                    <span
                                        v-for="type in business.verificationTypes"
                                        :key="type"
                                        class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-black text-emerald-700"
                                    >
                                        <BadgeCheck class="h-3.5 w-3.5" />
                                        {{ verificationLabel(type) }}
                                    </span>
                                </div>
                            </div>

                            <p
                                v-if="business.lastCheckedAt"
                                class="mt-5 text-xs font-semibold text-slate-400"
                            >
                                Listing data last checked {{ formatDate(business.lastCheckedAt) }}.
                            </p>
                        </article>
                    </div>

                    <aside class="space-y-6 lg:sticky lg:top-6 lg:self-start">
                        <section
                            id="enquiry"
                            class="scroll-mt-24 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"
                        >
                            <div
                                class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700"
                            >
                                <Send class="h-5 w-5" />
                            </div>

                            <p
                                class="mt-4 text-[10px] font-black uppercase tracking-[0.18em] text-emerald-700"
                            >
                                BusinessFinder lead request
                            </p>

                            <h2
                                class="mt-1 text-2xl font-black tracking-[-0.035em]"
                            >
                                Ask this business
                            </h2>

                            <p class="mt-2 text-sm leading-6 text-slate-500">
                                Send an enquiry or request a quote. BusinessFinder records every
                                submission so claimed owners can manage genuine leads.
                            </p>

                            <div
                                class="mt-4 rounded-xl px-4 py-3 text-xs font-bold leading-5"
                                :class="engagement.managedEnquiries
                                    ? 'bg-emerald-50 text-emerald-700'
                                    : 'bg-amber-50 text-amber-800'"
                            >
                                <template v-if="engagement.managedEnquiries">
                                    This listing is claimed. Its owner can receive this lead in the
                                    BusinessFinder dashboard.
                                </template>
                                <template v-else>
                                    This listing is not yet connected to a claimed owner dashboard.
                                    BusinessFinder will record the enquiry and platform support can review it.
                                </template>
                            </div>

                            <form
                                class="mt-5 grid gap-4"
                                @submit.prevent="submitEnquiry"
                            >
                                <div class="grid grid-cols-2 gap-2">
                                    <button
                                        type="button"
                                        :class="[
                                            'rounded-xl border px-3 py-2.5 text-xs font-black transition',
                                            enquiryForm.request_type === 'quote'
                                                ? 'border-emerald-600 bg-emerald-50 text-emerald-700'
                                                : 'border-slate-200 text-slate-600 hover:border-emerald-300',
                                        ]"
                                        @click="enquiryForm.request_type = 'quote'"
                                    >
                                        Request quote
                                    </button>

                                    <button
                                        type="button"
                                        :class="[
                                            'rounded-xl border px-3 py-2.5 text-xs font-black transition',
                                            enquiryForm.request_type === 'general'
                                                ? 'border-emerald-600 bg-emerald-50 text-emerald-700'
                                                : 'border-slate-200 text-slate-600 hover:border-emerald-300',
                                        ]"
                                        @click="enquiryForm.request_type = 'general'"
                                    >
                                        General enquiry
                                    </button>
                                </div>

                                <label class="grid gap-1.5">
                                    <span class="text-xs font-black text-slate-700">
                                        Your name
                                    </span>

                                    <input
                                        v-model="enquiryForm.contact_name"
                                        type="text"
                                        autocomplete="name"
                                        class="h-11 rounded-xl border border-slate-200 px-3.5 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                                        placeholder="Full name"
                                    />

                                    <span
                                        v-if="enquiryForm.errors.contact_name"
                                        class="text-xs font-bold text-rose-600"
                                    >
                                        {{ enquiryForm.errors.contact_name }}
                                    </span>
                                </label>

                                <label class="grid gap-1.5">
                                    <span class="text-xs font-black text-slate-700">
                                        Email
                                    </span>

                                    <input
                                        v-model="enquiryForm.contact_email"
                                        type="email"
                                        autocomplete="email"
                                        class="h-11 rounded-xl border border-slate-200 px-3.5 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                                        placeholder="name@example.com"
                                    />

                                    <span
                                        v-if="enquiryForm.errors.contact_email"
                                        class="text-xs font-bold text-rose-600"
                                    >
                                        {{ enquiryForm.errors.contact_email }}
                                    </span>
                                </label>

                                <label class="grid gap-1.5">
                                    <span class="text-xs font-black text-slate-700">
                                        Phone / WhatsApp
                                    </span>

                                    <input
                                        v-model="enquiryForm.contact_phone"
                                        type="tel"
                                        autocomplete="tel"
                                        class="h-11 rounded-xl border border-slate-200 px-3.5 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                                        placeholder="+234..."
                                    />

                                    <span
                                        v-if="enquiryForm.errors.contact_phone"
                                        class="text-xs font-bold text-rose-600"
                                    >
                                        {{ enquiryForm.errors.contact_phone }}
                                    </span>
                                </label>

                                <label class="grid gap-1.5">
                                    <span class="text-xs font-black text-slate-700">
                                        Preferred reply
                                    </span>

                                    <select
                                        v-model="enquiryForm.preferred_channel"
                                        class="h-11 rounded-xl border border-slate-200 bg-white px-3.5 text-sm font-semibold outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                                    >
                                        <option value="whatsapp">
                                            WhatsApp
                                        </option>
                                        <option value="phone">
                                            Phone
                                        </option>
                                        <option value="email">
                                            Email
                                        </option>
                                    </select>
                                </label>

                                <label class="grid gap-1.5">
                                    <span class="text-xs font-black text-slate-700">
                                        What do you need?
                                    </span>

                                    <textarea
                                        v-model="enquiryForm.message"
                                        rows="5"
                                        maxlength="4000"
                                        class="rounded-xl border border-slate-200 px-3.5 py-3 text-sm leading-6 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                                        :placeholder="enquiryForm.request_type === 'quote'
                                            ? 'Describe the service, quantity, timing or budget details that will help the business quote accurately.'
                                            : 'Write your enquiry.'"
                                    />

                                    <span
                                        v-if="enquiryForm.errors.message"
                                        class="text-xs font-bold text-rose-600"
                                    >
                                        {{ enquiryForm.errors.message }}
                                    </span>
                                </label>

                                <div
                                    class="absolute left-[-10000px] top-auto h-px w-px overflow-hidden"
                                    aria-hidden="true"
                                >
                                    <label>
                                        Website
                                        <input
                                            v-model="enquiryForm.website"
                                            type="text"
                                            tabindex="-1"
                                            autocomplete="off"
                                        />
                                    </label>
                                </div>

                                <label class="flex items-start gap-3">
                                    <input
                                        v-model="enquiryForm.consent"
                                        type="checkbox"
                                        class="mt-1 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                    />

                                    <span class="text-[11px] font-semibold leading-5 text-slate-500">
                                        I agree that BusinessFinder can store these contact details,
                                        notify platform support when needed, and make them available
                                        to the business owner for this enquiry.
                                    </span>
                                </label>

                                <span
                                    v-if="enquiryForm.errors.consent"
                                    class="text-xs font-bold text-rose-600"
                                >
                                    {{ enquiryForm.errors.consent }}
                                </span>

                                <button
                                    type="submit"
                                    :disabled="enquiryForm.processing"
                                    class="inline-flex h-12 items-center justify-center gap-2 rounded-xl bg-[#078844] px-5 text-sm font-black text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    <Send class="h-4 w-4" />
                                    {{ enquiryForm.processing ? 'Sending…' : 'Send through BusinessFinder' }}
                                </button>
                            </form>
                        </section>

                        <section
                            class="rounded-3xl border border-slate-200 bg-[#062c31] p-6 text-white shadow-sm"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/10"
                                >
                                    <UserCheck class="h-5 w-5" />
                                </div>

                                <div>
                                    <p
                                        class="text-[10px] font-black uppercase tracking-[0.18em] text-emerald-300"
                                    >
                                        Ownership
                                    </p>

                                    <h2 class="text-lg font-black">
                                        {{ business.claimStatus === 'claimed'
                                            ? 'Claimed listing'
                                            : business.claimStatus === 'pending'
                                                ? 'Claim under review'
                                                : 'Is this your business?' }}
                                    </h2>
                                </div>
                            </div>

                            <p class="mt-4 text-sm leading-6 text-slate-300">
                                <template v-if="business.claimStatus === 'claimed'">
                                    This listing has an approved owner account. Verification status
                                    remains separate and depends on approved evidence.
                                </template>

                                <template v-else-if="business.claimStatus === 'pending'">
                                    A BusinessFinder ownership claim is currently under review.
                                </template>

                                <template v-else>
                                    Claim the listing to manage enquiries, business information and
                                    future owner features.
                                </template>
                            </p>

                            <Link
                                v-if="engagement.isOwner"
                                href="/dashboard"
                                class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-white px-4 py-3 text-sm font-black text-[#062c31]"
                            >
                                <Building2 class="h-4 w-4" />
                                Manage in dashboard
                            </Link>

                            <Link
                                v-else-if="business.claimStatus === 'unclaimed'"
                                :href="`/business/${business.slug}/claim`"
                                class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-white px-4 py-3 text-sm font-black text-[#062c31]"
                            >
                                <ShieldCheck class="h-4 w-4" />
                                Claim this business
                            </Link>
                        </section>

                        <section
                            class="rounded-3xl border border-emerald-200 bg-emerald-50 p-6"
                        >
                            <div class="flex items-start gap-3">
                                <ShieldCheck class="mt-0.5 h-5 w-5 shrink-0 text-emerald-700" />

                                <div>
                                    <h3 class="text-sm font-black text-emerald-900">
                                        Trust policy
                                    </h3>

                                    <p class="mt-2 text-xs font-semibold leading-5 text-emerald-800/80">
                                        Imported listings remain “Listed Business” until approved
                                        verification evidence exists. BusinessFinder does not fabricate
                                        ratings, reviews or verification.
                                    </p>
                                </div>
                            </div>
                        </section>
                    </aside>
                </div>
            </section>
        </main>

        <PublicFooter />
    </div>
</template>
