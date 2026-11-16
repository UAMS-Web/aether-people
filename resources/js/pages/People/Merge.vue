<script setup>
import { computed, getCurrentInstance, ref, watch } from 'vue';
import { Head, router } from '@statamic/cms/inertia';
import { Header, Panel, PanelHeader, Heading, Card, Description, Badge, Button } from '@statamic/cms/ui';

/**
 * People merge reconciliation screen (UAMS-Web/uams-statamic-archive#838).
 *
 * Renders the field-by-field comparison the controller built, lets the operator
 * choose the survivor and the winning source per field, previews the exact
 * outcome (references re-pointed, redirects created, entries retired), and only
 * commits the destructive merge after an explicit confirmation.
 */
const props = defineProps({
    // Reconciliation payload from PeopleMergeService::buildReconciliation()->toArray(), or null on error
    reconciliation: { type: Object, default: null },
    // Human-readable problem (too few candidates, non-people entry), or null
    error: { type: String, default: null },
    // CP endpoint for a non-destructive dry run
    previewUrl: { type: String, required: true },
    // CP endpoint that performs the merge
    commitUrl: { type: String, required: true },
});

const instance = getCurrentInstance().proxy;
const __ = (key) => instance.__(key);

const candidates = computed(() => props.reconciliation?.candidates ?? []);
const fields = computed(() => props.reconciliation?.fields ?? []);
const sites = computed(() => props.reconciliation?.sites ?? {});

// The chosen survivor (defaults to the controller's pick)
const survivorId = ref(props.reconciliation?.survivor_id ?? null);

// Per-field winning source: handle => entry id (defaults to each field's default_source)
const selections = ref(
    Object.fromEntries((props.reconciliation?.fields ?? []).map((field) => [field.handle, field.default_source])),
);

const preview = ref(null);
const previewing = ref(false);
const committing = ref(false);

// Any change to a per-field source invalidates a prior preview, so the operator
// can only commit against the exact outcome they just reviewed (the Merge button
// is gated on `preview` being present).
watch(selections, () => { preview.value = null; }, { deep: true });

const conflictCount = computed(() => fields.value.filter((field) => field.conflict).length);

const loserIds = computed(() => candidates.value.map((c) => c.id).filter((id) => id !== survivorId.value));

// The value a candidate holds for a field, for table display
function valueFor(field, entryId) {
    return field.values.find((v) => v.entry_id === entryId);
}

function candidateTitle(entryId) {
    return candidates.value.find((c) => c.id === entryId)?.title ?? entryId;
}

// When the survivor changes, move any field still defaulting to the old survivor
// onto the new one, so "the survivor wins" stays intuitive. The preview is the
// authoritative outcome regardless.
function chooseSurvivor(newSurvivorId) {
    const previous = survivorId.value;
    survivorId.value = newSurvivorId;

    for (const field of fields.value) {
        if (selections.value[field.handle] === previous) {
            selections.value[field.handle] = newSurvivorId;
        }
    }

    // A new survivor invalidates a prior preview
    preview.value = null;
}

function payload() {
    return {
        ids: candidates.value.map((c) => c.id),
        survivor: survivorId.value,
        selections: selections.value,
    };
}

async function runPreview() {
    previewing.value = true;
    try {
        const { data } = await instance.$axios.post(props.previewUrl, payload());
        preview.value = data;
    } catch (error) {
        preview.value = null;
        instance.$toast?.error?.(error.response?.data?.message ?? __('Something went wrong'));
    } finally {
        previewing.value = false;
    }
}

async function commit() {
    if (!window.confirm(__('Merge these entries? The retired entries will be deleted. This cannot be undone.'))) {
        return;
    }

    committing.value = true;
    try {
        const { data } = await instance.$axios.post(props.commitUrl, payload());
        instance.$toast?.success?.(__('People merged.'));

        if (data.survivor_edit_url) {
            router.visit(data.survivor_edit_url);
        }
    } catch (error) {
        instance.$toast?.error?.(error.response?.data?.message ?? __('Something went wrong'));
    } finally {
        committing.value = false;
    }
}
</script>

