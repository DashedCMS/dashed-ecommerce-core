<form>
    <label for="retour_status" class="block text-sm font-medium text-gray-700">Verander
        retour status</label>
    <select id="retour_status" name="retour_status"
            wire:change="update"
            wire:model="retourStatus"
            class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
        @foreach(Orders::getReturnStatusses() as $key => $status)
            <option value="{{ $key }}">{{ $status }}</option>
        @endforeach
    </select>
</form>
