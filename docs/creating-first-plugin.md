# Creating First Plugin

1. Clone `nativephp-plugin-template`.
2. Run `php configure.php`.
3. Review `composer.json`, `nativephp.json`, PHP namespaces, and native bridge targets.
4. Run `php add-function.php --name=Level --description="Reads the battery level."` for each bridge function your plugin needs, replacing the template `Example` function with your own.
5. Implement the native logic in `resources/android/*.kt` and `resources/ios/*.swift`.
6. Run tests and static analysis.

Keep the package type as `nativephp-plugin`.

The public bridge name should stay stable after release because NativePHP apps call it through the manifest name.

Use `--no-interaction` when driving the template from a scaffolder:

```bash
php configure.php --no-interaction --vendor=acme --package=mobile-battery --plugin=Battery --namespace="Acme\\MobileBattery" --description="NativePHP Mobile battery plugin." --android-package=mobilebattery
```

## What add-function.php does under the hood

Adding a bridge function by hand means editing six files across three languages, and a missed one only fails at runtime on a device. `add-function.php` automates exactly that manual sequence:

1. Add a `bridge_functions` entry to `nativephp.json`.
2. Add a method to the plugin's `Contract`.
3. Implement the method on `Plugin`.
4. Add the `@method` line to the facade docblock.
5. Add the Kotlin class under `resources/android`.
6. Add the Swift class under `resources/ios`.
7. Add a Pest test asserting the wrapper forwards to the bridge with the right function name and payload.

See `docs/bridge-functions.md` for the full flag list.
