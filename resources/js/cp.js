// Control Panel entry point. Components, fieldtypes and Inertia pages register inside the
// booting callback so they run once Statamic has initialized.

import PersonNameDisplayFormatFieldtype from './components/fieldtypes/PersonNameDisplayFormatFieldtype.vue'
import PersonNameFieldtype from './components/fieldtypes/PersonNameFieldtype.vue'
import TextPhoneticPairFieldtype from './components/fieldtypes/TextPhoneticPairFieldtype.vue'
import PeopleMerge from './pages/People/Merge.vue'

Statamic.booting(() => {
    // A fieldtype's component registers as `<handle>-fieldtype`
    Statamic.$components.register('person_name-fieldtype', PersonNameFieldtype)
    Statamic.$components.register('person_name_display_format-fieldtype', PersonNameDisplayFormatFieldtype)
    Statamic.$components.register('text_phonetic_pair-fieldtype', TextPhoneticPairFieldtype)

    // The people merge reconciliation screen, rendered by `MergeController::show()`
    Statamic.$inertia.register('aether-people::PeopleMerge', PeopleMerge)
})
