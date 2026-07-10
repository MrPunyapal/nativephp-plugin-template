# Bridge Functions

A bridge function connects PHP to native platform code.

## PHP to Android

```text
{{ namespace }}\Facades\{{ plugin }}::example()
  -> {{ namespace }}\Plugin::example()
  -> "{{ plugin }}.Example"
  -> com.{{ vendor }}.{{ package }}.{{ plugin }}Functions.Example
  -> Android platform logic
  -> JSONObject response
```

## PHP to iOS

```text
{{ namespace }}\Facades\{{ plugin }}::example()
  -> {{ namespace }}\Plugin::example()
  -> "{{ plugin }}.Example"
  -> {{ plugin }}Functions.Example
  -> iOS platform logic
  -> dictionary response
```

## Adding A Function

```bash
php add-function.php --name=Level --description="Reads the battery level."
```

This does, in one command, what would otherwise mean hand-editing six files across three languages:

1. Add a `bridge_functions` entry to `nativephp.json`.
2. Add a PHP method on `{{ namespace }}\Contracts\{{ plugin }}Contract`.
3. Implement the method on `{{ namespace }}\Plugin`.
4. Add the `@method` line to `{{ namespace }}\Facades\{{ plugin }}`.
5. Add the Kotlin class under `resources/android`.
6. Add the Swift class under `resources/ios`.
7. Add a Pest test for the PHP call.

Flags:

| Flag | Effect |
| --- | --- |
| `--name=Level` | Required. PascalCase bridge function name. |
| `--description="..."` | Optional. Stored in the `nativephp.json` entry. |
| `--skip-android` | Skip the Kotlin file and omit the manifest `android` key. |
| `--skip-ios` | Skip the Swift file and omit the manifest `ios` key. |
| `--dry-run` | Print the planned changes; write nothing. |
| `--no-interaction` | Don't prompt; fail if `--name` is missing. |

Run it once per bridge function, as many times as needed. It validates before writing anything: it rejects names that already exist (checked against both `nativephp.json` and the contract), names that aren't PascalCase, and names that collide with PHP, Kotlin, or Swift reserved words.

If it can't find an Android or iOS bridge file to splice a new function into, it skips that file, warns you, and still finishes the rest — add the native class by hand in that case.
