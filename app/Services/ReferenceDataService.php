<?php

namespace App\Services;

use App\Exceptions\ReferenceDataInUseException;
use App\Models\CarListing;
use App\Models\CarMaker;
use App\Models\CarModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ReferenceDataService
{
    /**
     * @return Collection<int, CarMaker>
     */
    public function makers(): Collection
    {
        return CarMaker::query()->ordered()->withCount(['models', 'listings'])->get();
    }

    /**
     * @return Collection<int, CarModel>
     */
    public function models(CarMaker $maker): Collection
    {
        return $maker->models()->ordered()->withCount('listings')->get();
    }

    public function createMaker(string $name): CarMaker
    {
        $maker = new CarMaker(['name' => $name]);
        $maker->is_active = true;
        $maker->save();

        return $maker;
    }

    public function renameMaker(CarMaker $maker, string $name): void
    {
        $maker->name = $name;
        $maker->save();
    }

    public function setMakerActive(CarMaker $maker, bool $active): void
    {
        $maker->is_active = $active;
        $maker->save();
    }

    public function deleteMaker(CarMaker $maker): void
    {
        DB::transaction(function () use ($maker): void {
            $locked = CarMaker::query()->lockForUpdate()->findOrFail($maker->getKey());

            if (! $locked->isDeletable()) {
                throw new ReferenceDataInUseException("Maker {$locked->getKey()} still has models or listings.");
            }

            $locked->delete();
        });
    }

    public function createModel(CarMaker $maker, string $name, int $yearFrom, int $yearTo): CarModel
    {
        return DB::transaction(function () use ($maker, $name, $yearFrom, $yearTo): CarModel {
            $lockedMaker = CarMaker::query()->lockForUpdate()->findOrFail($maker->getKey());

            $model = $lockedMaker->models()->make(['name' => $name, 'year_from' => $yearFrom, 'year_to' => $yearTo]);
            $model->is_active = true;
            $model->save();

            return $model;
        }, attempts: 3);
    }

    public function updateModel(CarModel $model, string $name, int $yearFrom, int $yearTo): void
    {
        $updated = DB::transaction(function () use ($model, $name, $yearFrom, $yearTo): CarModel {
            CarMaker::query()->lockForUpdate()->findOrFail($model->car_maker_id);
            $locked = CarModel::query()->lockForUpdate()->findOrFail($model->getKey());
            $renamed = $locked->name !== $name;

            $locked->fill(['name' => $name, 'year_from' => $yearFrom, 'year_to' => $yearTo]);
            $locked->save();

            if ($renamed) {
                $linked = CarListing::query()->where('car_model_id', $locked->getKey())->pluck('id');

                CarListing::query()->whereKey($linked)->where('car_model_id', $locked->getKey())->toBase()->update(['model' => $name]);
            }

            return $locked;
        }, attempts: 3);

        $model->setRawAttributes($updated->getAttributes(), sync: true);
    }

    public function setModelActive(CarModel $model, bool $active): void
    {
        $model->is_active = $active;
        $model->save();
    }

    public function deleteModel(CarModel $model): void
    {
        DB::transaction(function () use ($model): void {
            CarMaker::query()->lockForUpdate()->findOrFail($model->car_maker_id);
            $locked = CarModel::query()->lockForUpdate()->findOrFail($model->getKey());

            if (! $locked->isDeletable()) {
                throw new ReferenceDataInUseException("Model {$locked->getKey()} still has listings.");
            }

            $locked->delete();
        }, attempts: 3);
    }
}
