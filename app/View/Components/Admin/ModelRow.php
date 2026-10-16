<?php

namespace App\View\Components\Admin;

use App\Models\CarMaker;
use App\Models\CarModel;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\View as ViewFactory;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\Component;

class ModelRow extends Component
{
    public function __construct(public CarMaker $maker, public CarModel $model) {}

    public function updateUrl(): string
    {
        return route('admin.makers.models.update', [$this->maker, $this->model]);
    }

    public function activeUrl(): string
    {
        return route('admin.makers.models.active', [$this->maker, $this->model]);
    }

    public function destroyUrl(): string
    {
        return route('admin.makers.models.destroy', [$this->maker, $this->model]);
    }

    public function deletable(): bool
    {
        return $this->model->listings_count === 0;
    }

    public function years(): string
    {
        return $this->model->productionYears();
    }

    public function renameHint(): ?string
    {
        if ($this->model->listings_count === 0) {
            return null;
        }

        return trans_choice('app.admin.models.edit.rename_hint', $this->model->listings_count, ['count' => $this->model->listings_count]);
    }

    public function editDialogId(): string
    {
        return "edit-model-{$this->model->id}";
    }

    public function deleteDialogId(): string
    {
        return "delete-model-{$this->model->id}";
    }

    public function editBag(): string
    {
        return "model-{$this->model->id}";
    }

    public function reopensEdit(): bool
    {
        $errors = ViewFactory::shared('errors');

        return $errors instanceof ViewErrorBag && $errors->getBag($this->editBag())->any();
    }

    public function render(): View
    {
        return view('components.admin.model-row');
    }
}
