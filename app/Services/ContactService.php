<?php

namespace App\Services;

use App\Imports\ContactsImport;
use App\Models\Contact;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class ContactService
{
    private const RULES = [
        'contacts' => ['required', 'array', 'min:1'],
        'contacts.*.name' => ['required', 'string', 'max:255'],
        'contacts.*.email' => ['required', 'email', 'max:255'],
        'contacts.*.phone' => ['nullable', 'string', 'max:30'],
        'contacts.*.age' => ['nullable', 'integer', 'min:0', 'max:150'],
    ];

    /**
     * Read the Excel file, validate its rows and save them.
     *
     * @return array{created: int, updated: int}|null  null when the file has no valid rows
     */
    public function importAndStore(UploadedFile $file): ?array
    {
        $contacts = $this->readImportFile($file);

        if ($contacts === []) {
            return null;
        }

        Validator::make(['contacts' => $contacts], self::RULES)->validate();

        return $this->store($contacts);
    }

    /**
     * Get all saved contacts.
     *
     * @return array{count: int, contacts: \Illuminate\Database\Eloquent\Collection}
     */
    public function getAll(): array
    {
        $contacts = Contact::orderBy('id')->get();

        return [
            'count' => $contacts->count(),
            'contacts' => $contacts,
        ];
    }

    /**
     * @return array<int, array{name: string, email: string, phone: string|null, age: int|null}>
     */
    private function readImportFile(UploadedFile $file): array
    {
        $sheets = Excel::toArray(new ContactsImport, $file);
        $contacts = [];

        foreach ($sheets[0] ?? [] as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $email = trim((string) ($row['email'] ?? ''));

            if ($name === '' || $email === '') {
                continue;
            }

            $phone = $row['phone'] ?? null;
            $age = $row['age'] ?? null;

            $contacts[] = [
                'name' => $name,
                'email' => $email,
                'phone' => $phone === null || trim((string) $phone) === '' ? null : (string) $phone,
                'age' => $age === null || trim((string) $age) === '' ? null : (int) $age,
            ];
        }

        return $contacts;
    }

    /**
     * Save contacts, updating the ones whose email already exists.
     *
     * @param  array<int, array{name: string, email: string, phone?: string|null, age?: int|null}>  $contacts
     * @return array{created: int, updated: int}
     */
    private function store(array $contacts): array
    {
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($contacts, &$created, &$updated): void {
            foreach ($contacts as $row) {
                $contact = Contact::updateOrCreate(['email' => $row['email']], $row);

                $contact->wasRecentlyCreated ? $created++ : $updated++;
            }
        });

        return ['created' => $created, 'updated' => $updated];
    }
}
