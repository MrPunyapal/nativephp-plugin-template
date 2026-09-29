# FAQ

## Which NativePHP Mobile versions does this support?\n\nThe template supports NativePHP Mobile v3 and v4. Generated packages use `nativephp/mobile` `^3.0|^4.0` by default.\n\n## Is this a demo plugin?

No. `{{ vendor }}/{{ package }}` is a reusable template for building plugins.

## Can this become a Composer create-project package?

Yes. The placeholder model and `stubs/` directory are designed for that workflow.

## Can this power `nativephp-plugin-maker`?

Yes. The stubs contain all core artifacts a companion scaffolder needs to generate a new plugin.

## Should every plugin have permissions?

No. Declare permissions only when a native API requires them.

## Where does native code live?

NativePHP plugin source lives in `resources/android` and `resources/ios`.
