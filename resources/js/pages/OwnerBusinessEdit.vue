<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowLeft,
    CircleCheck,
    ExternalLink,
    Globe2,
    Mail,
    MapPin,
    Phone,
    Save,
    Send,
    ShieldCheck,
} from 'lucide-vue-next';
import { computed, watch } from 'vue';
import PublicFooter from '@/components/public/PublicFooter.vue';
import PublicHeader from '@/components/public/PublicHeader.vue';

type StateItem = { id: number; name: string; slug: string; code: string };
type LgaItem = {
    id: number;
    state_id: number;
    name: string;
    slug: string;
    administrative_type: string;
};
type CategoryItem = { id: number; name: string; slug: string; depth: number };
type Readiness = { ready: boolean; missing: string[] };
type PublicationRequest = {
    status: string;
    requestedAt: string | null;
    reviewNotes: string | null;
};

const props = defineProps<{
    business: {
        id: number;
        name: string;
        slug: string;
        legalName: string | null;
        cacNumber: string | null;
        primaryPhone: string | null;
        whatsappPhone: string | null;
        primaryEmail: string | null;
        websiteUrl: string | null;
        shortDescription: string | null;
        description: string | null;
        claimStatus: string;
        verificationStatus: string;
        listingStatus: string;
        isPublished: boolean;
        publicUrl: string | null;
        categoryId: number | null;
    };
    location: {
        id: number;
        stateId: number | null;
        lgaId: number | null;
        addressLine1: string | null;
        addressLine2: string | null;
        landmark: string | null;
        postalCode: string | null;
        serviceAreaOnly: boolean;
    } | null;
    states: StateItem[];
    lgas: LgaItem[];
    categories: CategoryItem[];
    readiness: Readiness;
    publicationRequest: PublicationRequest | null;
    status?: string | null;
}>();

const form = useForm({
    name: props.business.name,
    legal_name: props.business.legalName ?? '',
    cac_number: props.business.cacNumber ?? '',
    primary_phone: props.business.primaryPhone ?? '',
    whatsapp_phone: props.business.whatsappPhone ?? '',
    primary_email: props.business.primaryEmail ?? '',
    website_url: props.business.websiteUrl ?? '',
    short_description: props.business.shortDescription ?? '',
    description: props.business.description ?? '',
    category_id: props.business.categoryId ? String(props.business.categoryId) : '',
    state_id: props.location?.stateId ? String(props.location.stateId) : '',
    lga_id: props.location?.lgaId ? String(props.location.lgaId) : '',
    address_line_1: props.location?.addressLine1 ?? '',
    address_line_2: props.location?.addressLine2 ?? '',
    landmark: props.location?.landmark ?? '',
    postal_code: props.location?.postalCode ?? '',
    service_area_only: props.location?.serviceAreaOnly ?? false,
});

const availableLgas = computed(() => {
    const stateId = Number(form.state_id);
    return stateId
        ? props.lgas.filter((lga) => Number(lga.state_id) === stateId)
        : [];
});

watch(
    () => form.state_id,
    () => {
        if (
            form.lga_id
            && ! availableLgas.value.some(
                (lga) => String(lga.id) === String(form.lga_id),
            )
        ) {
            form.lga_id = '';
        }
    },
);

function submit() {
    form.patch(`/dashboard/businesses/${props.business.id}`, {
        preserveScroll: true,
    });
}

function requestPublication() {
    router.post(
        `/dashboard/businesses/${props.business.id}/publication-request`,
        {},
        { preserveScroll: true },
    );
}

function requestClass(status: string): string {
    if (status === 'approved') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-800';
    }
    if (status === 'rejected') {
        return 'border-rose-200 bg-rose-50 text-rose-800';
    }
    return 'border-amber-200 bg-amber-50 text-amber-800';
}
</script>

