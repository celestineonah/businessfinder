<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { BadgeCheck, Building2, CheckCircle2, ShieldCheck, XCircle } from 'lucide-vue-next';
import PublicFooter from '@/components/public/PublicFooter.vue';
import PublicHeader from '@/components/public/PublicHeader.vue';

type Claim = {
    id: number;
    method: string;
    reference: string | null;
    notes: string | null;
    submittedAt: string | null;
    business: { id: number; name: string; slug: string; claimStatus: string; verificationStatus: string };
    claimant: { id: number; name: string; email: string };
};

type Verification = {
    id: number;
    type: string;
    reference: string | null;
    createdAt: string | null;
    business: { id: number; name: string; slug: string; verificationStatus: string };
};

defineProps<{
    metrics: { pendingClaims: number; approvedClaims: number; pendingVerifications: number; verifiedEvidence: number };
    pendingClaims: Claim[];
    pendingVerifications: Verification[];
    status?: string | null;
}>();

function approveClaim(id: number) {
    const form = useForm({ review_notes: '' });
    form.post(`/admin/claims/${id}/approve`, { preserveScroll: true });
}

function rejectClaim(id: number) {
    const notes = window.prompt('Reason for rejection (required):');
    if (!notes?.trim()) return;
    const form = useForm({ review_notes: notes.trim() });
    form.post(`/admin/claims/${id}/reject`, { preserveScroll: true });
}

function verifyEvidence(id: number) {
    const form = useForm({});
    form.post(`/admin/verifications/${id}/verify`, { preserveScroll: true });
}

function failEvidence(id: number) {
    const notes = window.prompt('Why did this evidence fail?');
    if (!notes?.trim()) return;
    const form = useForm({ review_notes: notes.trim() });
    form.post(`/admin/verifications/${id}/fail`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Claim & Verification Review | BusinessFinder Nigeria" />
    <div class="min-h-screen bg-[#f6f8f7] text-[#062c31]">
        <PublicHeader />
        <main class="mx-auto max-w-[1380px] px-5 py-10 sm:px-7 lg:px-10">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.22em] text-emerald-700">Administrator</p>
                    <h1 class="mt-2 text-3xl font-black tracking-[-0.04em] sm:text-4xl">Claims & verification review</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Ownership and Verified status are separate. Approving a claim transfers ownership only; evidence must be approved separately before the Verified badge appears.</p>
                </div>
                <Link href="/dashboard" class="text-sm font-black text-emerald-700">Back to dashboard →</Link>
            </div>

            <div v-if="status" class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-800">{{ status }}</div>

            <section class="mt-7 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-5"><Building2 class="h-5 w-5 text-emerald-700"/><p class="mt-3 text-3xl font-black">{{ metrics.pendingClaims }}</p><p class="text-sm text-slate-500">Pending claims</p></div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5"><CheckCircle2 class="h-5 w-5 text-emerald-700"/><p class="mt-3 text-3xl font-black">{{ metrics.approvedClaims }}</p><p class="text-sm text-slate-500">Approved claims</p></div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5"><ShieldCheck class="h-5 w-5 text-amber-600"/><p class="mt-3 text-3xl font-black">{{ metrics.pendingVerifications }}</p><p class="text-sm text-slate-500">Evidence awaiting review</p></div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5"><BadgeCheck class="h-5 w-5 text-emerald-700"/><p class="mt-3 text-3xl font-black">{{ metrics.verifiedEvidence }}</p><p class="text-sm text-slate-500">Verified evidence</p></div>
            </section>

            <section class="mt-8 rounded-3xl border border-slate-200 bg-white p-6 sm:p-8">
                <h2 class="text-xl font-black">Pending ownership claims</h2>
                <div v-if="pendingClaims.length === 0" class="mt-5 rounded-2xl bg-slate-50 p-6 text-sm text-slate-500">No ownership claims are waiting for review.</div>
                <div v-else class="mt-5 grid gap-4">
                    <article v-for="claim in pendingClaims" :key="claim.id" class="rounded-2xl border border-slate-200 p-5">
                        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <Link :href="`/business/${claim.business.slug}`" class="text-lg font-black text-slate-900 hover:text-emerald-700">{{ claim.business.name }}</Link>
                                <p class="mt-1 text-sm text-slate-500">{{ claim.claimant.name }} · {{ claim.claimant.email }}</p>
                                <p class="mt-3 text-sm"><span class="font-black">Method:</span> {{ claim.method }} <span v-if="claim.reference">· <span class="font-black">Reference:</span> {{ claim.reference }}</span></p>
                                <p v-if="claim.notes" class="mt-2 text-sm leading-6 text-slate-600">{{ claim.notes }}</p>
                            </div>
                            <div class="flex shrink-0 gap-2">
                                <button class="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-black text-white hover:bg-emerald-700" @click="approveClaim(claim.id)">Approve ownership</button>
                                <button class="rounded-xl border border-red-200 px-4 py-2.5 text-sm font-black text-red-700 hover:bg-red-50" @click="rejectClaim(claim.id)">Reject</button>
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <section class="mt-8 rounded-3xl border border-slate-200 bg-white p-6 sm:p-8">
                <h2 class="text-xl font-black">Pending verification evidence</h2>
                <p class="mt-1 text-sm text-slate-500">Only approve evidence you have actually checked. This action controls the public Verified badge.</p>
                <div v-if="pendingVerifications.length === 0" class="mt-5 rounded-2xl bg-slate-50 p-6 text-sm text-slate-500">No verification evidence is waiting for review.</div>
                <div v-else class="mt-5 grid gap-4">
                    <article v-for="item in pendingVerifications" :key="item.id" class="rounded-2xl border border-slate-200 p-5">
                        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <Link :href="`/business/${item.business.slug}`" class="text-lg font-black text-slate-900 hover:text-emerald-700">{{ item.business.name }}</Link>
                                <p class="mt-2 text-sm"><span class="font-black">Evidence type:</span> {{ item.type }}</p>
                                <p v-if="item.reference" class="mt-1 break-all text-sm text-slate-600"><span class="font-black">Reference:</span> {{ item.reference }}</p>
                            </div>
                            <div class="flex shrink-0 gap-2">
                                <button class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-black text-white hover:bg-emerald-700" @click="verifyEvidence(item.id)"><BadgeCheck class="h-4 w-4"/> Verify evidence</button>
                                <button class="inline-flex items-center gap-2 rounded-xl border border-red-200 px-4 py-2.5 text-sm font-black text-red-700 hover:bg-red-50" @click="failEvidence(item.id)"><XCircle class="h-4 w-4"/> Fail</button>
                            </div>
                        </div>
                    </article>
                </div>
            </section>
        </main>
        <PublicFooter />
    </div>
</template>
