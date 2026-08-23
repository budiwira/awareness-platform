<?php

namespace App\Services;

use App\Models\User;
use App\Support\Audit\Audit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CsvImporter
{
    private array $allowedRoles = ['user', 'tenant_admin'];
    private int $maxRows = 500;

    public function importUsers(UploadedFile $file, string $tenantId, int $actorId): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            throw new \RuntimeException('Gagal membuka file CSV.');
        }

        // Skip header
        $header = fgetcsv($handle);
        if (!$header || strtolower(trim($header[0] ?? '')) !== 'name') {
            fclose($handle);
            throw ValidationException::withMessages(['file' => 'Format CSV tidak valid. Kolom pertama harus "name".']);
        }

        $rows = [];
        $rowCount = 0;

        while (($data = fgetcsv($handle)) !== false) {
            $rowCount++;
            if ($rowCount > $this->maxRows) {
                fclose($handle);
                throw ValidationException::withMessages(['file' => "Maksimal {$this->maxRows} baris data."]);
            }

            if (count($data) < 3) continue;

            $name = trim($data[0]);
            $email = trim($data[1]);
            $role = trim($data[2]);

            // Sanitasi CSV Injection (Excel Formula Injection)
            $name = $this->sanitizeCsvInjection($name);

            if (!in_array($role, $this->allowedRoles)) {
                fclose($handle);
                throw ValidationException::withMessages(['file' => "Role '{$role}' tidak valid pada baris ke-{$rowCount}. Hanya 'user' atau 'tenant_admin' yang diperbolehkan."]);
            }

            $rows[] = [
                'name' => $name,
                'email' => $email,
                'role' => $role,
                'tenant_id' => $tenantId, // Dipaksa dari server
                'password' => bcrypt(Str::random(32)),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        fclose($handle);

        if (empty($rows)) {
            throw ValidationException::withMessages(['file' => 'File CSV kosong.']);
        }

        // Atomic transaction
        DB::beginTransaction();
        try {
            foreach ($rows as $row) {
                User::create($row);
            }
            DB::commit();
            Audit::log('user.bulk_imported', null, ['count' => count($rows), 'actor_id' => $actorId]);
            return $rows;
        } catch (UniqueConstraintViolationException $e) {
            DB::rollBack();
            throw ValidationException::withMessages(['file' => 'Gagal import: Terdapat email duplikat. Seluruh proses dibatalkan.']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function sanitizeCsvInjection(string $value): string
    {
        // Mencegah Excel Formula Injection dengan menambahkan tanda kutip satu di awal
        if (preg_match('/^[=+\-@]/', $value)) {
            return "'" . $value;
        }
        return $value;
    }
}