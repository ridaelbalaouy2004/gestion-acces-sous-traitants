<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CompanyDocument;
use App\Models\CompanyEmployee;
use App\Models\CompanyEmployeeDocument;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HseRequiredDocumentsSeeder extends Seeder
{
    public function run(): void
    {
        Company::query()->chunkById(100, function (EloquentCollection $companies): void {
            foreach ($companies as $company) {
                DB::transaction(function () use ($company): void {
                    foreach (CompanyDocument::allTypes() as $type) {
                        $document = $company->documents()->firstOrCreate(
                            ['type' => $type],
                            [
                                'label' => CompanyDocument::labelForType($type),
                                'status' => CompanyDocument::STATUS_DRAFT,
                            ],
                        );

                        if (
                            $type === CompanyDocument::TYPE_LISTE_PERSONNEL
                            && $document->label !== CompanyDocument::labelForType($type)
                        ) {
                            $document->update(['label' => CompanyDocument::labelForType($type)]);
                        }
                    }
                });
            }
        });

        CompanyEmployee::query()->chunkById(100, function (EloquentCollection $employees): void {
            foreach ($employees as $employee) {
                DB::transaction(function () use ($employee): void {
                    foreach (CompanyEmployeeDocument::allTypes() as $type) {
                        $employee->documents()->firstOrCreate(
                            ['type' => $type],
                            [
                                'label' => CompanyEmployeeDocument::labelForType($type),
                                'status' => CompanyEmployeeDocument::STATUS_DRAFT,
                            ],
                        );
                    }
                });
            }
        });
    }
}
