<div wire:poll.20s="pingPresence">
    @if($assignedUser || $viewers || $workingOn)
        <div class="au-od-banner" data-assignment>
            <div style="display:flex;flex-direction:column;gap:6px;">
                @if($assignedUser)
                    <div class="au-od-strong" style="display:flex;align-items:center;gap:8px;">
                        @include('dashed-ecommerce-core::orders.partials.user-avatar', ['user' => $assignedUser, 'size' => 'md'])
                        <span>
                            {{ $assignedAt
                                ? __('Toegewezen aan :naam sinds :datum', ['naam' => $assignedTo, 'datum' => $assignedAt->format('d-m-Y H:i')])
                                : __('Toegewezen aan :naam', ['naam' => $assignedTo]) }}
                        </span>
                    </div>
                @elseif($assignedTo)
                    <div class="au-od-strong">{{ __('Toegewezen aan :naam', ['naam' => $assignedTo]) }}</div>
                @endif
                @if($workingOn)
                    <div class="au-od-small" data-working-on style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                        @foreach($workingOn as $person)
                            @include('dashed-ecommerce-core::orders.partials.user-avatar', ['user' => $person, 'size' => 'sm'])
                        @endforeach
                        <span>{{ count($workingOn) === 1
                            ? __(':naam is ermee bezig', ['naam' => $workingOn[0]['name']])
                            : __(':namen zijn ermee bezig', ['namen' => implode(', ', array_column($workingOn, 'name'))]) }}</span>
                    </div>
                @endif
                @if($viewers)
                    <div class="au-od-small au-od-muted" data-other-viewers style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                        @foreach($viewers as $person)
                            @include('dashed-ecommerce-core::orders.partials.user-avatar', ['user' => $person, 'size' => 'sm'])
                        @endforeach
                        <span>{{ __('Ook open bij: :namen', ['namen' => implode(', ', array_column($viewers, 'name'))]) }}</span>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
