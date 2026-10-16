<?php

namespace App\View\Components\Admin;

use App\Models\CarMaker;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\View as ViewFactory;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\Component;

class MakerRow extends Component
{
    public function __construct(public CarMaker $maker) {}

    public function showUrl(): string
    {
        return route('admin.makers.show', $this->maker);
    }

    public function updateUrl(): string
    {
        return route('admin.makers.update', $this->maker);
    }

    public function activeUrl(): string
    {
        return route('admin.makers.active', $this->maker);
    }

    public function destroyUrl(): string
    {
        return route('admin.makers.destroy', $this->maker);
    }

    public function deletable(): bool
    {
        return $this->maker->models_count === 0 && $this->maker->listings_count === 0;
    }

    public function editDialogId(): string
    {
        return "edit-maker-{$this->maker->id}";
    }

    public function deleteDialogId(): string
    {
        return "delete-maker-{$this->maker->id}";
    }

    public function editBag(): string
    {
        return "maker-{$this->maker->id}";
    }

    public function reopensEdit(): bool
    {
        $errors = ViewFactory::shared('errors');

        return $errors instanceof ViewErrorBag && $errors->getBag($this->editBag())->any();
    }

    public function render(): View
    {
        return view('components.admin.maker-row');
    }
}
