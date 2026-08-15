# Blade templates

[Bladestan](https://github.com/bladestan/bladestan) makes translation calls in reachable Blade templates available to
PHPStan. Install and configure Bladestan according to its documentation; core PHP translation-call analysis does not
depend on it.

Bladestan compiles templates and analyzes them through a nested PHPStan run. Lost in Translation enables
`bridgeBladeDiagnostics` by default to relay its diagnostics to the outer analysis while preserving stable identifiers,
translation metadata, tips, and Bladestan's template path and line metadata.

Set `bridgeBladeDiagnostics: false` only if this compatibility bridge conflicts with another extension or a future
Bladestan release:

```neon
parameters:
    lostInTranslation:
        bridgeBladeDiagnostics: false
```

Given a reachable view:

```php
<?php

view('sample', [
    'var' => 'val',
]);
```

and its template:

```bladehtml
@lang('blade at directive')
{{ __('blade double underscore') }}
{{ __('exists in all locales') }}
{{ __('only in ja') }}

@php
    app('translator')->get('via app function');
    \Illuminate\Support\Facades\App::make('translator')->get('via app facade');
    app(\Illuminate\Translation\Translator::class)->get('via app function with class');
@endphp
```

missing-key diagnostics are attributed to the template, for example:

```text
Missing translation string "blade at directive" for locales: ja
rendered in: sample.blade.php:1
```

When `unusedTranslationStrings` is enabled, a parallel process-local bridge preserves constant keys found by the nested
analysis so those Blade calls count as uses. Both bridges are analysis plumbing; they are unrelated to Laravel's queue
system.
