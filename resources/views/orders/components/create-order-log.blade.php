<form wire:submit.prevent="submit">
    <div class="flex space-x-3">
        <img class="h-6 w-6 rounded-full" src="{{ auth()->user()->getFilamentAvatarUrl() }}"
             alt="">
        <div class="flex flex-col w-full gap-4">
            {{ $this->form }}

            <button
                class="inline-flex items-center justify-center font-medium tracking-tight rounded-lg focus:outline-none focus:ring-offset-2 focus:ring-2 focus:ring-inset bg-primary-600 hover:bg-primary-500 focus:bg-primary-700 focus:ring-offset-primary-700 h-9 px-4 text-white shadow focus:ring-white w-full"
                type="submit">
                Notitie aanmaken
            </button>
        </div>
    </div>
</form>
