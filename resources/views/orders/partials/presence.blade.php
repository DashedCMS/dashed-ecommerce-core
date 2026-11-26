{{--
    Wie heeft deze bestelling nu open: $presence = ['viewers' => [...], 'working_on' => [...]]
    (OrderPresence::presenceFor/presenceForMany). Toont avatars met "bezig" of "kijkt",
    zodat collega's niet tegelijk dezelfde bestelling inpakken.
--}}
@php
    $presenceWorking = $presence['working_on'] ?? [];
    $presenceViewers = $presence['viewers'] ?? [];
    $presenceFirst = fn (array $u): string => strtok(trim((string) ($u['name'] ?? '')), ' ') ?: __('Iemand');
@endphp
@if($presenceWorking !== [] || $presenceViewers !== [])
    <span style="display:inline-flex;flex-wrap:wrap;align-items:center;gap:6px;">
        @foreach([['list' => $presenceWorking, 'label' => __('bezig'), 'bg' => '#fef3c7', 'fg' => '#92400e'], ['list' => $presenceViewers, 'label' => __('kijkt'), 'bg' => '#e0f2fe', 'fg' => '#075985']] as $group)
            @if($group['list'] !== [])
                <span title="{{ implode(', ', array_map(fn ($u) => $u['name'] ?? '', $group['list'])) }} ({{ $group['label'] }})"
                      style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px 2px 2px;border-radius:9999px;background:{{ $group['bg'] }};color:{{ $group['fg'] }};font-size:12px;font-weight:600;line-height:1.2;white-space:nowrap;">
                    <span style="display:inline-flex;">
                        @foreach(array_slice($group['list'], 0, 3) as $i => $u)
                            <span style="display:inline-flex;{{ $i > 0 ? 'margin-left:-6px;' : '' }}border-radius:9999px;box-shadow:0 0 0 2px {{ $group['bg'] }};">
                                @include('dashed-ecommerce-core::orders.partials.user-avatar', ['user' => $u, 'size' => 'sm'])
                            </span>
                        @endforeach
                    </span>
                    {{ count($group['list']) === 1 ? $presenceFirst($group['list'][0]) : count($group['list']) }} {{ $group['label'] }}
                </span>
            @endif
        @endforeach
    </span>
@endif
