<ul class="divide-y divide-gray-200">
    @foreach($order->logs as $log)
        <li class="py-4">
            <div class="flex space-x-3">
                <img class="h-6 w-6 rounded-full"
                     src="{{ Helper::getProfilePicture($order->user ? $order->user->email : $order->email) }}"
                     alt="">
                <div class="flex-1 space-y-1">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-medium">{{ $log->user_id ? $log->user->name : ($order->user ? $order->user->name : (\Illuminate\Support\Str::contains('system', $order->tag) ? 'System' : $order->name)) }}</h3>
                        <p class="text-sm text-gray-500">
                            {{ $log->created_at > now()->subHour() ? $log->created_at->diffForHumans() : $log->created_at->format('d-m-Y H:i') }}
                        </p>
                    </div>
                    <p class="text-sm text-gray-500">{{ $log->tag() }}</p>
                    @if($log->images)
                        <div class="grid gap-2">
                            @foreach($log->images as $image)
                                <a class="text-primary-500 hover:text-primary-600"
                                   target="_blank"
                                   href="{{ mediaHelper()->getSingleMedia($image, 'original')->url ?? '' }}" download>
                                    @if(!\Illuminate\Support\Str::contains($image, '.pdf'))
                                        <img class="h-16 w-auto rounded-lg" src="{{ mediaHelper()->getSingleMedia($image, 'original')->url ?? '' }}">
                                    @else
                                        <span>PDF Bestand</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    @endif
                    @if($log->public_for_customer)
                        <p class="text-sm text-gray-500">
                            Notitie is zichtbaar voor de klant
                        </p>
                    @endif
                    @if($log->send_email_to_customer)
                        <p class="text-sm text-gray-500">
                            Klant heeft een mail gehad van deze notitie
                        </p>
                    @endif
                    @if($log->note)
                        <p class="text-sm text-gray-900">{!! nl2br($log->note) !!}</p>
                    @endif
                </div>
            </div>
        </li>
    @endforeach
    <li class="pt-4">
        <livewire:create-order-log :order="$order" />
    </li>
</ul>
