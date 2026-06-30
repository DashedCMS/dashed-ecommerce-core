<x-master>
    <section class="py-[clamp(40px,6vw,80px)]">
        <x-container>
            <div class="mx-auto max-w-md text-center">
                <div class="mx-auto mb-6 grid size-16 place-items-center rounded-full bg-secondary text-primary-text">
                    <x-lucide-check class="size-8"/>
                </div>
                <h1 class="font-display text-[clamp(26px,3vw,38px)] text-black">{{ __('Deze bestelling is al betaald') }}</h1>
                <p class="mt-4 text-[rgba(48,84,91,0.7)]">{{ __('Bedankt, er is niets meer te doen.') }}</p>
                <a href="{{ url('/') }}" class="button button--primary mt-8 inline-flex">{{ __('Naar de homepage') }}</a>
            </div>
        </x-container>
    </section>
</x-master>
