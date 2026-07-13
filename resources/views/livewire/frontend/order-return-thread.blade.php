<div>
    @if ($orderReturn)
        <div class="return-thread">
            <h2>Berichten</h2>

            <ul class="return-thread__messages">
                @forelse ($messages as $message)
                    <li class="return-thread__message return-thread__message--{{ $message->sender }}">
                        <span class="return-thread__sender">
                            {{ $message->isFromCustomer() ? 'Jij' : 'Klantenservice' }}
                        </span>
                        <span class="return-thread__time">{{ $message->created_at->format('d-m-Y H:i') }}</span>
                        <div class="return-thread__body">
                            @if ($message->isFromCustomer())
                                {{ $message->message }}
                            @else
                                {!! $message->message !!}
                            @endif
                        </div>
                    </li>
                @empty
                    <li class="return-thread__empty">Er zijn nog geen berichten.</li>
                @endforelse
            </ul>

            <form wire:submit.prevent="send" class="return-thread__form">
                <label for="return-reply">Stuur een bericht</label>
                <textarea id="return-reply" wire:model="reply" rows="4"></textarea>
                @error('reply') <span class="return-thread__error">{{ $message }}</span> @enderror
                @if ($rateLimitMessage) <span class="return-thread__error">{{ $rateLimitMessage }}</span> @endif
                <button type="submit" wire:loading.attr="disabled" wire:target="send">Versturen</button>
            </form>
        </div>
    @endif
</div>
