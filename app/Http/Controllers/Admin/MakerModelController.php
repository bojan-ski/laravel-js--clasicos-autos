<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ReferenceDataInUseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SetReferenceActiveRequest;
use App\Http\Requests\Admin\StoreCarModelRequest;
use App\Http\Requests\Admin\UpdateCarModelRequest;
use App\Models\CarMaker;
use App\Models\CarModel;
use App\Services\ReferenceDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class MakerModelController extends Controller
{
    public function store(StoreCarModelRequest $request, CarMaker $maker, ReferenceDataService $referenceData): RedirectResponse
    {
        $model = $referenceData->createModel($maker, $request->name(), $request->yearFrom(), $request->yearTo());

        return redirect()->route('admin.makers.show', $maker)->with('success', __('app.admin.models.flash.created', ['name' => $model->name]));
    }

    public function update(UpdateCarModelRequest $request, CarMaker $maker, CarModel $model, ReferenceDataService $referenceData): RedirectResponse
    {
        $referenceData->updateModel($model, $request->name(), $request->yearFrom(), $request->yearTo());

        return redirect()->route('admin.makers.show', $maker)->with('success', __('app.admin.models.flash.updated', ['name' => $model->name]));
    }

    public function setActive(SetReferenceActiveRequest $request, CarMaker $maker, CarModel $model, ReferenceDataService $referenceData): RedirectResponse
    {
        $referenceData->setModelActive($model, $request->active());

        return redirect()->route('admin.makers.show', $maker)
            ->with('success', __($request->active() ? 'app.admin.models.flash.activated' : 'app.admin.models.flash.deactivated', ['name' => $model->name]));
    }

    public function destroy(CarMaker $maker, CarModel $model, ReferenceDataService $referenceData): RedirectResponse
    {
        Gate::authorize('delete', $model);

        try {
            $referenceData->deleteModel($model);
        } catch (ReferenceDataInUseException) {
            return redirect()->route('admin.makers.show', $maker)->with('error', __('app.admin.models.flash.in_use', ['name' => $model->name]));
        }

        return redirect()->route('admin.makers.show', $maker)->with('success', __('app.admin.models.flash.deleted', ['name' => $model->name]));
    }
}
