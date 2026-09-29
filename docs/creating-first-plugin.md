# Creating First Plugin

1. Clone `nativephp-plugin-template`.\n2. The template supports NativePHP Mobile v3 and v4. By default, generated packages allow both with `nativephp/mobile` `^3.0|^4.0`.
3. Run `php configure.php`.
4. Review `composer.json`, `nativephp.json`, PHP namespaces, and native bridge targets.
5. Replace the template bridge function body with platform code.
6. Run tests and static analysis.

Keep the package type as `nativephp-plugin`.

The public bridge name should stay stable after release because NativePHP apps call it through the manifest name.

Use `--no-interaction` when driving the template from a scaffolder:

```bash
php configure.php --no-interaction --vendor=acme --package=mobile-battery --plugin=Battery --namespace="Acme\\MobileBattery" --description="NativePHP Mobile battery plugin." --android-package=mobilebattery
```
