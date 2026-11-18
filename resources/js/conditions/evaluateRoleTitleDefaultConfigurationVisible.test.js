import { describe, expect, it } from 'vitest'
import { evaluateRoleTitleDefaultConfigurationVisible as visible } from './evaluateRoleTitleDefaultConfigurationVisible.js'

describe('evaluateRoleTitleDefaultConfigurationVisible', () => {
    it('shows the default configuration when structured mode has several configurations', () => {
        expect(visible({ target: ['structured'], values: { enabled_configurations: ['a', 'b'] } })).toBe(true)
    })

    it('hides it without structured mode', () => {
        expect(visible({ target: ['unstructured'], values: { enabled_configurations: ['a', 'b'] } })).toBe(false)
    })

    it('hides it with a single configuration', () => {
        expect(visible({ target: ['structured'], values: { enabled_configurations: ['a'] } })).toBe(false)
    })

    it('reads the structures from the form when the target is not a list', () => {
        expect(visible({ target: null, values: { enabled_title_structures: ['structured'], enabled_configurations: ['a', 'b'] } })).toBe(true)
    })
})
