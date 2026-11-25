<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanyDocument;
use App\Models\CompanyEmployee;
use App\Models\CompanyEmployeeDocument;
use App\Models\CompanyMachinery;
use App\Models\CompanyMachineryDocument;
use App\Models\CompanyTooling;
use App\Models\CompanyToolingDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as SpreadsheetReaderException;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Shared\Date as SpreadsheetDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class CompanyListImportService
{
    private const MAX_DATA_ROWS = 5000;

    private const MAX_COLUMNS = 50;

    private const HEADER_SCAN_ROWS = 10;

    /**
     * @return array<string, array{label: string, required: bool, aliases: array<int, string>}>
     */
    private function fieldDefinitions(string $type): array
    {
        return match ($type) {
            CompanyDocument::TYPE_LISTE_PERSONNEL => [
                'last_name' => [
                    'label' => 'Nom',
                    'required' => true,
                    'aliases' => ['nom', 'last name', 'lastname', 'surname'],
                ],
                'first_name' => [
                    'label' => 'Prénom',
                    'required' => true,
                    'aliases' => ['prenom', 'first name', 'firstname', 'given name'],
                ],
                'cin' => [
                    'label' => 'CIN',
                    'required' => true,
                    'aliases' => ['cin', 'n cin', 'numero cin', 'numero de cin', 'carte nationale identite'],
                ],
                'position' => [
                    'label' => 'Fonction / poste',
                    'required' => true,
                    'aliases' => ['fonction', 'poste', 'fonction poste', 'position', 'job title', 'job'],
                ],
                'locker_number' => [
                    'label' => 'Numéro de cadenas',
                    'required' => false,
                    'aliases' => ['numero cadenas', 'numero de cadenas', 'n cadenas', 'cadenas', 'lock number'],
                ],
            ],
            CompanyDocument::TYPE_LISTE_MATERIEL => [
                'designation' => [
                    'label' => 'Désignation',
                    'required' => true,
                    'aliases' => ['designation', 'nom', 'materiel', 'equipement', 'engin', 'machine'],
                ],
                'category' => [
                    'label' => 'Catégorie',
                    'required' => true,
                    'aliases' => ['categorie', 'category'],
                ],
                'assignment' => [
                    'label' => 'Affectation',
                    'required' => false,
                    'aliases' => ['affectation', 'chantier', 'destination', 'assignment'],
                ],
                'brand' => [
                    'label' => 'Marque',
                    'required' => false,
                    'aliases' => ['marque', 'brand'],
                ],
                'model' => [
                    'label' => 'Modèle',
                    'required' => false,
                    'aliases' => ['modele', 'model'],
                ],
                'serial_number' => [
                    'label' => 'Numéro de série',
                    'required' => false,
                    'aliases' => ['numero serie', 'numero de serie', 'n serie', 'n de serie', 'serial number', 'serial no', 'serial'],
                ],
                'registration_number' => [
                    'label' => 'Numéro d’immatriculation',
                    'required' => false,
                    'aliases' => ['immatriculation', 'numero immatriculation', 'n immatriculation', 'registration number'],
                ],
                'commissioned_at' => [
                    'label' => 'Date de mise en service',
                    'required' => false,
                    'aliases' => ['date mise en service', 'date de mise en service', 'mise en service', 'commissioned at'],
                ],
                'description' => [
                    'label' => 'Description',
                    'required' => false,
                    'aliases' => ['description', 'details'],
                ],
            ],
            CompanyDocument::TYPE_LISTE_OUTILLAGE => [
                'designation' => [
                    'label' => 'Désignation',
                    'required' => true,
                    'aliases' => ['designation', 'nom', 'outillage', 'outil', 'materiel', 'equipement'],
                ],
                'category' => [
                    'label' => 'Catégorie',
                    'required' => true,
                    'aliases' => ['categorie', 'category'],
                ],
                'internal_reference' => [
                    'label' => 'Référence',
                    'required' => false,
                    'aliases' => ['reference', 'ref', 'reference interne', 'code interne', 'code article'],
                ],
                'brand' => [
                    'label' => 'Marque',
                    'required' => false,
                    'aliases' => ['marque', 'brand'],
                ],
                'model' => [
                    'label' => 'Modèle',
                    'required' => false,
                    'aliases' => ['modele', 'model'],
                ],
                'serial_number' => [
                    'label' => 'Numéro de série',
                    'required' => false,
                    'aliases' => ['numero serie', 'numero de serie', 'n serie', 'n de serie', 'serial number', 'serial no', 'serial'],
                ],
                'quantity' => [
                    'label' => 'Quantité',
                    'required' => true,
                    'aliases' => ['quantite', 'qte', 'quantity', 'qty', 'nombre'],
                ],
                'physical_condition' => [
                    'label' => 'État physique',
                    'required' => false,
                    'aliases' => ['etat', 'etat physique', 'condition', 'physical condition'],
                ],
                'acquired_at' => [
                    'label' => 'Date d’acquisition',
                    'required' => false,
                    'aliases' => ['date acquisition', 'date d acquisition', 'acquired at'],
                ],
                'location' => [
                    'label' => 'Emplacement',
                    'required' => false,
                    'aliases' => ['emplacement', 'localisation', 'lieu', 'location'],
                ],
                'description' => [
                    'label' => 'Description',
                    'required' => false,
                    'aliases' => ['description', 'details'],
                ],
                'observations' => [
                    'label' => 'Observations',
                    'required' => false,
                    'aliases' => ['observation', 'observations', 'remarques', 'notes'],
                ],
            ],
            default => [],
        };
    }

    public function supportsType(string $type): bool
    {
        return $this->fieldDefinitions($type) !== [];
    }

    public function isSpreadsheetUpload(string $type, UploadedFile $file): bool
    {
        return $this->supportsType($type)
            && in_array(mb_strtolower((string) $file->guessExtension()), ['xls', 'xlsx'], true);
    }

    public function isSpreadsheetDocument(CompanyDocument $document): bool
    {
        return $this->supportsType($document->type)
            && in_array(mb_strtolower(pathinfo((string) $document->file_path, PATHINFO_EXTENSION)), ['xls', 'xlsx'], true);
    }

    public function hasImportedData(Company $company, CompanyDocument $document): bool
    {
        return $this->isEligibleDocument($company, $document)
            && Storage::disk('documents_private')->exists($document->file_path)
            && $this->importedRecordsQuery($company, $document)->exists();
    }

    /**
     * @return array{title: string, columns: array<string, string>, rows: array<int, array<string, mixed>>}|null
     */
    public function importedData(Company $company, CompanyDocument $document): ?array
    {
        if (! $this->isEligibleDocument($company, $document)
            || ! Storage::disk('documents_private')->exists($document->file_path)) {
            return null;
        }

        $columns = match ($document->type) {
            CompanyDocument::TYPE_LISTE_PERSONNEL => [
                'last_name' => 'Nom',
                'first_name' => 'Prénom',
                'cin' => 'CIN',
                'position' => 'Fonction / poste',
                'locker_number' => 'Numéro de cadenas',
            ],
            CompanyDocument::TYPE_LISTE_MATERIEL => [
                'designation' => 'Désignation',
                'category' => 'Catégorie',
                'assignment' => 'Affectation',
                'brand' => 'Marque',
                'model' => 'Modèle',
                'serial_number' => 'Numéro de série',
                'registration_number' => 'Numéro d’immatriculation',
                'commissioned_at' => 'Date de mise en service',
                'description' => 'Description',
            ],
            CompanyDocument::TYPE_LISTE_OUTILLAGE => [
                'designation' => 'Désignation',
                'category' => 'Catégorie',
                'internal_reference' => 'Référence',
                'brand' => 'Marque',
                'model' => 'Modèle',
                'serial_number' => 'Numéro de série',
                'quantity' => 'Quantité',
                'physical_condition' => 'État physique',
                'acquired_at' => 'Date d’acquisition',
                'location' => 'Emplacement',
                'description' => 'Description',
                'observations' => 'Observations',
            ],
        };
        $records = $this->importedRecordsQuery($company, $document)->get(array_keys($columns));

        if ($records->isEmpty()) {
            return null;
        }

        $rows = $records->map(function (CompanyEmployee|CompanyMachinery|CompanyTooling $record) use ($columns): array {
            $row = [];

            foreach ($columns as $field => $label) {
                $value = $record->getAttribute($field);
                $row[$field] = $value instanceof \DateTimeInterface
                    ? $value->format('d/m/Y')
                    : ($value === null ? '' : (string) $value);
            }

            return $row;
        })->all();

        return [
            'title' => $document->displayLabel(),
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $mappingOverride
     * @return array<string, mixed>
     */
    public function preview(Company $company, CompanyDocument $document, ?array $mappingOverride = null): array
    {
        abort_unless(
            (int) $document->company_id === (int) $company->getKey()
                && $this->isSpreadsheetDocument($document)
                && filled($document->file_path),
            404,
        );

        $disk = Storage::disk('documents_private');

        if (! $disk->exists($document->file_path)) {
            return $this->emptyPreview($document, 'Le fichier Excel n’est plus disponible dans le stockage privé.');
        }

        $path = $disk->path($document->file_path);
        $sourceHash = hash_file('sha256', $path);

        if (! is_string($sourceHash)) {
            throw new RuntimeException('Impossible de vérifier le fichier Excel stocké.');
        }

        $spreadsheet = null;

        try {
            $readerType = IOFactory::identify($path);

            if (! in_array($readerType, ['Xls', 'Xlsx'], true)) {
                return $this->emptyPreview($document, 'Le fichier Excel est illisible ou endommagé.', $sourceHash);
            }

            $expectedReaderType = mb_strtolower(pathinfo($document->file_path, PATHINFO_EXTENSION)) === 'xls'
                ? 'Xls'
                : 'Xlsx';

            if ($readerType !== $expectedReaderType) {
                return $this->emptyPreview($document, 'Le contenu du fichier ne correspond pas à son format Excel déclaré.', $sourceHash);
            }

            $reader = IOFactory::createReader($readerType);
            $reader->setReadDataOnly(false);
            $spreadsheet = $reader->load($path);
            $worksheet = $spreadsheet->getActiveSheet();
            $highestDataRow = $worksheet->getHighestDataRow();
            $highestDataColumn = $worksheet->getHighestDataColumn();
            $highestColumnIndex = Coordinate::columnIndexFromString($highestDataColumn);

            if ($highestColumnIndex > self::MAX_COLUMNS) {
                return $this->emptyPreview(
                    $document,
                    'Le fichier contient plus de '.self::MAX_COLUMNS.' colonnes. Réduisez-le avant de recommencer.',
                    $sourceHash,
                );
            }

            if ($highestDataRow < 1) {
                return $this->emptyPreview($document, 'Aucune ligne de données n’a été détectée.', $sourceHash);
            }

            $columnKeys = [];

            for ($columnIndex = 1; $columnIndex <= $highestColumnIndex; $columnIndex++) {
                $columnKeys[] = Coordinate::stringFromColumnIndex($columnIndex);
            }

            $definitions = $this->fieldDefinitions($document->type);
            [$headerRow, $headers, $autoMapping, $ambiguousColumns] = $this->detectHeaders(
                $worksheet,
                $columnKeys,
                $definitions,
                $highestDataRow,
            );
            $mapping = $mappingOverride === null
                ? $autoMapping
                : $this->validatedMapping($mappingOverride, $definitions, $headers);
            $mappingErrors = $this->mappingErrors($mapping, $definitions);
            $mappedColumns = array_filter($mapping, fn (?string $column): bool => $column !== null);
            $unrecognizedColumns = array_diff(array_keys($headers), array_values($mappedColumns));
            $rawRows = [];
            $rowCount = 0;

            for ($rowIndex = $headerRow + 1; $rowIndex <= $highestDataRow; $rowIndex++) {
                $rawValues = [];

                foreach ($columnKeys as $column) {
                    $rawValues[$column] = $this->displayCellValue($worksheet, $column, $rowIndex);
                }

                if (collect($rawValues)->every(fn (mixed $value): bool => blank($value))) {
                    continue;
                }

                $rowCount++;

                if ($rowCount > self::MAX_DATA_ROWS) {
                    return $this->emptyPreview(
                        $document,
                        'Le fichier dépasse la limite de '.self::MAX_DATA_ROWS.' lignes de données.',
                        $sourceHash,
                    );
                }

                $values = array_fill_keys(array_keys($definitions), null);
                $formulaErrors = [];

                foreach ($mapping as $field => $column) {
                    if ($column === null) {
                        continue;
                    }

                    $cell = $worksheet->getCell($column.$rowIndex);

                    if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                        $formulaErrors[] = 'La colonne « '.$definitions[$field]['label'].' » contient une formule; remplacez-la par une valeur.';

                        continue;
                    }

                    $values[$field] = $this->mappedCellValue($cell, $field);
                }

                $errors = [...$formulaErrors, ...$this->validateRow($document->type, $values)];

                foreach ($mappingErrors as $mappingError) {
                    $errors[] = $mappingError;
                }

                $rawRows[] = [
                    'excel_row' => $rowIndex,
                    'raw' => $rawValues,
                    'values' => $values,
                    'errors' => array_values(array_unique($errors)),
                    'status' => $errors === [] ? 'valid' : 'invalid',
                    'duplicate_reason' => null,
                ];
            }

            if ($rowCount === 0) {
                return $this->emptyPreview($document, 'Aucune ligne de données n’a été détectée.', $sourceHash);
            }

            $duplicateRows = $this->markDuplicates($company, $document->type, $rawRows);
            $invalidRows = count(array_filter($rawRows, fn (array $row): bool => $row['status'] === 'invalid'));
            $validRows = count(array_filter($rawRows, fn (array $row): bool => $row['status'] === 'valid'));

            return [
                'file_name' => $document->file_name,
                'type' => $document->type,
                'destination' => $this->destinationLabel($document->type),
                'sheet_name' => $worksheet->getTitle(),
                'header_row' => $headerRow,
                'headers' => $headers,
                'definitions' => $definitions,
                'mappings' => $mapping,
                'mapping_errors' => $mappingErrors,
                'ambiguous_columns' => $ambiguousColumns,
                'unrecognized_columns' => array_values($unrecognizedColumns),
                'rows' => $rawRows,
                'total_rows' => $rowCount,
                'valid_rows' => $validRows,
                'invalid_rows' => $invalidRows,
                'duplicate_rows' => $duplicateRows,
                'source_hash' => $sourceHash,
                'fatal_error' => null,
            ];
        } catch (SpreadsheetReaderException|SpreadsheetException $exception) {
            return $this->emptyPreview($document, 'Le fichier Excel est illisible ou endommagé.', $sourceHash);
        } finally {
            $spreadsheet?->disconnectWorksheets();
        }
    }

    /**
     * @param  array<string, mixed>  $mapping
     * @param  array<int, int|string>  $selectedRows
     * @return array<string, mixed>
     */
    public function import(
        Company $company,
        CompanyDocument $document,
        User $actor,
        array $mapping,
        array $selectedRows,
        string $sourceHash,
    ): array {
        $preview = $this->preview($company, $document, $mapping);

        if ($preview['fatal_error'] !== null) {
            throw ValidationException::withMessages(['import' => $preview['fatal_error']]);
        }

        if ($preview['mapping_errors'] !== []) {
            throw ValidationException::withMessages(['mapping' => $preview['mapping_errors'][0]]);
        }

        if (! hash_equals($preview['source_hash'], $sourceHash)) {
            throw ValidationException::withMessages([
                'source_hash' => 'Le fichier a changé depuis l’aperçu. Vérifiez à nouveau les lignes avant de confirmer.',
            ]);
        }

        $selectedRowNumbers = array_values(array_unique(array_map('intval', $selectedRows)));
        $rowsByNumber = collect($preview['rows'])->keyBy('excel_row');

        foreach ($selectedRowNumbers as $rowNumber) {
            $row = $rowsByNumber->get($rowNumber);

            if ($row === null || $row['errors'] !== []) {
                throw ValidationException::withMessages([
                    'selected_rows' => 'La sélection contient une ligne invalide ou absente de l’aperçu.',
                ]);
            }
        }

        if ($selectedRowNumbers === []) {
            throw ValidationException::withMessages([
                'selected_rows' => 'Sélectionnez au moins une ligne valide à importer.',
            ]);
        }

        $summary = DB::transaction(function () use (
            $company,
            $document,
            $actor,
            $preview,
            $rowsByNumber,
            $selectedRowNumbers,
        ): array {
            $lockedCompany = Company::query()
                ->whereKey($company->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $selectedRows = array_map(
                fn (int $rowNumber): array => $rowsByNumber->get($rowNumber),
                $selectedRowNumbers,
            );
            $existingKeys = $this->existingIdentityKeys($lockedCompany, $document->type, $preview['rows']);
            $seenKeys = [];
            $createdCount = 0;
            $duplicateCount = $preview['duplicate_rows'];
            $duplicateRows = array_values(array_filter(
                $preview['rows'],
                fn (array $row): bool => $row['status'] === 'duplicate',
            ));

            foreach ($selectedRows as $row) {
                $identityKeys = $this->identityKeys($document->type, $row['values']);
                $isDuplicate = collect($identityKeys)->contains(
                    fn (string $key): bool => isset($existingKeys[$key]) || isset($seenKeys[$key]),
                );

                if ($isDuplicate) {
                    if ($row['status'] !== 'duplicate') {
                        $duplicateCount++;
                        $duplicateRows[] = [
                            'excel_row' => $row['excel_row'],
                            'duplicate_reason' => 'Un enregistrement portant la même référence a été créé depuis l’aperçu.',
                        ];
                    }

                    continue;
                }

                if ($row['status'] === 'duplicate') {
                    continue;
                }

                foreach ($identityKeys as $identityKey) {
                    $seenKeys[$identityKey] = true;
                }

                $this->createImportedRecord($lockedCompany, $document, $actor, $row);
                $createdCount++;
            }

            return [
                'source_file' => $document->file_name,
                'destination' => $this->destinationLabel($document->type),
                'created_count' => $createdCount,
                'created_label' => $this->createdLabel($document->type, $createdCount),
                'duplicate_count' => $duplicateCount,
                'rejected_count' => $preview['invalid_rows'],
                'duplicate_rows' => $duplicateRows,
                'rejected_rows' => array_values(array_filter(
                    $preview['rows'],
                    fn (array $row): bool => $row['status'] === 'invalid',
                )),
                'selected_count' => count($selectedRowNumbers),
            ];
        });

        return $summary;
    }

    /**
     * @param  array<int, string>  $columnKeys
     * @param  array<string, array{label: string, required: bool, aliases: array<int, string>}>  $definitions
     * @return array{int, array<string, string>, array<string, string|null>, array<string, string>}
     */
    private function detectHeaders(
        Worksheet $worksheet,
        array $columnKeys,
        array $definitions,
        int $highestDataRow,
    ): array {
        $bestRow = 1;
        $bestScore = -1;
        $bestHeaders = [];
        $bestMapping = [];
        $bestAmbiguous = [];

        for ($row = 1; $row <= min(self::HEADER_SCAN_ROWS, $highestDataRow); $row++) {
            $headers = [];

            foreach ($columnKeys as $column) {
                $headers[$column] = $this->displayCellValue($worksheet, $column, $row);
            }

            if (collect($headers)->every(fn (mixed $header): bool => blank($header))) {
                continue;
            }

            [$mapping, $ambiguous] = $this->automaticMapping($headers, $definitions);
            $score = count(array_filter($mapping, fn (?string $column): bool => $column !== null));

            if ($score > $bestScore) {
                $bestRow = $row;
                $bestScore = $score;
                $bestHeaders = array_map(
                    fn (string $column, mixed $header): string => filled($header) ? (string) $header : 'Colonne '.$column,
                    array_keys($headers),
                    array_values($headers),
                );
                $bestHeaders = array_combine(array_keys($headers), $bestHeaders) ?: [];
                $bestMapping = $mapping;
                $bestAmbiguous = $ambiguous;
            }
        }

        if ($bestScore < 0) {
            $bestMapping = array_fill_keys(array_keys($definitions), null);
        }

        return [$bestRow, $bestHeaders, $bestMapping, $bestAmbiguous];
    }

    /**
     * @param  array<string, mixed>  $headers
     * @param  array<string, array{label: string, required: bool, aliases: array<int, string>}>  $definitions
     * @return array{array<string, string|null>, array<string, string>}
     */
    private function automaticMapping(array $headers, array $definitions): array
    {
        $columnsByField = [];
        $ambiguous = [];

        foreach ($headers as $column => $header) {
            $normalizedHeader = $this->normalizeHeader((string) $header);
            $matches = [];

            foreach ($definitions as $field => $definition) {
                foreach ($definition['aliases'] as $alias) {
                    if ($normalizedHeader === $this->normalizeHeader($alias)) {
                        $matches[] = $field;

                        break;
                    }
                }
            }

            $matches = array_values(array_unique($matches));

            if (count($matches) === 1) {
                $columnsByField[$matches[0]][] = $column;
            } elseif (count($matches) > 1) {
                $ambiguous[$column] = 'Cet en-tête correspond à plusieurs champs.';
            }
        }

        $mapping = array_fill_keys(array_keys($definitions), null);

        foreach ($columnsByField as $field => $columns) {
            if (count($columns) === 1) {
                $mapping[$field] = $columns[0];
            } else {
                foreach ($columns as $column) {
                    $ambiguous[$column] = 'Plusieurs colonnes correspondent à ce champ.';
                }
            }
        }

        return [$mapping, $ambiguous];
    }

    /**
     * @param  array<string, mixed>  $mapping
     * @param  array<string, array{label: string, required: bool, aliases: array<int, string>}>  $definitions
     * @param  array<string, string>  $headers
     * @return array<string, string|null>
     */
    private function validatedMapping(array $mapping, array $definitions, array $headers): array
    {
        $unexpectedFields = array_diff(array_keys($mapping), array_keys($definitions));

        if ($unexpectedFields !== []) {
            throw ValidationException::withMessages([
                'mapping' => 'La correspondance contient un champ non reconnu.',
            ]);
        }

        $validated = array_fill_keys(array_keys($definitions), null);

        foreach ($definitions as $field => $definition) {
            $column = $mapping[$field] ?? null;

            if ($column === null || $column === '') {
                continue;
            }

            if (! is_string($column) || ! array_key_exists(Str::upper($column), $headers)) {
                throw ValidationException::withMessages([
                    'mapping.'.$field => 'La colonne sélectionnée n’existe pas dans le fichier.',
                ]);
            }

            $validated[$field] = Str::upper($column);
        }

        return $validated;
    }

    /**
     * @param  array<string, array{label: string, required: bool, aliases: array<int, string>}>  $definitions
     * @return array<int, string>
     */
    private function mappingErrors(array $mapping, array $definitions): array
    {
        $errors = [];
        $selectedColumns = array_filter($mapping, fn (?string $column): bool => $column !== null);
        $columnsByField = array_count_values($selectedColumns);

        foreach ($columnsByField as $column => $count) {
            if ($count > 1) {
                $errors[] = 'La colonne '.$column.' est affectée à plusieurs champs.';
            }
        }

        foreach ($definitions as $field => $definition) {
            if ($definition['required'] && ($mapping[$field] ?? null) === null) {
                $errors[] = 'Associez une colonne au champ obligatoire « '.$definition['label'].' ».';
            }
        }

        return array_values(array_unique($errors));
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<int, string>
     */
    private function validateRow(string $type, array &$values): array
    {
        if (array_key_exists('cin', $values) && is_string($values['cin'])) {
            $values['cin'] = Str::upper(trim($values['cin']));
        }

        if (array_key_exists('quantity', $values) && is_numeric($values['quantity'])) {
            $quantity = (float) $values['quantity'];

            if (floor($quantity) === $quantity) {
                $values['quantity'] = (string) (int) $quantity;
            }
        }

        $rules = match ($type) {
            CompanyDocument::TYPE_LISTE_PERSONNEL => [
                'last_name' => ['required', 'string', 'max:100'],
                'first_name' => ['required', 'string', 'max:100'],
                'cin' => ['required', 'string', 'max:50'],
                'position' => ['required', 'string', 'max:255'],
                'locker_number' => ['nullable', 'string', 'max:50'],
            ],
            CompanyDocument::TYPE_LISTE_MATERIEL => [
                'designation' => ['required', 'string', 'max:150'],
                'category' => ['required', 'string', 'max:100'],
                'assignment' => ['nullable', 'string', 'max:255'],
                'brand' => ['nullable', 'string', 'max:100'],
                'model' => ['nullable', 'string', 'max:100'],
                'serial_number' => ['nullable', 'string', 'max:100'],
                'registration_number' => ['nullable', 'string', 'max:100'],
                'commissioned_at' => ['nullable', 'date'],
                'description' => ['nullable', 'string', 'max:5000'],
            ],
            CompanyDocument::TYPE_LISTE_OUTILLAGE => [
                'designation' => ['required', 'string', 'max:150'],
                'category' => ['required', 'string', 'max:100'],
                'internal_reference' => ['nullable', 'string', 'max:100'],
                'brand' => ['nullable', 'string', 'max:100'],
                'model' => ['nullable', 'string', 'max:100'],
                'serial_number' => ['nullable', 'string', 'max:100'],
                'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
                'physical_condition' => ['nullable', 'in:new,good,average,damaged'],
                'acquired_at' => ['nullable', 'date'],
                'location' => ['nullable', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:5000'],
                'observations' => ['nullable', 'string', 'max:5000'],
            ],
            default => [],
        };
        $messages = [
            'last_name.required' => 'Le nom est obligatoire.',
            'first_name.required' => 'Le prénom est obligatoire.',
            'cin.required' => 'CIN obligatoire.',
            'position.required' => 'La fonction / le poste est obligatoire.',
            'designation.required' => 'La désignation est obligatoire.',
            'category.required' => 'La catégorie est obligatoire.',
            'quantity.required' => 'La quantité est obligatoire.',
            'quantity.integer' => 'La quantité doit être un nombre entier.',
            'quantity.min' => 'La quantité doit être supérieure ou égale à 1.',
            'quantity.max' => 'La quantité ne peut pas dépasser 1 000 000.',
            'physical_condition.in' => 'L’état physique doit être new, good, average ou damaged.',
            'commissioned_at.date' => 'La date de mise en service est invalide.',
            'acquired_at.date' => 'La date d’acquisition est invalide.',
        ];
        $validator = Validator::make($values, $rules, $messages);

        return $validator->errors()->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function markDuplicates(Company $company, string $type, array &$rows): int
    {
        $candidateRows = array_filter($rows, fn (array $row): bool => $row['errors'] === []);
        $identifiers = $this->existingIdentityKeys($company, $type, $candidateRows);
        $seen = [];
        $duplicateCount = 0;

        foreach ($rows as &$row) {
            if ($row['errors'] !== []) {
                continue;
            }

            $identityKeys = $this->identityKeys($type, $row['values']);
            $matchedKeys = array_values(array_filter(
                $identityKeys,
                fn (string $key): bool => isset($identifiers[$key]) || isset($seen[$key]),
            ));

            if ($matchedKeys !== []) {
                $row['status'] = 'duplicate';
                $row['duplicate_reason'] = 'Une référence ou un numéro de série existe déjà dans cette entreprise ou dans une ligne précédente.';
                $duplicateCount++;
            }

            foreach ($identityKeys as $identityKey) {
                $seen[$identityKey] = true;
            }
        }

        unset($row);

        return $duplicateCount;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, true>
     */
    private function existingIdentityKeys(Company $company, string $type, array $rows): array
    {
        $identifiers = [];

        if ($type === CompanyDocument::TYPE_LISTE_PERSONNEL) {
            $cins = collect($rows)
                ->pluck('values.cin')
                ->filter(fn (mixed $cin): bool => filled($cin))
                ->map(fn (string $cin): string => Str::upper(trim($cin)))
                ->unique()
                ->values()
                ->all();

            if ($cins === []) {
                return [];
            }

            $existingCins = CompanyEmployee::query()
                ->where('company_id', $company->getKey())
                ->whereIn(DB::raw('UPPER(cin)'), $cins)
                ->pluck('cin');

            foreach ($existingCins as $cin) {
                $identifiers['cin:'.Str::upper(trim((string) $cin))] = true;
            }

            return $identifiers;
        }

        $serialNumbers = collect($rows)
            ->pluck('values.serial_number')
            ->filter(fn (mixed $value): bool => filled($value))
            ->map(fn (string $value): string => Str::upper(trim($value)))
            ->unique()
            ->values()
            ->all();
        $references = collect($rows)
            ->pluck($type === CompanyDocument::TYPE_LISTE_MATERIEL ? 'values.registration_number' : 'values.internal_reference')
            ->filter(fn (mixed $value): bool => filled($value))
            ->map(fn (string $value): string => Str::upper(trim($value)))
            ->unique()
            ->values()
            ->all();

        if ($serialNumbers === [] && $references === []) {
            return [];
        }

        if ($type === CompanyDocument::TYPE_LISTE_MATERIEL) {
            $query = CompanyMachinery::query()
                ->where('company_id', $company->getKey())
                ->where(function (Builder $query) use ($serialNumbers, $references): void {
                    if ($serialNumbers !== []) {
                        $query->whereIn(DB::raw('UPPER(serial_number)'), $serialNumbers);
                    }

                    if ($references !== []) {
                        $method = $serialNumbers === [] ? 'whereIn' : 'orWhereIn';
                        $query->{$method}(DB::raw('UPPER(registration_number)'), $references);
                    }
                });
            $existingRecords = $query->get(['serial_number', 'registration_number']);

            foreach ($existingRecords as $record) {
                if (filled($record->serial_number)) {
                    $identifiers['serial:'.Str::upper(trim($record->serial_number))] = true;
                }

                if (filled($record->registration_number)) {
                    $identifiers['reference:'.Str::upper(trim($record->registration_number))] = true;
                }
            }

            return $identifiers;
        }

        $query = CompanyTooling::query()
            ->where('company_id', $company->getKey())
            ->where(function (Builder $query) use ($serialNumbers, $references): void {
                if ($serialNumbers !== []) {
                    $query->whereIn(DB::raw('UPPER(serial_number)'), $serialNumbers);
                }

                if ($references !== []) {
                    $method = $serialNumbers === [] ? 'whereIn' : 'orWhereIn';
                    $query->{$method}(DB::raw('UPPER(internal_reference)'), $references);
                }
            });
        $existingRecords = $query->get(['serial_number', 'internal_reference']);

        foreach ($existingRecords as $record) {
            if (filled($record->serial_number)) {
                $identifiers['serial:'.Str::upper(trim($record->serial_number))] = true;
            }

            if (filled($record->internal_reference)) {
                $identifiers['reference:'.Str::upper(trim($record->internal_reference))] = true;
            }
        }

        return $identifiers;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<int, string>
     */
    private function identityKeys(string $type, array $values): array
    {
        if ($type === CompanyDocument::TYPE_LISTE_PERSONNEL) {
            return filled($values['cin'] ?? null)
                ? ['cin:'.Str::upper(trim((string) $values['cin']))]
                : [];
        }

        $keys = [];

        if (filled($values['serial_number'] ?? null)) {
            $keys[] = 'serial:'.Str::upper(trim((string) $values['serial_number']));
        }

        $referenceField = $type === CompanyDocument::TYPE_LISTE_MATERIEL
            ? 'registration_number'
            : 'internal_reference';

        if (filled($values[$referenceField] ?? null)) {
            $keys[] = 'reference:'.Str::upper(trim((string) $values[$referenceField]));
        }

        return $keys;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function createImportedRecord(
        Company $company,
        CompanyDocument $document,
        User $actor,
        array $row,
    ): void {
        $values = $row['values'];
        $metadata = [
            'source' => 'excel_import',
            'source_file' => $document->file_name,
            'source_document_id' => $document->getKey(),
            'source_document_path' => $document->file_path,
            'excel_row' => $row['excel_row'],
        ];

        if ($document->type === CompanyDocument::TYPE_LISTE_PERSONNEL) {
            $employee = $company->employees()->create([
                'first_name' => $values['first_name'],
                'last_name' => $values['last_name'],
                'cin' => $values['cin'],
                'position' => $values['position'],
                'locker_number' => $values['locker_number'],
                'status' => CompanyEmployee::STATUS_DRAFT,
            ]);

            foreach (CompanyEmployeeDocument::allTypes() as $type) {
                $employee->documents()->create([
                    'type' => $type,
                    'label' => CompanyEmployeeDocument::labelForType($type),
                    'status' => CompanyEmployeeDocument::STATUS_DRAFT,
                ]);
            }

            $employee->history()->create([
                'user_id' => $actor->getKey(),
                'action' => 'created',
                'to_status' => CompanyEmployee::STATUS_DRAFT,
                'reason' => 'Création via import Excel de la liste du personnel.',
                'metadata' => $metadata,
            ]);

            return;
        }

        if ($document->type === CompanyDocument::TYPE_LISTE_MATERIEL) {
            $machinery = $company->machinery()->create([
                'designation' => $values['designation'],
                'category' => $values['category'],
                'assignment' => $values['assignment'],
                'brand' => $values['brand'],
                'model' => $values['model'],
                'serial_number' => $values['serial_number'],
                'registration_number' => $values['registration_number'],
                'commissioned_at' => $values['commissioned_at'],
                'description' => $values['description'],
                'status' => CompanyMachinery::STATUS_DRAFT,
            ]);

            foreach (CompanyMachineryDocument::allTypes() as $type) {
                $machinery->documents()->create([
                    'type' => $type,
                    'label' => CompanyMachineryDocument::labelForType($type),
                    'status' => CompanyMachineryDocument::STATUS_DRAFT,
                ]);
            }

            $machinery->history()->create([
                'user_id' => $actor->getKey(),
                'action' => 'created',
                'to_status' => CompanyMachinery::STATUS_DRAFT,
                'reason' => 'Création via import Excel de la liste du matériel.',
                'metadata' => $metadata,
            ]);

            return;
        }

        $tooling = $company->toolings()->create([
            'designation' => $values['designation'],
            'category' => $values['category'],
            'internal_reference' => $values['internal_reference'],
            'brand' => $values['brand'],
            'model' => $values['model'],
            'serial_number' => $values['serial_number'],
            'quantity' => $values['quantity'],
            'physical_condition' => $values['physical_condition'],
            'acquired_at' => $values['acquired_at'],
            'location' => $values['location'],
            'description' => $values['description'],
            'observations' => $values['observations'],
            'status' => CompanyTooling::STATUS_DRAFT,
        ]);

        foreach (CompanyToolingDocument::standardTypes() as $type) {
            $tooling->documents()->create([
                'type' => $type,
                'label' => CompanyToolingDocument::labelForType($type),
                'status' => CompanyToolingDocument::STATUS_DRAFT,
            ]);
        }

        $tooling->history()->create([
            'user_id' => $actor->getKey(),
            'action' => 'created',
            'to_status' => CompanyTooling::STATUS_DRAFT,
            'reason' => 'Création via import Excel de la liste outillage.',
            'metadata' => $metadata,
        ]);
    }

    private function normalizeHeader(string $header): string
    {
        $normalized = Str::ascii(mb_strtolower(trim($header)));
        $normalized = preg_replace('/[^a-z0-9]+/', ' ', $normalized);

        return trim(is_string($normalized) ? $normalized : '');
    }

    private function isEligibleDocument(Company $company, CompanyDocument $document): bool
    {
        return $document->exists
            && (int) $document->company_id === (int) $company->getKey()
            && $this->isSpreadsheetDocument($document)
            && filled($document->file_path);
    }

    private function importedRecordsQuery(Company $company, CompanyDocument $document): Builder
    {
        $currentVersionUploadedAt = $document->versions()
            ->where('is_current', true)
            ->value('uploaded_at');
        $historyConstraint = function (Builder $query) use ($document, $currentVersionUploadedAt): void {
            $query
                ->where('metadata->source', 'excel_import')
                ->where(function (Builder $sourceQuery) use ($document, $currentVersionUploadedAt): void {
                    $sourceQuery->where(function (Builder $versionQuery) use ($document): void {
                        $versionQuery
                            ->where('metadata->source_document_id', $document->getKey())
                            ->where('metadata->source_document_path', $document->file_path);
                    });

                    if ($currentVersionUploadedAt !== null) {
                        $sourceQuery->orWhere(function (Builder $legacyQuery) use ($document, $currentVersionUploadedAt): void {
                            $legacyQuery
                                ->whereNull('metadata->source_document_path')
                                ->where('metadata->source_file', $document->file_name)
                                ->where('created_at', '>=', $currentVersionUploadedAt);
                        });
                    }
                });
        };

        return match ($document->type) {
            CompanyDocument::TYPE_LISTE_PERSONNEL => CompanyEmployee::query()
                ->where('company_id', $company->getKey())
                ->whereHas('history', $historyConstraint),
            CompanyDocument::TYPE_LISTE_MATERIEL => CompanyMachinery::query()
                ->where('company_id', $company->getKey())
                ->whereHas('history', $historyConstraint),
            CompanyDocument::TYPE_LISTE_OUTILLAGE => CompanyTooling::query()
                ->where('company_id', $company->getKey())
                ->whereHas('history', $historyConstraint),
            default => throw new RuntimeException('Le type de document ne prend pas en charge les données Excel.'),
        };
    }

    private function displayCellValue(Worksheet $worksheet, string $column, int $row): string
    {
        $cell = $worksheet->getCell($column.$row);
        $value = $cell->getValue();

        if ($value instanceof RichText) {
            $value = $value->getPlainText();
        } elseif ($cell->getDataType() === DataType::TYPE_FORMULA) {
            $value = (string) $value;
        } elseif (is_scalar($value)) {
            $value = $cell->getFormattedValue();
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function mappedCellValue(
        Cell $cell,
        string $field,
    ): mixed {
        $value = $cell->getValue();

        if ($value instanceof RichText) {
            $value = $value->getPlainText();
        }

        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        if (in_array($field, ['commissioned_at', 'acquired_at'], true)) {
            if ($value instanceof \DateTimeInterface) {
                return $value->format('Y-m-d');
            }

            if (is_numeric($value) && (float) $value > 0) {
                try {
                    return SpreadsheetDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
                } catch (SpreadsheetException) {
                    return trim((string) $cell->getFormattedValue());
                }
            }
        }

        if ($field === 'physical_condition' && is_string($value)) {
            $condition = match ($this->normalizeHeader($value)) {
                'new', 'neuf' => 'new',
                'good', 'bon' => 'good',
                'average', 'moyen' => 'average',
                'damaged', 'a reparer', 'hors service' => 'damaged',
                default => null,
            };

            if ($condition !== null) {
                return $condition;
            }
        }

        if (is_scalar($value)) {
            $formattedValue = $cell->getFormattedValue();

            if (is_scalar($formattedValue)) {
                return trim((string) $formattedValue);
            }
        }

        return null;
    }

    /**
     * @param  array<string, string|null>  $mapping
     * @param  array<string, mixed>  $definitions
     */
    private function emptyPreview(
        CompanyDocument $document,
        string $fatalError,
        ?string $sourceHash = null,
    ): array {
        return [
            'file_name' => $document->file_name,
            'type' => $document->type,
            'destination' => $this->destinationLabel($document->type),
            'sheet_name' => null,
            'header_row' => null,
            'headers' => [],
            'definitions' => $this->fieldDefinitions($document->type),
            'mappings' => array_fill_keys(array_keys($this->fieldDefinitions($document->type)), null),
            'mapping_errors' => [],
            'ambiguous_columns' => [],
            'unrecognized_columns' => [],
            'rows' => [],
            'total_rows' => 0,
            'valid_rows' => 0,
            'invalid_rows' => 0,
            'duplicate_rows' => 0,
            'source_hash' => $sourceHash,
            'fatal_error' => $fatalError,
        ];
    }

    private function destinationLabel(string $type): string
    {
        return match ($type) {
            CompanyDocument::TYPE_LISTE_PERSONNEL => 'Mon équipe',
            CompanyDocument::TYPE_LISTE_MATERIEL => 'Mon matériel',
            CompanyDocument::TYPE_LISTE_OUTILLAGE => 'Mon outillage',
            default => 'Enregistrements',
        };
    }

    private function createdLabel(string $type, int $count): string
    {
        return match ($type) {
            CompanyDocument::TYPE_LISTE_PERSONNEL => $count.' '.($count === 1 ? 'employé créé' : 'employés créés'),
            CompanyDocument::TYPE_LISTE_MATERIEL => $count.' '.($count === 1 ? 'équipement créé' : 'équipements créés'),
            CompanyDocument::TYPE_LISTE_OUTILLAGE => $count.' '.($count === 1 ? 'outillage créé' : 'outillages créés'),
            default => $count.' enregistrement(s) créé(s)',
        };
    }
}
