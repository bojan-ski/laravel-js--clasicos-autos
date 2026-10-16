<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ReferenceDataInUseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SetReferenceActiveRequest;
use App\Http\Requests\Admin\StoreCarMakerRequest;
use App\Http\Requests\Admin\UpdateCarMakerRequest;
use App\Models\CarMaker;
use App\Services\ReferenceDataService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class MakerController extends Controller
{
    public function index(ReferenceDataService $referenceData): View
    {
        Gate::authorize('viewAny', CarMaker::class);

        return view('pages.admin.makers.index', ['makers' => $referenceData->makers()]);
    }

    public function show(CarMaker $maker, ReferenceDataService $referenceData): View
    {
        Gate::authorize('view', $maker);

        return view('pages.admin.makers.show', ['maker' => $maker, 'models' => $referenceData->models($maker)]);
    }

    public function store(StoreCarMakerRequest $request, ReferenceDataService $referenceData): RedirectResponse
    {
        $maker = $referenceData->createMaker($request->name());

        return redirect()->route('admin.makers')->with('success', __('app.admin.makers.flash.created', ['name' => $maker->name]));
    }

    public function update(UpdateCarMakerRequest $request, CarMaker $maker, ReferenceDataService $referenceData): RedirectResponse
    {
        $referenceData->renameMaker($maker, $request->name());

        return redirect()->route('admin.makers')->with('success', __('app.admin.makers.flash.updated', ['name' => $maker->name]));
    }

    public function setActive(SetReferenceActiveRequest $request, CarMaker $maker, ReferenceDataService $referenceData): RedirectResponse
    {
        $referenceData->setMakerActive($maker, $request->active());

        return redirect()->route('admin.makers')
            ->with('success', __($request->active() ? 'app.admin.makers.flash.activated' : 'app.admin.makers.flash.deactivated', ['name' => $maker->name]));
    }

    public function destroy(CarMaker $maker, ReferenceDataService $referenceData): RedirectResponse
    {
        Gate::authorize('delete', $maker);

        try {
            $referenceData->deleteMaker($maker);
        } catch (ReferenceDataInUseException) {
            return redirect()->route('admin.makers')->with('error', __('app.admin.makers.flash.in_use', ['name' => $maker->name]));
        }

        return redirect()->route('admin.makers')->with('success', __('app.admin.makers.flash.deleted', ['name' => $maker->name]));
    }
}
