<?php

namespace App\Imports;

use App\Models\User;
use App\Models\OrganizationUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Validators\Failure;

class MembersImport implements
    ToCollection,
    WithHeadingRow,
    WithValidation,
    SkipsOnFailure,
    WithChunkReading
{
    use SkipsFailures;

    public int $importedCount = 0;

    public function __construct(
        private readonly int $organizationId
    ) {}

    /**
     * Process a chunk of valid rows.
     */
    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            // Skip rows where all key fields are empty
            if (empty($row['full_name']) && empty($row['email_address']) && empty($row['password'])) {
                continue;
            }

            $user = User::create([
                'name'     => $row['full_name'],
                'email'    => $row['email_address'],
                'password' => Hash::make($row['password']),
                'role'     => 'member',
            ]);

            OrganizationUser::create([
                'user_id'         => $user->id,
                'organization_id' => $this->organizationId,
                'user_type'       => 'member',
                'branch_role'     => 'member',
            ]);

            $this->importedCount++;
        }
    }

    /**
     * Validation rules applied per row (after heading row mapping).
     * Keys match the snake_cased heading row values.
     */
    public function rules(): array
    {
        return [
            'full_name'     => ['required', 'string', 'max:255'],
            'email_address' => ['required', 'email', 'unique:users,email'],
            'password'      => ['required', 'string', 'min:8'],
        ];
    }

    /**
     * Human-readable attribute names for validation messages.
     */
    public function customValidationAttributes(): array
    {
        return [
            'full_name'     => 'Full Name',
            'email_address' => 'Email Address',
            'password'      => 'Password',
        ];
    }

    /**
     * Read in chunks to handle large files without memory issues.
     */
    public function chunkSize(): int
    {
        return 200;
    }
}
