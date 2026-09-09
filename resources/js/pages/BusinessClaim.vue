<script setup lang="ts">
import {
    Head,
    Link,
    useForm,
} from '@inertiajs/vue3';
import {
    BadgeCheck,
    Building2,
    Globe2,
    Mail,
    Phone,
    ShieldCheck,
} from 'lucide-vue-next';

import PublicFooter from '@/components/public/PublicFooter.vue';
import PublicHeader from '@/components/public/PublicHeader.vue';

type ClaimBusiness = {
    name: string;
    slug: string;
    location: string;
    claimStatus: string;
    verificationStatus: string;
    publicUrl: string;
};

type ExistingClaim = {
    status: string;
    claimMethod: string | null;
    submittedAt: string | null;
};

const props = defineProps<{
    business: ClaimBusiness;
    existingClaim: ExistingClaim | null;
    canSubmit: boolean;
    status?: string | null;
}>();

const form = useForm({
    claim_method: 'phone',
    reference: '',
    claimant_notes: '',
});

const methods = [
    {
        value: 'phone',
        label: 'Business Phone',
        description:
            'Use a phone number connected to the business.',
        icon: Phone,
    },
    {
        value: 'email',
        label: 'Business Email',
        description:
            'Use an email address belonging to the business.',
        icon: Mail,
    },
    {
        value: 'website',
        label: 'Business Website',
        description:
            'Provide the official business website or domain.',
        icon: Globe2,
    },
    {
        value: 'cac',
        label: 'CAC Registration',
        description:
            'Provide the business CAC registration reference.',
        icon: BadgeCheck,
    },
];

function submit() {
    form.post(
        `/business/${props.business.slug}/claim`,
        {
            preserveScroll: true,
        },
    );
}
</script>

