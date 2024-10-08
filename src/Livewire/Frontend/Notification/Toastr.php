<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Notification;

use Livewire\Component;

class Toastr extends Component
{
    public $successMessage;
    public $errorMessage;

    protected $listeners = [
        'showAlert',
    ];

    public function showAlert(string $type, string $message)
    {
        $this->dispatch(
            'alert',
            [
                'type' => $type,
                'message' => $message,
            ]
        );
    }

    public function render()
    {
        return view(env('SITE_THEME', 'dashed') . '.notification.toastr');
    }
}
