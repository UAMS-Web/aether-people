Written against v0.5.4.

The add-on ships five fieldtypes. Statamic discovers them from `src/Fieldtypes/`, and each one's handle is its component name.

| Handle | Offered in the blueprint editor | Stores |
| --- | --- | --- |
| `person_name` | collections and forms | first, middle, nickname, last and suffix parts, each with phonetic text |
| `person_name_display_format` | no | a display format key |
| `text_phonetic_pair` | collections and forms | `text` and `phonetic_text` |
| `role_title` | collections only | a structured or free-text role title |
| `isco_08` | collections | an ISCO-08 occupation chain |

## Validators the host provides

`person_name` and `text_phonetic_pair` validate text with the Laravel rules `name` and `international_phonetic_alphabet`. The add-on does not define either rule, so the host must register them. See [Host bindings](Host-bindings.md#validation-rules).

## person_name

Captures a legal name. Config options, all toggles:

| Option | Default | Effect |
| --- | --- | --- |
| `require_text` | on | The name parts require text. |
| `require_phonetic` | off | The phonetic parts are required. |
| `include_phonetic_instructions` | on | Shows the instructions for entering phonetic text. |
| `include_display_format` | on | Includes the display format chooser. When off, the stored `display_format` is cleared. |
| `include_nickname` | on | Includes the nickname part. |
| `include_phonetic` | on | Includes phonetic text. When off, every stored `phonetic_text` is cleared. |

Stored value: `first_name`, `middle_name`, `nickname` and `last_name`, each `{text, phonetic_text}`; the flags `no_middle_name`, `no_nickname` and `no_generational_suffix`; `generational_suffix`; and `display_format`.

On a public form the fieldtype renders through `resources/field-views/forms/fields/person_name.antlers.html`; a host copy of that view wins.

## person_name_display_format

Holds the chosen display format key beside a `person_name`. It is not offered in the blueprint editor. Its one option, `require_when_available` (default on), requires a format to be chosen once every name part the formats depend on is filled. The stored value is a string or `null`.

## text_phonetic_pair

A text input with a paired phonetic input. Options:

| Option | Default | Effect |
| --- | --- | --- |
| `text_label`, `phonetic_label` | empty | Labels for the two inputs. |
| `quote_straight_on_text` | off | Shows straight double quotes as a decorative prefix and suffix on the text input. They are not stored. |
| `split_inputs` | on | Shows the two inputs side by side. |
| `border` | on | Draws a border around the pair. |
| `body_class` | `space-y-6` | Classes on the body element. |
| `require_text` | on | The text is required. |
| `require_phonetic` | off | The phonetic text is required. |
| `include_phonetic_instructions` | on | Shows the phonetic entry instructions. |

Stored value: `{text, phonetic_text}`, both strings.

## role_title

Builds a role title in one of two modes, `structured` or `unstructured`, under one of four configurations: `faculty`, `academic_leadership`, `institutional_leadership` and `clinical_leadership`. The choices come from [`config/role_title.php`](Configuration.md#role_title).

| Option | Effect |
| --- | --- |
| `enabled_title_structures` | Which of the two modes the editor may use. |
| `default_title_structure` | The mode a new value starts in. Default `structured`. |
| `enabled_configurations` | Which configurations the editor may use. |
| `default_configuration` | The configuration a new value starts in. Hidden until more than one is enabled. |
| `required_unstructured_subfields` | Which unstructured subfields must be filled. |
| `scope_unit_row_link` | The handle of a sibling field naming the organizational unit the role belongs to. A linked scope unit must match it. |
| `border` | Draws a border. Default on. |

A structured value stores its parts; the composed title and the schema.org output are rebuilt each time the value is read, and are removed before saving. An unstructured value keeps the typed title and occupation rows.

Validation refuses:

- a typed scope that uses "in" without the article "the" ("A typed scope that uses \"in\" must use the article \"the\", or link a scope unit instead.");
- a linked scope unit that does not exist or is not an organizational unit;
- a linked scope unit with no public label, or combined with an object;
- a scope unit that differs from the row's own linked unit;
- an O*Net-SOC code in an unstructured title's occupation rows that does not match the pattern `NN-NNNN.NN`: "The value must be a valid O*Net-SOC Occupation Code beginning with two digits, followed by a hyphen, four digits, a period and two digits (e.g., '11-1011.00', '13-2011.01')." An empty value passes.

The fieldtype cannot be rendered on a public form.

## isco_08

Selects an International Standard Classification of Occupations (ISCO-08) code through four tiers: major group, sub-major group, minor group and unit group. The stored value keeps the chosen tiers and a derived `value`; any `value` sent by the browser is ignored. With nothing selected the field stores an empty array. A required field fails with "Select a major group." and a value that is not an array fails with "The ISCO-08 field must be an object." The occupation lists come from the `uams-web/aether-enums` package.

## Where the add-on uses them

The `people` blueprint uses `person_name` (legal name and the alternate full name), `text_phonetic_pair` (nickname), `person_name_display_format` (preferred display format) and `role_title` (each job title row). `isco_08` backs the occupation rows of an unstructured role title.