<template>
    <Head :title="`Manage ${business.name}`" />

    <div class="min-h-screen bg-[#f6f8f7] text-[#062c31]">
        <PublicHeader active="businesses" />

        <main class="mx-auto max-w-[1260px] px-5 py-8 sm:px-7 lg:px-10 lg:py-12">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <Link
                        href="/dashboard/businesses"
                        class="inline-flex items-center gap-2 text-sm font-black text-emerald-700"
                    >
                        <ArrowLeft class="h-4 w-4" />
                        Manage businesses
                    </Link>

                    <h1 class="mt-3 text-3xl font-black tracking-[-0.04em] sm:text-4xl">
                        {{ business.name }}
                    </h1>

                    <p class="mt-2 text-sm text-slate-500">
                        Update owner-controlled profile information. Your public slug,
                        claim status and verification status remain protected.
                    </p>
                </div>

                <a
                    v-if="business.publicUrl"
                    :href="business.publicUrl"
                    class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-black text-slate-700"
                >
                    <ExternalLink class="h-4 w-4" />
                    View public listing
                </a>
            </div>

            <div
                v-if="status"
                class="mt-6 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-900"
            >
                <CircleCheck class="mt-0.5 h-5 w-5 shrink-0" />
                {{ status }}
            </div>

            <div
                v-if="publicationRequest"
                :class="[
                    'mt-6 rounded-2xl border px-5 py-4 text-sm',
                    requestClass(publicationRequest.status),
                ]"
            >
                <p class="font-black">
                    Publication review: {{ publicationRequest.status }}
                </p>
                <p
                    v-if="publicationRequest.reviewNotes"
                    class="mt-1 leading-6"
                >
                    {{ publicationRequest.reviewNotes }}
                </p>
            </div>

            <div class="mt-7 grid gap-7 xl:grid-cols-[minmax(0,1fr)_360px]">
                <form class="space-y-6" @submit.prevent="submit">
                    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-black">Business identity</h2>
                        <p class="mt-1 text-xs leading-5 text-slate-500">
                            Use genuine trading and legal information for this business.
                        </p>

                        <div class="mt-5 grid gap-4 md:grid-cols-2">
                            <label class="grid gap-1.5 text-sm font-bold text-slate-700">
                                Business name
                                <input
                                    v-model="form.name"
                                    type="text"
                                    class="rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-emerald-500"
                                />
                                <span v-if="form.errors.name" class="text-xs text-rose-600">
                                    {{ form.errors.name }}
                                </span>
                            </label>

                            <label class="grid gap-1.5 text-sm font-bold text-slate-700">
                                Legal name
                                <input
                                    v-model="form.legal_name"
                                    type="text"
                                    class="rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-emerald-500"
                                />
                            </label>

                            <label class="grid gap-1.5 text-sm font-bold text-slate-700">
                                CAC number
                                <input
                                    v-model="form.cac_number"
                                    type="text"
                                    class="rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-emerald-500"
                                />
                            </label>

                            <label class="grid gap-1.5 text-sm font-bold text-slate-700">
                                Primary category
                                <select
                                    v-model="form.category_id"
                                    class="rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-emerald-500"
                                >
                                    <option value="">Select category</option>
                                    <option
                                        v-for="category in categories"
                                        :key="category.id"
                                        :value="String(category.id)"
                                    >
                                        {{ category.name }}
                                    </option>
                                </select>
                                <span v-if="form.errors.category_id" class="text-xs text-rose-600">
                                    {{ form.errors.category_id }}
                                </span>
                            </label>
                        </div>

                        <div class="mt-4 grid gap-4">
                            <label class="grid gap-1.5 text-sm font-bold text-slate-700">
                                Short description
                                <textarea
                                    v-model="form.short_description"
                                    rows="3"
                                    maxlength="500"
                                    class="rounded-xl border border-slate-200 px-4 py-3 leading-6 outline-none focus:border-emerald-500"
                                    placeholder="A concise, factual description of what the business offers."
                                />
                                <span class="text-right text-[11px] text-slate-400">
                                    {{ form.short_description.length }}/500
                                </span>
                            </label>

                            <label class="grid gap-1.5 text-sm font-bold text-slate-700">
                                Full description
                                <textarea
                                    v-model="form.description"
                                    rows="7"
                                    class="rounded-xl border border-slate-200 px-4 py-3 leading-6 outline-none focus:border-emerald-500"
                                />
                            </label>
                        </div>
                    </section>

                    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-black">Customer contacts</h2>

                        <div class="mt-5 grid gap-4 md:grid-cols-2">
                            <label class="grid gap-1.5 text-sm font-bold text-slate-700">
                                <span class="inline-flex items-center gap-2">
                                    <Phone class="h-4 w-4" /> Phone
                                </span>
                                <input
                                    v-model="form.primary_phone"
                                    type="text"
                                    class="rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-emerald-500"
                                />
                            </label>

                            <label class="grid gap-1.5 text-sm font-bold text-slate-700">
                                <span class="inline-flex items-center gap-2">
                                    <Phone class="h-4 w-4" /> WhatsApp
                                </span>
                                <input
                                    v-model="form.whatsapp_phone"
                                    type="text"
                                    class="rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-emerald-500"
                                />
                            </label>

                            <label class="grid gap-1.5 text-sm font-bold text-slate-700">
                                <span class="inline-flex items-center gap-2">
                                    <Mail class="h-4 w-4" /> Email
                                </span>
                                <input
                                    v-model="form.primary_email"
                                    type="email"
                                    class="rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-emerald-500"
                                />
                                <span v-if="form.errors.primary_email" class="text-xs text-rose-600">
                                    {{ form.errors.primary_email }}
                                </span>
                            </label>

                            <label class="grid gap-1.5 text-sm font-bold text-slate-700">
                                <span class="inline-flex items-center gap-2">
                                    <Globe2 class="h-4 w-4" /> Website
                                </span>
                                <input
                                    v-model="form.website_url"
                                    type="text"
                                    class="rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-emerald-500"
                                />
                            </label>
                        </div>
                    </section>

                    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 class="inline-flex items-center gap-2 text-lg font-black">
                            <MapPin class="h-5 w-5 text-emerald-700" />
                            Primary location
                        </h2>

                        <div class="mt-5 grid gap-4 md:grid-cols-2">
                            <label class="grid gap-1.5 text-sm font-bold text-slate-700">
                                State / FCT
                                <select
                                    v-model="form.state_id"
                                    class="rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-emerald-500"
                                >
                                    <option value="">Select state</option>
                                    <option
                                        v-for="state in states"
                                        :key="state.id"
                                        :value="String(state.id)"
                                    >
                                        {{ state.name }}
                                    </option>
                                </select>
                                <span v-if="form.errors.state_id" class="text-xs text-rose-600">
                                    {{ form.errors.state_id }}
                                </span>
                            </label>

                            <label class="grid gap-1.5 text-sm font-bold text-slate-700">
                                LGA / Area Council
                                <select
                                    v-model="form.lga_id"
                                    class="rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-emerald-500"
                                >
                                    <option value="">Not selected</option>
                                    <option
                                        v-for="lga in availableLgas"
                                        :key="lga.id"
                                        :value="String(lga.id)"
                                    >
                                        {{ lga.name }}
                                    </option>
                                </select>
                                <span v-if="form.errors.lga_id" class="text-xs text-rose-600">
                                    {{ form.errors.lga_id }}
                                </span>
                            </label>

                            <label class="grid gap-1.5 text-sm font-bold text-slate-700 md:col-span-2">
                                Address line 1
                                <input
                                    v-model="form.address_line_1"
                                    type="text"
                                    :disabled="form.service_area_only"
                                    class="rounded-xl border border-slate-200 px-4 py-3 outline-none disabled:bg-slate-50 disabled:text-slate-400 focus:border-emerald-500"
                                />
                                <span v-if="form.errors.address_line_1" class="text-xs text-rose-600">
                                    {{ form.errors.address_line_1 }}
                                </span>
                            </label>

                            <label class="grid gap-1.5 text-sm font-bold text-slate-700">
                                Address line 2
                                <input
                                    v-model="form.address_line_2"
                                    type="text"
                                    :disabled="form.service_area_only"
                                    class="rounded-xl border border-slate-200 px-4 py-3 outline-none disabled:bg-slate-50 disabled:text-slate-400 focus:border-emerald-500"
                                />
                            </label>

                            <label class="grid gap-1.5 text-sm font-bold text-slate-700">
                                Landmark
                                <input
                                    v-model="form.landmark"
                                    type="text"
                                    class="rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-emerald-500"
                                />
                            </label>

                            <label class="grid gap-1.5 text-sm font-bold text-slate-700">
                                Postal code
                                <input
                                    v-model="form.postal_code"
                                    type="text"
                                    class="rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-emerald-500"
                                />
                            </label>

                            <label class="flex items-center gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-700">
                                <input
                                    v-model="form.service_area_only"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300"
                                />
                                Service-area business; street address not required
                            </label>
                        </div>
                    </section>

                    <div class="flex justify-end">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex items-center gap-2 rounded-xl bg-[#078844] px-6 py-3.5 text-sm font-black text-white disabled:opacity-50"
                        >
                            <Save class="h-4 w-4" />
                            {{ form.processing ? 'Saving…' : 'Save business profile' }}
                        </button>
                    </div>
                </form>

                <aside class="space-y-5 xl:sticky xl:top-6 xl:self-start">
                    <section
                        :class="[
                            'rounded-3xl border p-6 shadow-sm',
                            readiness.ready
                                ? 'border-emerald-200 bg-emerald-50'
                                : 'border-amber-200 bg-amber-50',
                        ]"
                    >
                        <div class="flex items-center gap-3">
                            <CircleCheck
                                v-if="readiness.ready"
                                class="h-6 w-6 text-emerald-700"
                            />
                            <AlertCircle
                                v-else
                                class="h-6 w-6 text-amber-700"
                            />
                            <h2 class="font-black">Publication readiness</h2>
                        </div>

                        <p
                            v-if="readiness.ready"
                            class="mt-3 text-sm leading-6 text-emerald-900"
                        >
                            The required profile fields are present.
                        </p>

                        <ul
                            v-else
                            class="mt-3 space-y-2 text-sm text-amber-900"
                        >
                            <li
                                v-for="item in readiness.missing"
                                :key="item"
                                class="flex gap-2"
                            >
                                <span>•</span>
                                <span>{{ item }}</span>
                            </li>
                        </ul>

                        <p class="mt-4 text-xs leading-5 text-slate-600">
                            Publication is an editorial listing review. It does not mean
                            BusinessFinder has verified ownership, CAC status, address or identity.
                            If a verified business changes sensitive identity, contact or location
                            details, prior verification evidence is expired until re-verification.
                        </p>

                        <button
                            v-if="
                                ! business.isPublished
                                && readiness.ready
                                && publicationRequest?.status !== 'pending'
                            "
                            type="button"
                            class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[#062c31] px-4 py-3 text-sm font-black text-white"
                            @click="requestPublication"
                        >
                            <Send class="h-4 w-4" />
                            Request publication review
                        </button>

                        <div
                            v-else-if="business.isPublished"
                            class="mt-5 rounded-xl bg-white/80 px-4 py-3 text-xs font-black text-emerald-800"
                        >
                            This listing is already public.
                        </div>

                        <div
                            v-else-if="publicationRequest?.status === 'pending'"
                            class="mt-5 rounded-xl bg-white/80 px-4 py-3 text-xs font-black text-amber-800"
                        >
                            Publication review is pending.
                        </div>
                    </section>

                    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex items-center gap-2">
                            <ShieldCheck class="h-5 w-5 text-emerald-700" />
                            <h2 class="font-black">Protected status</h2>
                        </div>

                        <dl class="mt-4 space-y-3 text-sm">
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">Claim</dt>
                                <dd class="font-black">{{ business.claimStatus }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">Verification</dt>
                                <dd class="font-black">{{ business.verificationStatus }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">Listing</dt>
                                <dd class="font-black">{{ business.listingStatus }}</dd>
                            </div>
                        </dl>
                    </section>
                </aside>
            </div>
        </main>

        <PublicFooter />
    </div>
</template>
