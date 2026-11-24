<div wire:poll.20s="pingPresence">
    @if($assignedTo || $otherViewers)
        <div class="au-od-banner" data-assignment>
            <div>
                @if($assignedTo)
                    <div class="au-od-strong">
                        {{ $assignedAt
                            ? __('Toegewezen aan :naam sinds :datum', ['naam' => $assignedTo, 'datum' => $assignedAt->format('d-m-Y H:i')])
                            : __('Toegewezen aan :naam', ['naam' => $assignedTo]) }}
                    </div>
                @endif
                @if($otherViewers)
                    <div class="au-od-small au-od-muted" data-other-viewers>
                        {{ __('Ook open bij: :namen', ['namen' => implode(', ', $otherViewers)]) }}
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
