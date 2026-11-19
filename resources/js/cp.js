// Control Panel entry point. Components, fieldtypes and Inertia pages register inside the
// booting callback so they run once Statamic has initialized.

import Isco08Fieldtype from './components/fieldtypes/Isco08Fieldtype.vue'
import RoleTitleFieldtype from './components/fieldtypes/RoleTitleFieldtype.vue'
import { evaluateRoleTitleDefaultConfigurationVisible } from './conditions/evaluateRoleTitleDefaultConfigurationVisible.js'
import PersonNameDisplayFormatFieldtype from './components/fieldtypes/PersonNameDisplayFormatFieldtype.vue'
import PersonNameFieldtype from './components/fieldtypes/PersonNameFieldtype.vue'
import TextPhoneticPairFieldtype from './components/fieldtypes/TextPhoneticPairFieldtype.vue'
import PeopleMerge from './pages/People/Merge.vue'

Statamic.booting(() => {
    // A fieldtype's component registers as `<handle>-fieldtype`
    Statamic.$components.register('person_name-fieldtype', PersonNameFieldtype)
    Statamic.$components.register('person_name_display_format-fieldtype', PersonNameDisplayFormatFieldtype)
    Statamic.$components.register('text_phonetic_pair-fieldtype', TextPhoneticPairFieldtype)
    Statamic.$components.register('role_title-fieldtype', RoleTitleFieldtype)
    Statamic.$components.register('isco_08-fieldtype', Isco08Fieldtype)

    // The `role_title` settings hide the default configuration until it has a choice to make
    Statamic.$conditions.add('roleTitleDefaultConfigurationVisible', evaluateRoleTitleDefaultConfigurationVisible)

    // The people merge reconciliation screen, rendered by `MergeController::show()`
    Statamic.$inertia.register('aether-people::PeopleMerge', PeopleMerge)
})