<template>
    <Head
        :title="`Claim ${business.name} | BusinessFinder Nigeria`"
    />

    <div class="min-h-screen bg-[#f6f8f7]">
        <PublicHeader />

        <main class="py-10 sm:py-14">
            <div
                class="mx-auto grid max-w-[1180px] gap-7 px-5 sm:px-7 lg:grid-cols-[1fr_360px] lg:px-10"
            >
                <section
                    class="rounded-3xl border border-slate-200 bg-white p-6 sm:p-9"
                >
                    <p
                        class="text-xs font-black uppercase tracking-[0.22em] text-emerald-700"
                    >
                        Business ownership
                    </p>

                    <h1
                        class="mt-3 text-3xl font-black tracking-[-0.04em] text-[#062c31] sm:text-4xl"
                    >
                        Claim {{ business.name }}
                    </h1>

                    <p
                        class="mt-3 max-w-2xl text-sm leading-7 text-slate-500"
                    >
                        Submit evidence showing that you own or
                        officially represent this business.
                        A claim does not automatically make a
                        listing verified.
                    </p>

                    <div
                        v-if="status"
                        class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-800"
                    >
                        {{ status }}
                    </div>

                    <div
                        v-if="existingClaim"
                        class="mt-7 rounded-2xl border border-amber-200 bg-amber-50 p-6"
                    >
                        <ShieldCheck
                            class="h-7 w-7 text-amber-600"
                        />

                        <h2
                            class="mt-4 text-lg font-black text-slate-900"
                        >
                            Claim under review
                        </h2>

                        <p
                            class="mt-2 text-sm leading-6 text-slate-600"
                        >
                            Your ownership claim has been received.
                            Business ownership will not be transferred
                            until the evidence is reviewed.
                        </p>
                    </div>

                    <form
                        v-else-if="canSubmit"
                        class="mt-8"
                        @submit.prevent="submit"
                    >
                        <h2
                            class="text-lg font-black text-slate-900"
                        >
                            Choose your proof method
                        </h2>

                        <div
                            class="mt-4 grid gap-3 sm:grid-cols-2"
                        >
                            <label
                                v-for="method in methods"
                                :key="method.value"
                                :class="[
                                    'cursor-pointer rounded-2xl border p-5 transition',
                                    form.claim_method === method.value
                                        ? 'border-emerald-500 bg-emerald-50'
                                        : 'border-slate-200 hover:border-emerald-300',
                                ]"
                            >
                                <input
                                    v-model="form.claim_method"
                                    type="radio"
                                    name="claim_method"
                                    :value="method.value"
                                    class="sr-only"
                                />

                                <component
                                    :is="method.icon"
                                    class="h-6 w-6 text-emerald-700"
                                />

                                <p
                                    class="mt-3 font-black text-slate-900"
                                >
                                    {{ method.label }}
                                </p>

                                <p
                                    class="mt-1 text-xs leading-5 text-slate-500"
                                >
                                    {{ method.description }}
                                </p>
                            </label>
                        </div>

                        <p
                            v-if="form.errors.claim_method"
                            class="mt-2 text-sm font-semibold text-red-600"
                        >
                            {{ form.errors.claim_method }}
                        </p>

                        <div class="mt-6">
                            <label
                                for="reference"
                                class="text-sm font-black text-slate-800"
                            >
                                Evidence reference
                            </label>

                            <input
                                id="reference"
                                v-model="form.reference"
                                type="text"
                                required
                                maxlength="255"
                                class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none transition focus:border-emerald-500"
                                placeholder="Phone, email, website or CAC reference"
                            />

                            <p
                                v-if="form.errors.reference"
                                class="mt-2 text-sm font-semibold text-red-600"
                            >
                                {{ form.errors.reference }}
                            </p>
                        </div>

                        <div class="mt-6">
                            <label
                                for="claimant_notes"
                                class="text-sm font-black text-slate-800"
                            >
                                Additional information
                            </label>

                            <textarea
                                id="claimant_notes"
                                v-model="form.claimant_notes"
                                rows="5"
                                maxlength="2000"
                                class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none transition focus:border-emerald-500"
                                placeholder="Explain your relationship to the business, if useful."
                            />

                            <p
                                v-if="form.errors.claimant_notes"
                                class="mt-2 text-sm font-semibold text-red-600"
                            >
                                {{ form.errors.claimant_notes }}
                            </p>
                        </div>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="mt-7 inline-flex w-full items-center justify-center rounded-xl bg-emerald-600 px-6 py-3.5 text-sm font-black text-white transition hover:bg-emerald-700 disabled:opacity-60"
                        >
                            {{
                                form.processing
                                    ? 'Submitting...'
                                    : 'Submit Ownership Claim'
                            }}
                        </button>
                    </form>

                    <div
                        v-else
                        class="mt-7 rounded-2xl border border-slate-200 bg-slate-50 p-6"
                    >
                        <Building2
                            class="h-7 w-7 text-slate-400"
                        />

                        <h2
                            class="mt-4 font-black text-slate-900"
                        >
                            This listing is not currently claimable
                        </h2>

                        <p
                            class="mt-2 text-sm leading-6 text-slate-500"
                        >
                            It may already have an ownership claim
                            under review or already be claimed.
                        </p>
                    </div>
                </section>

                <aside
                    class="h-fit rounded-3xl border border-slate-200 bg-white p-6"
                >
                    <p
                        class="text-xs font-black uppercase tracking-[0.18em] text-slate-400"
                    >
                        Listing
                    </p>

                    <h2
                        class="mt-3 text-xl font-black text-slate-900"
                    >
                        {{ business.name }}
                    </h2>

                    <p
                        v-if="business.location"
                        class="mt-2 text-sm leading-6 text-slate-500"
                    >
                        {{ business.location }}
                    </p>

                    <div
                        class="mt-5 flex flex-wrap gap-2"
                    >
                        <span
                            class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-black text-slate-700"
                        >
                            Listed Business
                        </span>

                        <span
                            v-if="
                                business.verificationStatus
                                === 'verified'
                            "
                            class="rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-black text-emerald-700"
                        >
                            Verified
                        </span>
                    </div>

                    <Link
                        :href="business.publicUrl"
                        class="mt-6 inline-flex text-sm font-black text-emerald-700"
                    >
                        ← Back to business profile
                    </Link>

                    <div
                        class="mt-6 border-t border-slate-100 pt-5"
                    >
                        <p
                            class="text-xs leading-5 text-slate-500"
                        >
                            BusinessFinder does not transfer
                            ownership merely because a claim was
                            submitted. Evidence must be reviewed.
                        </p>
                    </div>
                </aside>
            </div>
        </main>

        <PublicFooter />
    </div>
</template>