<template>
    <Head :title="__('Merge People')" />

    <div class="max-w-page mx-auto">
        <Header :title="__('Merge People')" icon="arrows-merge-alt" />

        <!-- Unable to build the reconciliation (too few candidates, etc.) -->
        <Card v-if="error">
            <Description>{{ error }}</Description>
        </Card>

        <template v-else-if="reconciliation">
            <!-- 1. Choose the survivor -->
            <Panel class="mb-6">
                <PanelHeader class="flex items-center justify-between">
                    <Heading>{{ __('Survivor') }}</Heading>
                    <Badge v-if="conflictCount" color="orange" :prepend="__('Conflicts')">{{ conflictCount }}</Badge>
                </PanelHeader>
                <Card>
                    <Description class="mb-3">
                        {{ __('The survivor keeps its ID, slug, and public URL. The others are retired and redirected to it.') }}
                    </Description>
                    <ul class="space-y-2">
                        <li v-for="candidate in candidates" :key="candidate.id">
                            <label class="flex items-start gap-2 cursor-pointer">
                                <input
                                    type="radio"
                                    name="survivor"
                                    class="mt-1"
                                    :value="candidate.id"
                                    :checked="survivorId === candidate.id"
                                    @change="chooseSurvivor(candidate.id)"
                                />
                                <span>
                                    <span class="font-medium">{{ candidate.title }}</span>
                                    <Badge v-if="candidate.is_survivor && survivorId === candidate.id" class="ml-2">{{ __('Default') }}</Badge>
                                    <span class="block text-xs text-gray">
                                        {{ candidate.slug }} &middot; {{ candidate.site }}
                                        <span v-if="candidate.url"> &middot; {{ candidate.url }}</span>
                                    </span>
                                </span>
                            </label>
                        </li>
                    </ul>
                </Card>
            </Panel>

            <!-- 2. Field-by-field reconciliation -->
            <Panel class="mb-6">
                <PanelHeader><Heading>{{ __('Fields') }}</Heading></PanelHeader>
                <Card>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left border-b">
                                <th class="py-2 pr-4">{{ __('Field') }}</th>
                                <th v-for="candidate in candidates" :key="candidate.id" class="py-2 px-2">
                                    {{ candidate.title }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="field in fields"
                                :key="field.handle"
                                class="border-b align-top"
                                :class="field.conflict ? 'bg-amber-50 dark:bg-amber-950' : ''"
                            >
                                <td class="py-2 pr-4">
                                    <span class="font-medium">{{ field.display }}</span>
                                    <Badge v-if="field.conflict" color="orange" class="ml-1">{{ __('Conflict') }}</Badge>
                                    <span class="block text-xs text-gray">{{ field.handle }}</span>
                                </td>
                                <td v-for="candidate in candidates" :key="candidate.id" class="py-2 px-2">
                                    <label class="flex items-start gap-2 cursor-pointer">
                                        <input
                                            type="radio"
                                            class="mt-1"
                                            :name="`field_${field.handle}`"
                                            :value="candidate.id"
                                            v-model="selections[field.handle]"
                                        />
                                        <span :class="valueFor(field, candidate.id)?.has_value ? '' : 'text-gray italic'">
                                            {{ valueFor(field, candidate.id)?.has_value ? valueFor(field, candidate.id).display : __('— empty —') }}
                                        </span>
                                    </label>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </Card>
            </Panel>

            <!-- 3. Multisite footprint -->
            <Panel v-if="Object.keys(sites).length" class="mb-6">
                <PanelHeader><Heading>{{ __('Sites') }}</Heading></PanelHeader>
                <Card>
                    <Description class="mb-3">
                        {{ __('Each retired URL below will redirect to the survivor in that site (or its canonical URL).') }}
                    </Description>
                    <ul class="space-y-2 text-sm">
                        <li v-for="(rows, site) in sites" :key="site">
                            <span class="font-medium">{{ site }}</span>
                            <ul class="ml-4">
                                <li v-for="row in rows" :key="row.entry_id" class="text-gray">
                                    {{ row.url || row.slug }}
                                    <Badge v-if="row.is_survivor" class="ml-1">{{ __('Survivor') }}</Badge>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </Card>
            </Panel>

            <!-- 4. Preview + commit -->
            <Panel>
                <PanelHeader class="flex items-center justify-between">
                    <Heading>{{ __('Review & merge') }}</Heading>
                    <div class="flex gap-2">
                        <Button :text="__('Preview')" :disabled="previewing" @click="runPreview" />
                        <Button
                            variant="primary"
                            :text="__('Merge')"
                            :disabled="!preview || committing"
                            @click="commit"
                        />
                    </div>
                </PanelHeader>
                <Card>
                    <Description v-if="!preview">
                        {{ __('Preview the merge to see what will change before committing. Nothing is written until you confirm.') }}
                    </Description>
                    <div v-else class="flex flex-wrap gap-2">
                        <Badge :prepend="__('Survivor')">{{ candidateTitle(preview.survivor_id) }}</Badge>
                        <Badge :prepend="__('Fields taken from others')">{{ preview.applied_fields.length }}</Badge>
                        <Badge :prepend="__('References re-pointed')">{{ preview.references.occurrences }}</Badge>
                        <Badge :prepend="__('Items updated')">{{ preview.references.items_changed }}</Badge>
                        <Badge :prepend="__('Redirects')">{{ preview.redirects_created }}</Badge>
                        <Badge color="red" :prepend="__('Entries retired')">{{ preview.retired_ids.length }}</Badge>
                    </div>
                </Card>
            </Panel>
        </template>
    </div>
</template>
