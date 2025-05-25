@lang('blade at directive')
{{ __('blade double underscore') }}
{{ __('exists in all locales') }}
{{ __('only in ja') }}

@php
    // these may require larastan to work
    app('translator')->get('via app function');
    \Illuminate\Support\Facades\App::make('translator')->get('via app facade');
    app(\Illuminate\Translation\Translator::class)->get('via app function with class');
@endphp
