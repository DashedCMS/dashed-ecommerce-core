<x-filament::page>
    <style>
        .ckp-search { display:flex; gap:.5rem; margin-bottom:1rem; flex-wrap:wrap; }
        .ckp-search input { flex:1 1 16rem; border:1px solid rgba(0,0,0,.15); border-radius:.5rem; padding:.45rem .75rem; background:transparent; }
        .ckp-head { display:flex; justify-content:space-between; align-items:baseline; gap:1rem; flex-wrap:wrap; margin-bottom:1rem; }
        .ckp-head h2 { font-size:1.25rem; font-weight:600; margin:0; }
        .ckp-muted { color:#6b7280; font-size:.875rem; }
        .ckp-stats { display:grid; grid-template-columns:repeat(auto-fit, minmax(10rem, 1fr)); gap:.75rem; margin-bottom:1rem; }
        .ckp-stat { border:1px solid rgba(0,0,0,.08); border-radius:.75rem; padding:.75rem 1rem; }
        .ckp-stat dt { color:#6b7280; font-size:.75rem; }
        .ckp-stat dd { font-size:1.05rem; font-weight:600; margin:.15rem 0 0; word-break:break-all; }
        .ckp-table { width:100%; font-size:.875rem; border-collapse:collapse; }
        .ckp-table th { text-align:left; color:#6b7280; font-weight:500; padding:.4rem .5rem; border-bottom:1px solid rgba(0,0,0,.08); }
        .ckp-table td { padding:.4rem .5rem; border-bottom:1px solid rgba(0,0,0,.05); }
        .ckp-table a { color:rgb(var(--primary-600)); text-decoration:underline; }
        .ckp-grid { display:grid; gap:1rem; }
        .ckp-note { border-left:3px solid rgb(var(--primary-500)); padding:.25rem .75rem; margin-bottom:.75rem; }
        .ckp-note textarea { width:100%; min-height:5rem; border:1px solid rgba(0,0,0,.15); border-radius:.5rem; padding:.5rem; background:transparent; }
        .ckp-link { background:none; border:0; color:#dc2626; cursor:pointer; font-size:.75rem; padding:0; }
    </style>

    <form wire:submit="find" class="ckp-search">
        <input type="email" wire:model="search" placeholder="{{ __('Zoek een klant op e-mailadres') }}">
        <x-filament::button type="submit">{{ __('Zoeken') }}</x-filament::button>
    </form>

    @php($profile = $this->profile())

    @if(! $profile)
        <p class="ckp-muted">{{ __('Kies een klant vanuit de gebruikerslijst of een bestelling, of zoek op e-mailadres.') }}</p>
    @else
        <div class="ckp-head">
            <h2>{{ $profile->name() }}</h2>
            <span class="ckp-muted">
                {{ $profile->email }}
                @if($profile->user)
                    · {{ __('Account sinds :datum', ['datum' => $profile->user->created_at?->format('d-m-Y')]) }}
                @else
                    · {{ __('Gast zonder account') }}
                @endif
            </span>
        </div>

        <dl class="ckp-stats">
            @foreach($this->summaryStats($profile) as $stat)
                <div class="ckp-stat"><dt>{{ $stat['label'] }}</dt><dd>{{ $stat['value'] }}</dd></div>
            @endforeach
        </dl>

        <div class="ckp-grid">
            @foreach($profile->sections() as $section)
                <x-filament::section :key="'ckp-'.$section['key']">
                    <x-slot name="heading">{{ $section['label'] }}</x-slot>
                    @if(! empty($section['data']['note']))
                        <x-slot name="description">{{ $section['data']['note'] }}</x-slot>
                    @endif

                    @if(! empty($section['data']['stats']))
                        <dl class="ckp-stats">
                            @foreach($section['data']['stats'] as $stat)
                                <div class="ckp-stat"><dt>{{ $stat['label'] }}</dt><dd>{{ $stat['value'] }}</dd></div>
                            @endforeach
                        </dl>
                    @endif

                    @if(! empty($section['data']['columns']))
                        @if(empty($section['data']['rows']))
                            <p class="ckp-muted">{{ $section['data']['empty'] ?? __('Niets gevonden.') }}</p>
                        @else
                            <table class="ckp-table">
                                <thead>
                                    <tr>
                                        @foreach($section['data']['columns'] as $kop)
                                            <th>{{ $kop }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($section['data']['rows'] as $row)
                                        <tr>
                                            @foreach(array_keys($section['data']['columns']) as $i => $kolom)
                                                <td>
                                                    @if($i === 0 && ! empty($row['_url']))
                                                        <a href="{{ $row['_url'] }}">{{ $row[$kolom] ?? '' }}</a>
                                                    @else
                                                        {{ $row[$kolom] ?? '' }}
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    @endif

                    @if(! empty($section['data']['url']))
                        <p style="margin-top:.5rem"><a class="ckp-muted" href="{{ $section['data']['url'] }}">{{ __('Alles bekijken') }}</a></p>
                    @endif
                </x-filament::section>
            @endforeach

            <x-filament::section>
                <x-slot name="heading">{{ __('Notities') }}</x-slot>
                <x-slot name="description">{{ __('Alleen zichtbaar in het CMS.') }}</x-slot>

                @foreach($this->notes($profile) as $note)
                    <div class="ckp-note" wire:key="note-{{ $note->id }}">
                        <div style="white-space:pre-line">{{ $note->note }}</div>
                        <div class="ckp-muted">
                            {{ $note->author?->name ?? __('Onbekend') }} · {{ $note->created_at?->format('d-m-Y H:i') }}
                            @can('edit_user')
                                · <button type="button" class="ckp-link" wire:click="deleteNote({{ $note->id }})" wire:confirm="{{ __('Deze notitie verwijderen?') }}">{{ __('Verwijderen') }}</button>
                            @endcan
                        </div>
                    </div>
                @endforeach

                @can('edit_user')
                    <form wire:submit="addNote" class="ckp-note" style="border:0;padding:0">
                        <textarea wire:model="note" maxlength="5000" placeholder="{{ __('Nieuwe notitie') }}"></textarea>
                        <x-filament::button type="submit" size="sm" style="margin-top:.5rem">{{ __('Notitie toevoegen') }}</x-filament::button>
                    </form>
                @endcan
            </x-filament::section>
        </div>
    @endif
</x-filament::page>
