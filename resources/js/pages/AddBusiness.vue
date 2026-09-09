<script setup lang="ts">
import {
    Head,
    Link,
    useForm,
} from '@inertiajs/vue3';
import {
    Building2,
    Check,
    ChevronLeft,
    ChevronRight,
    Clock3,
    CreditCard,
    ImagePlus,
    MapPin,
    Phone,
    ShieldCheck,
} from 'lucide-vue-next';
import {
    computed,
    ref,
} from 'vue';

import PublicFooter from '@/components/public/PublicFooter.vue';
import PublicHeader from '@/components/public/PublicHeader.vue';
import {
    login,
    register,
} from '@/routes';

type StateOption = {
    id: number;
    name: string;
    slug: string;
    code: string;
};

type CategoryOption = {
    id: number;
    name: string;
    slug: string;
};

const props = defineProps<{
    states: StateOption[];
    categories: CategoryOption[];
    status?: string | null;
    createdBusiness?: {
        name: string;
        slug: string;
    } | null;
}>();

const steps = [
    'Business Info',
    'Contact Details',
    'Media Uploads',
    'Opening Hours',
    'Subscription',
    'Review & Publish',
];

const days = [
    'monday',
    'tuesday',
    'wednesday',
    'thursday',
    'friday',
    'saturday',
    'sunday',
];

const currentStep = ref(
    props.status
        ? 6
        : 1,
);

const selectedPlan =
    ref('free');

const form = useForm({
    business_name: '',
    legal_name: '',
    short_description: '',
    description: '',
    primary_phone: '',
    whatsapp_phone: '',
    primary_email: '',
    website_url: '',
    category_id: null as number | null,
    state_id: null as number | null,
    address_line_1: '',
    address_line_2: '',
    landmark: '',
    postal_code: '',
    service_area_only: false,
    opening_hours: {
        monday: '',
        tuesday: '',
        wednesday: '',
        thursday: '',
        friday: '',
        saturday: '',
        sunday: '',
    } as Record<string, string>,
});

const selectedState =
    computed(
        () =>
            props.states.find(
                (state) =>
                    state.id
                    === form.state_id,
            ) ?? null,
    );

const selectedCategory =
    computed(
        () =>
            props.categories.find(
                (category) =>
                    category.id
                    === form.category_id,
            ) ?? null,
    );

const canContinue =
    computed(() => {
        if (
            currentStep.value === 1
        ) {
            return (
                form.business_name
                    .trim()
                    .length > 0
            );
        }

        if (
            currentStep.value === 2
        ) {
            return (
                form.state_id !== null
                &&
                form.address_line_1
                    .trim()
                    .length > 0
            );
        }

        return true;
    });

function nextStep() {
    if (
        ! canContinue.value
    ) {
        return;
    }

    currentStep.value =
        Math.min(
            6,
            currentStep.value + 1,
        );
}

function previousStep() {
    currentStep.value =
        Math.max(
            1,
            currentStep.value - 1,
        );
}

function submit() {
    form.post(
        '/add-business',
        {
            preserveScroll: true,
        },
    );
}

function titleCase(
    value: string,
): string {
    return (
        value.charAt(0).toUpperCase()
        + value.slice(1)
    );
}
</script>

