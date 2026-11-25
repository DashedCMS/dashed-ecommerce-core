{{--
    Avatar van een medewerker. $user is een User-model of een payload {id, name, avatar_url}.
    Model + dashed-core met <x-dashed-core::user-avatar>: dat component; anders de avatar_url,
    en zonder URL een initialenbolletje.
--}}
@php
    $avatarUser = $user ?? null;
    $avatarSize = $size ?? 'sm';
    $avatarPx = ['sm' => 24, 'md' => 32, 'lg' => 48][$avatarSize] ?? 24;
    $avatarIsModel = is_object($avatarUser);
    $avatarName = (string) ($avatarIsModel ? ($avatarUser->name ?? '') : ($avatarUser['name'] ?? ''));
    $avatarUrl = $avatarIsModel ? rescue(fn () => $avatarUser->avatar_url, null, false) : ($avatarUser['avatar_url'] ?? null);
    $avatarInitials = collect(preg_split('/\s+/', trim($avatarName)) ?: [])
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('') ?: '?';
@endphp
@if($avatarIsModel && view()->exists('dashed-core::components.user-avatar'))
    <x-dashed-core::user-avatar :user="$avatarUser" :size="$avatarSize" />
@elseif($avatarUrl)
    <img src="{{ $avatarUrl }}" alt="{{ $avatarName }}" title="{{ $avatarName }}" width="{{ $avatarPx }}" height="{{ $avatarPx }}" loading="lazy" decoding="async"
         class="dashed-user-avatar" style="display:inline-block;flex-shrink:0;width:{{ $avatarPx }}px;height:{{ $avatarPx }}px;border-radius:9999px;object-fit:cover;">
@else
    <span class="dashed-user-avatar" title="{{ $avatarName }}" aria-hidden="true"
          style="display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;width:{{ $avatarPx }}px;height:{{ $avatarPx }}px;border-radius:9999px;background:#e5e7eb;color:#374151;font-size:{{ max(9, (int) round($avatarPx * 0.42)) }}px;font-weight:600;line-height:1;">{{ $avatarInitials }}</span>
@endif
