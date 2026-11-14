// Control Panel entry point. Components, fieldtypes and Inertia pages register inside the
// booting callback so they run once Statamic has initialized.

import PersonNameDisplayFormatFieldtype from './components/fieldtypes/PersonNameDisplayFormatFieldtype.vue'

Statamic.booting(() => {
    // A fieldtype's component registers as `<handle>-fieldtype`
    Statamic.$components.register('person_name_display_format-fieldtype', PersonNameDisplayFormatFieldtype)
})
