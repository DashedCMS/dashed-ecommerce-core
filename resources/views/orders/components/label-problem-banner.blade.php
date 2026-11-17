<div>
    @if($problem)
        <div style="padding: .875rem 1.125rem; border-radius: .75rem; border: 1px solid #ef4444; background: rgba(239, 68, 68, .1); display: flex; flex-direction: column; gap: .5rem;">
            <div style="font-weight: 600;">{{ __('Verzendlabel lukt niet') }}</div>
            <div>{{ $problem->cause }}</div>

            @if($problem->suggestion)
                <div>
                    <button type="button"
                            wire:click="fix"
                            wire:loading.attr="disabled"
                            wire:confirm="{{ __('Het adres van de bestelling wordt aangepast en er wordt een nieuw label gemaakt. Doorgaan?') }}"
                            class="fi-btn fi-btn-size-md fi-color-danger"
                            style="padding: .5rem 1rem; border-radius: .5rem; background: #dc2626; color: #fff; font-weight: 600;">
                        {{ __('Wijzig naar :adres en maak nieuw label', ['adres' => $problem->suggestion->label]) }}
                    </button>
                </div>
            @endif

            <details>
                <summary style="cursor: pointer; opacity: .7;">{{ __('Foutmelding van de vervoerder') }}</summary>
                <pre style="white-space: pre-wrap; overflow-wrap: anywhere; font-size: .8125rem; margin-top: .5rem;">{{ $problem->rawError }}</pre>
            </details>
        </div>
    @endif
</div>
