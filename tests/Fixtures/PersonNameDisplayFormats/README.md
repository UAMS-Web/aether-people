# Person-name display-format fixtures

These files hold `UAMSWeb\Aether\People\Support\PersonNameDisplayFormats::buildOptions()` and its JavaScript twin, `buildDisplayFormatOptions()` in `resources/js/components/fieldtypes/person-name/personNameDisplayFormats.js`, to the same output (UAMS-Web/uams-statamic-archive#3053, decided on UAMS-Web/uams-statamic-archive#3043).

- `corpus.json` is the input: normalized person-name values, each with an `include_nickname` flag. Edit it by hand.
- `display-format-options.json` is generated from the PHP. Do not edit it by hand.

After changing the corpus or the PHP option builder, regenerate and commit the result:

```bash
php scripts/generate-person-name-display-format-fixtures.php
```

`tests/Unit/Support/PersonNameDisplayFormatsFixturesTest.php` fails until the committed file matches the PHP, and `personNameDisplayFormats.fixtures.test.js` (run by `npm run test:js`) fails when the JavaScript no longer matches the file. A JavaScript-only change never needs a regenerate: change the JavaScript until the spec passes.

**The nickname-off mapping.** The JavaScript takes `{ includeNickname: false }`; `buildOptions()` has no such option. A field configured with `include_nickname: false` reaches the PHP through `PersonName::applyIncludeNicknameConfig()`, which sets `no_nickname` and clears the nickname. The generator applies that step for a case whose `include_nickname` is false, and the spec passes the same flag as `includeNickname`.
