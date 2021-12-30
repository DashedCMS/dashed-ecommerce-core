<form wire:submit.prevent="submit">
    <div class="flex space-x-3">
        <img class="h-6 w-6 rounded-full" src="{{ auth()->user()->getFilamentAvatarUrl() }}"
             alt="">
        <div class="flex-1 space-y-1">
            <div class="flex items-center">
                <input wire:model="publicForCustomer" id="public_for_customer"
                       type="checkbox"
                       class="form-checkbox h-4 w-4 text-secondary-600 transition duration-150 ease-in-out">
                <label for="public_for_customer"
                       class="ml-2 block text-sm leading-5 text-gray-900">
                    Moet de klant een notificatie mail krijgen met deze inhoud?
                </label>
            </div>
            @error('publicForCustomer') <span class="error">{{ $message }}</span> @enderror
            <label for="note" class="block text-sm font-medium text-gray-700">
                Maak een notitie:
            </label>
            <textarea wire:model="note"
                      id="note"
                      name="note"
                      class="textarea-input"
                      rows="3"></textarea>
            @error('note') <span class="error">{{ $message }}</span> @enderror
            <button
                class="inline-flex items-center justify-center font-medium tracking-tight rounded-lg focus:outline-none focus:ring-offset-2 focus:ring-2 focus:ring-inset bg-primary-600 hover:bg-primary-500 focus:bg-primary-700 focus:ring-offset-primary-700 h-9 px-4 text-white shadow focus:ring-white w-full mt-2"
                type="submit">
                Notitie aanmaken
            </button>
        </div>
    </div>
</form>
