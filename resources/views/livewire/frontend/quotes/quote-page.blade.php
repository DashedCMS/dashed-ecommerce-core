@php
    use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
    use Dashed\DashedEcommerceCore\Services\Quotes\QuotePdf;
    use Dashed\DashedEcommerceCore\Services\Quotes\QuoteBranding;

    $brand = QuoteBranding::for($quote);
    $exVat = (bool) $quote->prices_ex_vat;
    $pdfUrl = QuotePdf::downloadUrl($quote);
@endphp

<section class="dq">
    @include('dashed-ecommerce-core::quotes.partials.styles', ['color' => $brand->color()])
    <style>
        .dq-qty-m { display: none; }
        @media (max-width: 560px) { .dq-qty-m { display: inline; } }
    </style>

    <div class="dq-wrap">
        <div class="dq-card">
            <div class="dq-head">
                <div>
                    <p class="dq-eyebrow">{{ Translation::get('quote', 'quote', 'Offerte') }} {{ $quote->displayNumber() }}</p>
                    <h1 class="dq-h1">{{ $quote->title ?: $brand->companyName() }}</h1>
                    @if ($quote->fullName() || $quote->company_name)
                        <p class="dq-sub">
                            {{ Translation::get('prepared-for', 'quote', 'Opgesteld voor') }}
                            {{ collect([$quote->company_name, $quote->fullName()])->filter()->implode(', ') }}
                        </p>
                    @endif
                </div>

                <div class="dq-badges">
                    @if ($quote->valid_until)
                        <span class="dq-badge">
                            {{ Translation::get('valid-until', 'quote', 'Geldig t/m') }}
                            <b>{{ $quote->valid_until->translatedFormat('j F Y') }}</b>
                        </span>
                    @endif
                    @if ($pdfUrl)
                        <a href="{{ $pdfUrl }}" class="dq-link">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>
                            {{ Translation::get('download-pdf-short', 'quote', 'PDF downloaden') }}
                        </a>
                    @endif
                </div>
            </div>

            <div class="dq-body">
                <div class="dq-parties">
                    <div>
                        <p class="dq-eyebrow">{{ Translation::get('to', 'quote', 'Aan') }}</p>
                        @if ($quote->company_name)
                            <p><b>{{ $quote->company_name }}</b></p>
                        @endif
                        @if ($quote->fullName())
                            <p>{{ $quote->fullName() }}</p>
                        @endif
                        @if ($quote->invoice_street)
                            <p>{{ $quote->invoice_street }} {{ $quote->invoice_house_nr }}</p>
                        @endif
                        @if (trim($quote->invoice_zip_code.' '.$quote->invoice_city))
                            <p>{{ trim($quote->invoice_zip_code.' '.$quote->invoice_city) }}</p>
                        @endif
                        @if ($quote->email)
                            <p>{{ $quote->email }}</p>
                        @endif
                    </div>
                    <div>
                        <p class="dq-eyebrow">{{ Translation::get('from', 'quote', 'Van') }}</p>
                        <p><b>{{ $brand->companyName() }}</b></p>
                        @foreach ($brand->senderLines() as $line)
                            <p>{{ $line }}</p>
                        @endforeach
                    </div>
                    <div>
                        <p class="dq-eyebrow">{{ Translation::get('details', 'quote', 'Details') }}</p>
                        <p><b>{{ ($quote->sent_at ?? $quote->created_at)->translatedFormat('j F Y') }}</b></p>
                        @if ($quote->reference)
                            <p>{{ Translation::get('reference-short', 'quote', 'Ref:') }} {{ $quote->reference }}</p>
                        @endif
                    </div>
                </div>

                @if ($quote->intro)
                    <div class="dq-text">
                        @include('dashed-ecommerce-core::quotes.partials.text', ['text' => $quote->intro])
                    </div>
                @endif

                <div class="dq-lines">
                    <div class="dq-lines-head">
                        <span>{{ Translation::get('description', 'quote', 'Omschrijving') }}</span>
                        <span class="dq-num">{{ Translation::get('quantity', 'quote', 'Aantal') }}</span>
                        <span class="dq-num">
                            {{ $exVat
                                ? Translation::get('amount-ex-vat', 'quote', 'Bedrag ex btw')
                                : Translation::get('amount-incl-vat', 'quote', 'Bedrag incl. btw') }}
                        </span>
                    </div>

                    @foreach ($quote->lines as $line)
                        @php($isOn = $selected[$line->id] ?? ! $line->is_optional)
                        @if ($line->is_optional)
                            <label class="dq-line is-selectable {{ $isOn ? '' : 'is-off' }}" wire:key="line-{{ $line->id }}">
                        @else
                            <div class="dq-line" wire:key="line-{{ $line->id }}">
                        @endif
                            <div class="dq-line-main">
                                @if ($line->is_optional && $line->choice_group)
                                    <input type="radio" name="choice-{{ $line->choice_group }}"
                                           wire:click="chooseInGroup('{{ $line->choice_group }}', {{ $line->id }})"
                                           @checked($isOn)>
                                @elseif ($line->is_optional)
                                    <input type="checkbox"
                                           wire:click="toggleLine({{ $line->id }})"
                                           @checked($isOn)>
                                @endif
                                <div>
                                    <p class="dq-name">
                                        {{ $line->name }}
                                        <span class="dq-qty-m">&middot; {{ $line->quantity }}x</span>
                                    </p>
                                    @if ($line->description)
                                        <p class="dq-desc">{!! nl2br(e($line->description)) !!}</p>
                                    @endif
                                    @if ($line->is_optional)
                                        <span class="dq-pill">
                                            {{ $line->choice_group
                                                ? Translation::get('choice-option', 'quote', 'Keuze')
                                                : Translation::get('optional', 'quote', 'Optioneel') }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="dq-num dq-qty">{{ $line->quantity }}</div>
                            <div class="dq-num">
                                {{ CurrencyHelper::formatPrice($exVat ? $line->lineTotalExVat() : $line->lineTotal()) }}
                            </div>
                        @if ($line->is_optional)
                            </label>
                        @else
                            </div>
                        @endif
                    @endforeach
                </div>

                <div class="dq-totals">
                    <div class="dq-totals-row">
                        <span>{{ Translation::get('subtotal-ex-vat', 'quote', 'Subtotaal ex btw') }}</span>
                        <span>{{ CurrencyHelper::formatPrice($totals->totalExVat()) }}</span>
                    </div>
                    @foreach ($totals->vatPerRate as $rate => $amount)
                        <div class="dq-totals-row">
                            <span>{{ Translation::get('vat-percentage', 'quote', 'BTW :percentage:%', 'text', ['percentage' => $rate]) }}</span>
                            <span>{{ CurrencyHelper::formatPrice($amount) }}</span>
                        </div>
                    @endforeach
                    <div class="dq-grand">
                        <span>{{ Translation::get('total-incl-vat', 'quote', 'Totaal incl. btw') }}</span>
                        <span>{{ CurrencyHelper::formatPrice($totals->total) }}</span>
                    </div>
                </div>
            </div>
        </div>

        @if ($quote->terms)
            <div class="dq-card">
                <div class="dq-section dq-text">
                    <h2 class="dq-h2">{{ Translation::get('terms', 'quote', 'Planning en voorwaarden') }}</h2>
                    @include('dashed-ecommerce-core::quotes.partials.text', ['text' => $quote->terms])
                </div>
            </div>
        @endif

        <div class="dq-card">
            <div class="dq-section">
                <h2 class="dq-h2">{{ Translation::get('agreement', 'quote', 'Akkoord') }}</h2>

                @if ($quote->acceptance_text)
                    <div class="dq-text">
                        @include('dashed-ecommerce-core::quotes.partials.text', ['text' => $quote->acceptance_text])
                    </div>
                @endif

                <div style="max-width: 520px">
                    <label class="dq-field">
                        <span>{{ Translation::get('your-name', 'quote', 'Uw naam') }}</span>
                        <input type="text" wire:model="acceptName" class="dq-input" autocomplete="name">
                    </label>
                    @error('acceptName') <p class="dq-error">{{ $message }}</p> @enderror

                    <div class="dq-field">
                        <span>{{ Translation::get('your-signature', 'quote', 'Uw handtekening') }}</span>
                        <div wire:ignore
                             x-data="{
                                 drawing: false,
                                 signed: false,
                                 ctx: null,
                                 init() {
                                     this.size();
                                     window.addEventListener('resize', () => { if (! this.signed) this.size() });
                                 },
                                 size() {
                                     const c = this.$refs.canvas;
                                     const ratio = Math.min(window.devicePixelRatio || 1, 2);
                                     c.width = c.offsetWidth * ratio;
                                     c.height = c.offsetHeight * ratio;
                                     this.ctx = c.getContext('2d');
                                     this.ctx.scale(ratio, ratio);
                                     this.ctx.lineWidth = 2.2;
                                     this.ctx.lineCap = 'round';
                                     this.ctx.lineJoin = 'round';
                                     this.ctx.strokeStyle = '#111827';
                                 },
                                 point(e) {
                                     const r = this.$refs.canvas.getBoundingClientRect();
                                     return { x: e.clientX - r.left, y: e.clientY - r.top };
                                 },
                                 start(e) {
                                     this.drawing = true;
                                     this.$refs.canvas.setPointerCapture(e.pointerId);
                                     const p = this.point(e);
                                     this.ctx.beginPath();
                                     this.ctx.moveTo(p.x, p.y);
                                     this.ctx.lineTo(p.x + 0.1, p.y + 0.1);
                                     this.ctx.stroke();
                                 },
                                 move(e) {
                                     if (! this.drawing) return;
                                     const p = this.point(e);
                                     this.ctx.lineTo(p.x, p.y);
                                     this.ctx.stroke();
                                 },
                                 end() {
                                     if (! this.drawing) return;
                                     this.drawing = false;
                                     this.signed = true;
                                     this.$wire.$set('signature', this.$refs.canvas.toDataURL('image/png'), false);
                                 },
                                 clear() {
                                     const c = this.$refs.canvas;
                                     this.ctx.save();
                                     this.ctx.setTransform(1, 0, 0, 1, 0, 0);
                                     this.ctx.clearRect(0, 0, c.width, c.height);
                                     this.ctx.restore();
                                     this.signed = false;
                                     this.$wire.$set('signature', '', false);
                                 },
                             }"
                             class="dq-pad" :class="signed && 'is-signed'">
                            <div class="dq-pad-base"></div>
                            <p class="dq-pad-hint" x-show="! signed">{{ Translation::get('sign-here', 'quote', 'Teken hier met uw muis of vinger') }}</p>
                            <canvas x-ref="canvas"
                                    @pointerdown.prevent="start($event)"
                                    @pointermove.prevent="move($event)"
                                    @pointerup="end()"
                                    @pointercancel="end()"></canvas>
                            <button type="button" class="dq-pad-clear" x-show="signed" x-cloak @click="clear()">
                                {{ Translation::get('clear-signature', 'quote', 'Opnieuw') }}
                            </button>
                        </div>
                    </div>
                    @error('signature') <p class="dq-error">{{ $message }}</p> @enderror

                    <label class="dq-check">
                        <input type="checkbox" wire:model="acceptAgreed">
                        <span>{{ Translation::get('agree-checkbox', 'quote', 'Ik ga akkoord met deze offerte') }}</span>
                    </label>
                    @error('acceptAgreed') <p class="dq-error">{{ $message }}</p> @enderror

                    <button type="button" wire:click="accept" wire:loading.attr="disabled" wire:target="accept" class="dq-btn">
                        <span wire:loading.remove wire:target="accept">{{ Translation::get('accept', 'quote', 'Akkoord geven') }}</span>
                        <span wire:loading wire:target="accept">{{ Translation::get('accepting', 'quote', 'Bezig...') }}</span>
                    </button>
                </div>

                <div class="dq-reject">
                    <button type="button" wire:click="$toggle('showReject')" class="dq-textbtn">
                        {{ Translation::get('reject', 'quote', 'Offerte afwijzen') }}
                    </button>

                    @if ($showReject)
                        <div style="max-width: 520px">
                            <label class="dq-field">
                                <span>{{ Translation::get('reject-reason', 'quote', 'Waarom wijst u af?') }}</span>
                                <textarea wire:model="rejectReason" rows="3" class="dq-input"></textarea>
                            </label>
                            @error('rejectReason') <p class="dq-error">{{ $message }}</p> @enderror

                            <button type="button" wire:click="reject" class="dq-btn dq-btn-ghost">
                                {{ Translation::get('confirm-reject', 'quote', 'Afwijzen bevestigen') }}
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
