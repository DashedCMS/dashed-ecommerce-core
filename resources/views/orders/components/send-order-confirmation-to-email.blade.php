<form wire:submit.prevent="submit">
    @if($order->downloadInvoiceUrl())
        <label for="fulfillment_status" class="block text-sm font-medium text-gray-700">
            Bestelbevestiging versturen naar:
        </label>
        <input wire:model="email"
               id="email"
               name="email"
               type="email"
               class="form-input"
               required>
        @error('email') <span class="error">{{ $message }}</span> @enderror
        <button type="submit"
                class="inline-flex items-center justify-center font-medium tracking-tight rounded-lg focus:outline-none focus:ring-offset-2 focus:ring-2 focus:ring-inset bg-primary-600 hover:bg-primary-500 focus:bg-primary-700 focus:ring-offset-primary-700 h-9 px-4 text-white shadow focus:ring-white w-full mt-2">
            Stuur bevestiging
        </button>
    @endif
</form>
