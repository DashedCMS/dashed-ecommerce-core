@php($full = (int) round((float) $rating))
<span class="dpr-stars" aria-label="{{ number_format((float) $rating, 1, ',', '') }} / 5">@for($i = 1; $i <= 5; $i++)<span class="{{ $i <= $full ? '' : 'dpr-off' }}">&#9733;</span>@endfor</span>