<template>
    <Head title="Add Your Business">
        <meta
            name="description"
            content="Add your Nigerian business to BusinessFinder Nigeria."
        />
    </Head>

    <div
        class="min-h-screen bg-[#f8faf9] text-[#072c33]"
    >
        <PublicHeader />

        <main>
            <section
                class="border-b border-slate-200 bg-white"
            >
                <div
                    class="mx-auto max-w-[1350px] px-5 py-8 sm:px-7 lg:px-10"
                >
                    <div
                        class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between"
                    >
                        <div>
                            <p
                                class="text-xs font-black uppercase tracking-[0.25em] text-emerald-700"
                            >
                                Business Owners
                            </p>

                            <h1
                                class="mt-3 text-4xl font-black tracking-[-0.045em] text-[#062c31] sm:text-5xl"
                            >
                                Add Your Business
                            </h1>

                            <p
                                class="mt-3 max-w-2xl text-sm leading-6 text-slate-600 sm:text-base"
                            >
                                Create your BusinessFinder
                                Nigeria listing and prepare it
                                for review and publication.
                            </p>
                        </div>

                        <div
                            class="rounded-xl bg-emerald-50 px-5 py-3 text-sm font-bold text-emerald-800"
                        >
                            Drafts are not automatically
                            published or verified.
                        </div>
                    </div>
                </div>
            </section>

            <section
                class="border-b border-slate-200 bg-white"
            >
                <div
                    class="mx-auto max-w-[1350px] overflow-x-auto px-5 py-5 sm:px-7 lg:px-10"
                >
                    <div
                        class="flex min-w-[760px] items-center"
                    >
                        <template
                            v-for="(step, index) in steps"
                            :key="step"
                        >
                            <button
                                type="button"
                                class="flex shrink-0 items-center gap-2"
                                @click="
                                    currentStep =
                                        index + 1
                                "
                            >
                                <span
                                    :class="[
                                        'flex h-9 w-9 items-center justify-center rounded-full border-2 text-sm font-black',
                                        currentStep
                                            > index + 1
                                            ? 'border-emerald-600 bg-emerald-600 text-white'
                                            : currentStep
                                                === index + 1
                                              ? 'border-emerald-600 bg-white text-emerald-700'
                                              : 'border-slate-200 bg-white text-slate-400',
                                    ]"
                                >
                                    <Check
                                        v-if="
                                            currentStep
                                            > index + 1
                                        "
                                        class="h-4 w-4"
                                    />

                                    <span v-else>
                                        {{ index + 1 }}
                                    </span>
                                </span>

                                <span
                                    :class="[
                                        'text-xs font-black',
                                        currentStep
                                            === index + 1
                                            ? 'text-emerald-700'
                                            : 'text-slate-500',
                                    ]"
                                >
                                    {{ step }}
                                </span>
                            </button>

                            <div
                                v-if="
                                    index
                                    < steps.length - 1
                                "
                                class="mx-3 h-px min-w-8 flex-1 bg-slate-200"
                            />
                        </template>
                    </div>
                </div>
            </section>

            <section class="py-10">
                <div
                    class="mx-auto grid max-w-[1350px] gap-7 px-5 sm:px-7 lg:grid-cols-[1fr_300px] lg:px-10"
                >
                    <div
                        class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8"
                    >
                        <div
                            v-if="
                                status
                                && currentStep === 6
                            "
                            class="mb-7 rounded-2xl border border-emerald-200 bg-emerald-50 p-6"
                        >
                            <div
                                class="flex gap-4"
                            >
                                <div
                                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-white"
                                >
                                    <Check
                                        class="h-6 w-6"
                                    />
                                </div>

                                <div>
                                    <h2
                                        class="text-lg font-black text-emerald-900"
                                    >
                                        Draft created
                                    </h2>

                                    <p
                                        class="mt-1 text-sm leading-6 text-emerald-800"
                                    >
                                        {{ status }}
                                    </p>

                                    <p
                                        v-if="
                                            createdBusiness
                                        "
                                        class="mt-2 text-xs font-bold text-emerald-700"
                                    >
                                        {{
                                            createdBusiness.name
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Step 1 -->
                        <div
                            v-if="
                                currentStep === 1
                            "
                        >
                            <div
                                class="flex items-center gap-3"
                            >
                                <Building2
                                    class="h-7 w-7 text-emerald-600"
                                />

                                <div>
                                    <h2
                                        class="text-2xl font-black text-slate-900"
                                    >
                                        Business Information
                                    </h2>

                                    <p
                                        class="mt-1 text-sm text-slate-500"
                                    >
                                        Tell us about your
                                        business.
                                    </p>
                                </div>
                            </div>

                            <div
                                class="mt-7 grid gap-6"
                            >
                                <label>
                                    <span
                                        class="text-sm font-black text-slate-800"
                                    >
                                        Business Name *
                                    </span>

                                    <input
                                        v-model="
                                            form.business_name
                                        "
                                        type="text"
                                        maxlength="180"
                                        class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-emerald-500"
                                        placeholder="Your business name"
                                    />

                                    <span
                                        v-if="
                                            form.errors
                                                .business_name
                                        "
                                        class="mt-1 block text-xs font-semibold text-red-600"
                                    >
                                        {{
                                            form.errors
                                                .business_name
                                        }}
                                    </span>
                                </label>

                                <label>
                                    <span
                                        class="text-sm font-black text-slate-800"
                                    >
                                        Legal / Registered Name
                                    </span>

                                    <input
                                        v-model="
                                            form.legal_name
                                        "
                                        type="text"
                                        maxlength="200"
                                        class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-emerald-500"
                                        placeholder="Optional registered legal name"
                                    />
                                </label>

                                <div>
                                    <span
                                        class="text-sm font-black text-slate-800"
                                    >
                                        Primary Category
                                    </span>

                                    <select
                                        v-if="
                                            categories.length
                                        "
                                        v-model="
                                            form.category_id
                                        "
                                        class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-emerald-500"
                                    >
                                        <option
                                            :value="null"
                                        >
                                            Select category
                                        </option>

                                        <option
                                            v-for="
                                                category
                                                in categories
                                            "
                                            :key="
                                                category.id
                                            "
                                            :value="
                                                category.id
                                            "
                                        >
                                            {{
                                                category.name
                                            }}
                                        </option>
                                    </select>

                                    <div
                                        v-else
                                        class="mt-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-800"
                                    >
                                        Business taxonomy is
                                        still being prepared.
                                        We will not invent
                                        category records just
                                        to populate this field.
                                    </div>
                                </div>

                                <label>
                                    <span
                                        class="text-sm font-black text-slate-800"
                                    >
                                        Short Description
                                    </span>

                                    <textarea
                                        v-model="
                                            form.short_description
                                        "
                                        maxlength="500"
                                        rows="3"
                                        class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-emerald-500"
                                        placeholder="Briefly describe what your business does"
                                    />
                                </label>

                                <label>
                                    <span
                                        class="text-sm font-black text-slate-800"
                                    >
                                        About the Business
                                    </span>

                                    <textarea
                                        v-model="
                                            form.description
                                        "
                                        rows="6"
                                        class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-emerald-500"
                                        placeholder="Tell customers more about your products, services and business"
                                    />
                                </label>
                            </div>
                        </div>

                        <!-- Step 2 -->
                        <div
                            v-else-if="
                                currentStep === 2
                            "
                        >
                            <div
                                class="flex items-center gap-3"
                            >
                                <Phone
                                    class="h-7 w-7 text-emerald-600"
                                />

                                <div>
                                    <h2
                                        class="text-2xl font-black text-slate-900"
                                    >
                                        Contact Details
                                    </h2>

                                    <p
                                        class="mt-1 text-sm text-slate-500"
                                    >
                                        Add real contact and
                                        location information.
                                    </p>
                                </div>
                            </div>

                            <div
                                class="mt-7 grid gap-6 sm:grid-cols-2"
                            >
                                <label>
                                    <span
                                        class="text-sm font-black text-slate-800"
                                    >
                                        Phone
                                    </span>

                                    <input
                                        v-model="
                                            form.primary_phone
                                        "
                                        type="tel"
                                        class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-emerald-500"
                                        placeholder="e.g. 080..."
                                    />
                                </label>

                                <label>
                                    <span
                                        class="text-sm font-black text-slate-800"
                                    >
                                        WhatsApp
                                    </span>

                                    <input
                                        v-model="
                                            form.whatsapp_phone
                                        "
                                        type="tel"
                                        class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-emerald-500"
                                        placeholder="WhatsApp number"
                                    />
                                </label>

                                <label>
                                    <span
                                        class="text-sm font-black text-slate-800"
                                    >
                                        Business Email
                                    </span>

                                    <input
                                        v-model="
                                            form.primary_email
                                        "
                                        type="email"
                                        class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-emerald-500"
                                        placeholder="business@example.com"
                                    />
                                </label>

                                <label>
                                    <span
                                        class="text-sm font-black text-slate-800"
                                    >
                                        Website
                                    </span>

                                    <input
                                        v-model="
                                            form.website_url
                                        "
                                        type="text"
                                        class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-emerald-500"
                                        placeholder="example.com"
                                    />
                                </label>

                                <label>
                                    <span
                                        class="text-sm font-black text-slate-800"
                                    >
                                        State / FCT *
                                    </span>

                                    <select
                                        v-model="
                                            form.state_id
                                        "
                                        class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-emerald-500"
                                    >
                                        <option
                                            :value="null"
                                        >
                                            Select location
                                        </option>

                                        <option
                                            v-for="
                                                state
                                                in states
                                            "
                                            :key="
                                                state.id
                                            "
                                            :value="
                                                state.id
                                            "
                                        >
                                            {{
                                                state.name
                                            }}
                                        </option>
                                    </select>

                                    <span
                                        v-if="
                                            form.errors
                                                .state_id
                                        "
                                        class="mt-1 block text-xs font-semibold text-red-600"
                                    >
                                        {{
                                            form.errors
                                                .state_id
                                        }}
                                    </span>
                                </label>

                                <label>
                                    <span
                                        class="text-sm font-black text-slate-800"
                                    >
                                        Postal Code
                                    </span>

                                    <input
                                        v-model="
                                            form.postal_code
                                        "
                                        type="text"
                                        class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-emerald-500"
                                    />
                                </label>

                                <label
                                    class="sm:col-span-2"
                                >
                                    <span
                                        class="text-sm font-black text-slate-800"
                                    >
                                        Address *
                                    </span>

                                    <input
                                        v-model="
                                            form.address_line_1
                                        "
                                        type="text"
                                        class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-emerald-500"
                                        placeholder="Street and business address"
                                    />

                                    <span
                                        v-if="
                                            form.errors
                                                .address_line_1
                                        "
                                        class="mt-1 block text-xs font-semibold text-red-600"
                                    >
                                        {{
                                            form.errors
                                                .address_line_1
                                        }}
                                    </span>
                                </label>

                                <label>
                                    <span
                                        class="text-sm font-black text-slate-800"
                                    >
                                        Address Line 2
                                    </span>

                                    <input
                                        v-model="
                                            form.address_line_2
                                        "
                                        type="text"
                                        class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-emerald-500"
                                    />
                                </label>

                                <label>
                                    <span
                                        class="text-sm font-black text-slate-800"
                                    >
                                        Landmark
                                    </span>

                                    <input
                                        v-model="
                                            form.landmark
                                        "
                                        type="text"
                                        class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-emerald-500"
                                    />
                                </label>

                                <label
                                    class="flex items-start gap-3 rounded-xl bg-slate-50 p-4 sm:col-span-2"
                                >
                                    <input
                                        v-model="
                                            form.service_area_only
                                        "
                                        type="checkbox"
                                        class="mt-1"
                                    />

                                    <span>
                                        <span
                                            class="block text-sm font-black text-slate-800"
                                        >
                                            Service-area
                                            business
                                        </span>

                                        <span
                                            class="mt-1 block text-xs leading-5 text-slate-500"
                                        >
                                            Select this if
                                            customers do not
                                            normally visit a
                                            storefront.
                                        </span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <!-- Step 3 -->
                        <div
                            v-else-if="
                                currentStep === 3
                            "
                        >
                            <div
                                class="flex items-center gap-3"
                            >
                                <ImagePlus
                                    class="h-7 w-7 text-emerald-600"
                                />

                                <div>
                                    <h2
                                        class="text-2xl font-black text-slate-900"
                                    >
                                        Media Uploads
                                    </h2>

                                    <p
                                        class="mt-1 text-sm text-slate-500"
                                    >
                                        Business photos and
                                        logo.
                                    </p>
                                </div>
                            </div>

                            <div
                                class="mt-8 flex min-h-[280px] items-center justify-center rounded-2xl border-2 border-dashed border-emerald-200 bg-emerald-50/50 p-8 text-center"
                            >
                                <div
                                    class="max-w-md"
                                >
                                    <ImagePlus
                                        class="mx-auto h-12 w-12 text-emerald-600"
                                    />

                                    <h3
                                        class="mt-4 text-lg font-black text-slate-900"
                                    >
                                        Media storage is
                                        coming next
                                    </h3>

                                    <p
                                        class="mt-2 text-sm leading-6 text-slate-500"
                                    >
                                        The approved upload
                                        interface is reserved,
                                        but we will not accept
                                        files until the real
                                        media storage and
                                        moderation workflow is
                                        connected.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Step 4 -->
                        <div
                            v-else-if="
                                currentStep === 4
                            "
                        >
                            <div
                                class="flex items-center gap-3"
                            >
                                <Clock3
                                    class="h-7 w-7 text-emerald-600"
                                />

                                <div>
                                    <h2
                                        class="text-2xl font-black text-slate-900"
                                    >
                                        Opening Hours
                                    </h2>

                                    <p
                                        class="mt-1 text-sm text-slate-500"
                                    >
                                        Add hours exactly as
                                        customers should see
                                        them.
                                    </p>
                                </div>
                            </div>

                            <div
                                class="mt-7 divide-y divide-slate-100"
                            >
                                <label
                                    v-for="
                                        day in days
                                    "
                                    :key="day"
                                    class="grid gap-3 py-4 sm:grid-cols-[160px_1fr] sm:items-center"
                                >
                                    <span
                                        class="text-sm font-black text-slate-800"
                                    >
                                        {{
                                            titleCase(
                                                day,
                                            )
                                        }}
                                    </span>

                                    <input
                                        v-model="
                                            form
                                                .opening_hours[
                                                day
                                            ]
                                        "
                                        type="text"
                                        class="rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-emerald-500"
                                        placeholder="e.g. 8:00 AM - 6:00 PM or Closed"
                                    />
                                </label>
                            </div>
                        </div>

                        <!-- Step 5 -->
                        <div
                            v-else-if="
                                currentStep === 5
                            "
                        >
                            <div
                                class="flex items-center gap-3"
                            >
                                <CreditCard
                                    class="h-7 w-7 text-emerald-600"
                                />

                                <div>
                                    <h2
                                        class="text-2xl font-black text-slate-900"
                                    >
                                        Subscription
                                    </h2>

                                    <p
                                        class="mt-1 text-sm text-slate-500"
                                    >
                                        Choose how to start.
                                    </p>
                                </div>
                            </div>

                            <div
                                class="mt-8 grid gap-5 md:grid-cols-3"
                            >
                                <button
                                    type="button"
                                    class="rounded-2xl border-2 border-emerald-500 bg-emerald-50 p-6 text-left"
                                    @click="
                                        selectedPlan =
                                            'free'
                                    "
                                >
                                    <span
                                        class="text-xs font-black uppercase tracking-wider text-emerald-700"
                                    >
                                        Available
                                    </span>

                                    <h3
                                        class="mt-3 text-xl font-black text-slate-900"
                                    >
                                        Free Listing
                                    </h3>

                                    <p
                                        class="mt-2 text-sm leading-6 text-slate-500"
                                    >
                                        Create a real business
                                        draft for review.
                                    </p>

                                    <div
                                        class="mt-5 text-sm font-black text-emerald-700"
                                    >
                                        Selected ✓
                                    </div>
                                </button>

                                <div
                                    class="rounded-2xl border border-slate-200 bg-slate-50 p-6 opacity-70"
                                >
                                    <span
                                        class="text-xs font-black uppercase tracking-wider text-slate-500"
                                    >
                                        Coming later
                                    </span>

                                    <h3
                                        class="mt-3 text-xl font-black text-slate-900"
                                    >
                                        Growth
                                    </h3>

                                    <p
                                        class="mt-2 text-sm leading-6 text-slate-500"
                                    >
                                        Enhanced listing and
                                        business tools.
                                    </p>
                                </div>

                                <div
                                    class="rounded-2xl border border-slate-200 bg-slate-50 p-6 opacity-70"
                                >
                                    <span
                                        class="text-xs font-black uppercase tracking-wider text-slate-500"
                                    >
                                        Coming later
                                    </span>

                                    <h3
                                        class="mt-3 text-xl font-black text-slate-900"
                                    >
                                        Pro
                                    </h3>

                                    <p
                                        class="mt-2 text-sm leading-6 text-slate-500"
                                    >
                                        Advanced promotion,
                                        analytics and lead
                                        tools.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Step 6 -->
                        <div v-else>
                            <div
                                class="flex items-center gap-3"
                            >
                                <ShieldCheck
                                    class="h-7 w-7 text-emerald-600"
                                />

                                <div>
                                    <h2
                                        class="text-2xl font-black text-slate-900"
                                    >
                                        Review & Submit
                                    </h2>

                                    <p
                                        class="mt-1 text-sm text-slate-500"
                                    >
                                        Review your details
                                        before creating the
                                        draft.
                                    </p>
                                </div>
                            </div>

                            <div
                                class="mt-8 grid gap-4 sm:grid-cols-2"
                            >
                                <div
                                    class="rounded-xl bg-slate-50 p-5"
                                >
                                    <p
                                        class="text-xs font-black uppercase tracking-wide text-slate-400"
                                    >
                                        Business
                                    </p>

                                    <p
                                        class="mt-2 font-black text-slate-900"
                                    >
                                        {{
                                            form.business_name
                                            || 'Not entered'
                                        }}
                                    </p>

                                    <p
                                        v-if="
                                            selectedCategory
                                        "
                                        class="mt-1 text-sm text-slate-500"
                                    >
                                        {{
                                            selectedCategory.name
                                        }}
                                    </p>
                                </div>

                                <div
                                    class="rounded-xl bg-slate-50 p-5"
                                >
                                    <p
                                        class="text-xs font-black uppercase tracking-wide text-slate-400"
                                    >
                                        Location
                                    </p>

                                    <p
                                        class="mt-2 font-black text-slate-900"
                                    >
                                        {{
                                            selectedState
                                                ?.name
                                            || 'Not selected'
                                        }}
                                    </p>

                                    <p
                                        class="mt-1 text-sm text-slate-500"
                                    >
                                        {{
                                            form.address_line_1
                                            || 'No address'
                                        }}
                                    </p>
                                </div>

                                <div
                                    class="rounded-xl bg-slate-50 p-5"
                                >
                                    <p
                                        class="text-xs font-black uppercase tracking-wide text-slate-400"
                                    >
                                        Contact
                                    </p>

                                    <p
                                        class="mt-2 text-sm font-semibold text-slate-700"
                                    >
                                        {{
                                            form.primary_phone
                                            || 'No phone'
                                        }}
                                    </p>

                                    <p
                                        class="mt-1 text-sm text-slate-500"
                                    >
                                        {{
                                            form.primary_email
                                            || 'No email'
                                        }}
                                    </p>
                                </div>

                                <div
                                    class="rounded-xl bg-slate-50 p-5"
                                >
                                    <p
                                        class="text-xs font-black uppercase tracking-wide text-slate-400"
                                    >
                                        Listing Plan
                                    </p>

                                    <p
                                        class="mt-2 font-black text-emerald-700"
                                    >
                                        Free Listing
                                    </p>

                                    <p
                                        class="mt-1 text-xs text-slate-500"
                                    >
                                        No payment is being
                                        processed.
                                    </p>
                                </div>
                            </div>

                            <div
                                class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-900"
                            >
                                Submitting creates an
                                <strong>
                                    unpublished,
                                    unverified business draft
                                </strong>.
                                It will not automatically
                                appear in public search or
                                receive a Verified badge.
                            </div>

                            <div
                                v-if="
                                    !$page.props.auth.user
                                "
                                class="mt-6 rounded-2xl border border-slate-200 p-6"
                            >
                                <h3
                                    class="font-black text-slate-900"
                                >
                                    Sign in to create your
                                    draft
                                </h3>

                                <p
                                    class="mt-2 text-sm leading-6 text-slate-500"
                                >
                                    An account is required so
                                    the listing can be attached
                                    to its owner.
                                </p>

                                <div
                                    class="mt-5 flex flex-wrap gap-3"
                                >
                                    <Link
                                        :href="login()"
                                        class="rounded-xl bg-emerald-600 px-6 py-3 text-sm font-black text-white"
                                    >
                                        Log In
                                    </Link>

                                    <Link
                                        :href="register()"
                                        class="rounded-xl border border-slate-200 px-6 py-3 text-sm font-black text-slate-700"
                                    >
                                        Create Account
                                    </Link>
                                </div>
                            </div>

                            <button
                                v-else
                                type="button"
                                :disabled="
                                    form.processing
                                    || !canContinue
                                "
                                class="mt-6 w-full rounded-xl bg-emerald-600 px-6 py-4 text-sm font-black text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                                @click="submit"
                            >
                                {{
                                    form.processing
                                        ? 'Creating Draft...'
                                        : 'Create Business Draft'
                                }}
                            </button>
                        </div>

                        <div
                            class="mt-9 flex items-center justify-between border-t border-slate-200 pt-6"
                        >
                            <button
                                type="button"
                                :disabled="
                                    currentStep === 1
                                "
                                class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-5 py-3 text-sm font-black text-slate-700 disabled:opacity-40"
                                @click="
                                    previousStep
                                "
                            >
                                <ChevronLeft
                                    class="h-4 w-4"
                                />
                                Back
                            </button>

                            <button
                                v-if="
                                    currentStep < 6
                                "
                                type="button"
                                :disabled="
                                    !canContinue
                                "
                                class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-6 py-3 text-sm font-black text-white disabled:cursor-not-allowed disabled:opacity-40"
                                @click="nextStep"
                            >
                                Continue
                                <ChevronRight
                                    class="h-4 w-4"
                                />
                            </button>
                        </div>
                    </div>

                    <aside class="space-y-5">
                        <div
                            class="rounded-2xl border border-emerald-100 bg-emerald-50 p-6"
                        >
                            <MapPin
                                class="h-7 w-7 text-emerald-600"
                            />

                            <h2
                                class="mt-4 text-lg font-black text-slate-900"
                            >
                                Built for Nigeria
                            </h2>

                            <p
                                class="mt-2 text-sm leading-6 text-slate-600"
                            >
                                The location selector uses
                                BusinessFinder's real 37
                                state/FCT geography records.
                            </p>
                        </div>

                        <div
                            class="rounded-2xl border border-slate-200 bg-white p-6"
                        >
                            <ShieldCheck
                                class="h-7 w-7 text-slate-700"
                            />

                            <h2
                                class="mt-4 text-lg font-black text-slate-900"
                            >
                                Honest listing status
                            </h2>

                            <p
                                class="mt-2 text-sm leading-6 text-slate-500"
                            >
                                New submissions are not
                                labelled Verified until a
                                real verification process has
                                succeeded.
                            </p>
                        </div>
                    </aside>
                </div>
            </section>
        </main>

        <PublicFooter />
    </div>
</template>
