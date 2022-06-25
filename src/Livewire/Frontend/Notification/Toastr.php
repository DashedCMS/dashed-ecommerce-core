<?php

namespace App\Http\Livewire\Notification;

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
        $this->dispatchBrowserEvent(
            'alert',
            [
                'type' => $type,
                'message' => $message
            ]
        );
    }

    public function render()
    {
        return view('livewire.notification.toastr');
    }
}
