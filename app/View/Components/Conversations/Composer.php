<?php

namespace App\View\Components\Conversations;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Composer extends Component
{
    public function __construct(public string $action, public bool $async = false) {}

    public function min(): int
    {
        return config('listings.message.min');
    }

    public function max(): int
    {
        return config('listings.message.max');
    }

    public function count(): int
    {
        return mb_strlen((string) old('message', ''));
    }

    public function counter(): string
    {
        return __('app.conversations.composer.counter', ['count' => $this->count(), 'max' => $this->max(), 'min' => $this->min()]);
    }

    public function counterTemplate(): string
    {
        return __('app.conversations.composer.counter', ['max' => $this->max(), 'min' => $this->min()]);
    }

    public function render(): View
    {
        return view('components.conversations.composer');
    }
}
