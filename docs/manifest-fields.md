# NativePHP Manifest Fields

This file documents the `nativephp.json` placeholders used by `{{ vendor }}/{{ package }}` for NativePHP Mobile v3 and v4.

| Placeholder | Meaning |
| --- | --- |
| `{{ vendor }}` | Composer vendor and source control owner. |
| `{{ package }}` | Composer package name without the vendor prefix. |
| `{{ plugin }}` | Public NativePHP bridge namespace, facade name, and platform symbol prefix. |
| `{{ namespace }}` | PHP namespace used by the package. |
| `{{ description }}` | Short package description shown by Composer and NativePHP tooling. |

| Field | Purpose |
| --- | --- |
| `namespace` | JavaScript and bridge namespace exposed to NativePHP apps, matching official plugins such as `Camera`, `Device`, `Browser`, `Share`, `File`, and `Dialog`. |
| `bridge_functions` | NativePHP bridge entries. Each entry maps a public bridge name such as `{{ plugin }}.Example` to Android and iOS handlers. |
| `bridge_functions[].android` | Kotlin class and nested bridge function in the Android package. Official plugins use values like `com.nativephp.device.DeviceFunctions.GetInfo`. |
| `bridge_functions[].ios` | Swift symbol used by NativePHP Mobile on iOS. Official plugins use values like `DeviceFunctions.GetInfo`. |
| `android.min_version` | Minimum Android API level supported by the native implementation. Official Mobile v3 plugins currently use `26`. |
| `android.permissions` | Android manifest permissions required by the plugin. Keep it empty until the native feature needs permissions. |
| `android.dependencies.implementation` | Gradle implementation dependencies needed by the plugin. |
| `android.features` | Android hardware or software features required by the plugin. Keep it empty unless the plugin needs a declared feature. |
| `ios.min_version` | Minimum iOS version supported by the native implementation. Official Mobile v3 plugins currently use `18.2`. |
| `ios.info_plist` | Usage descriptions and other Info.plist keys required by iOS permissions or capabilities. |
| `ios.dependencies.swift_packages` | Swift Package Manager dependencies required by the plugin. |
| `ios.dependencies.pods` | CocoaPods dependencies required by the plugin. |
| `events` | PHP event classes emitted from native code back into the Laravel application. |
