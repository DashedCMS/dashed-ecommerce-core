@php($body = (string) $getState())
@if($body === '')
    <div style="opacity: .6;">{{ __('Leeg') }}</div>
@else
    <pre style="white-space: pre-wrap; overflow-wrap: anywhere; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .8125rem; line-height: 1.45; max-height: 36rem; overflow: auto; padding: .75rem; border-radius: .5rem; background: rgba(127, 127, 127, .08);">{{ $body }}</pre>
@endif
