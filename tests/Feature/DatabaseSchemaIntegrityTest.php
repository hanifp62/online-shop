<?php

namespace Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;

/**
 * Validasi skema: setiap model Eloquent harus punya tabel, primary key, dan
 * seluruh kolom $fillable/$casts yang benar-benar ada setelah migrasi bersih.
 * Menangkap drift model ↔ migrasi sebelum sampai produksi.
 */
class DatabaseSchemaIntegrityTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, Model> */
    private function models(): array
    {
        $models = [];
        foreach (glob(app_path('Models/*.php')) as $file) {
            $class = 'App\\Models\\'.basename($file, '.php');
            $reflection = new ReflectionClass($class);
            if ($reflection->isAbstract() || ! $reflection->isSubclassOf(Model::class)) {
                continue;
            }
            $models[] = new $class;
        }

        return $models;
    }

    public function test_all_migration_files_are_applied_in_test_database(): void
    {
        $files = array_map(
            fn (string $file): string => basename($file, '.php'),
            glob(database_path('migrations/*.php'))
        );
        $applied = DB::table('migrations')->pluck('migration')->all();

        $this->assertEmpty(array_diff($files, $applied), 'Migrasi belum diterapkan pada skema uji');
    }

    public function test_application_does_not_call_legacy_getcat_procedure(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path()));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $this->assertDoesNotMatchRegularExpression(
                '/\\b(?:CALL|EXEC(?:UTE)?)\\s+[`"]?getcat\\b/i',
                file_get_contents($file->getPathname()),
                $file->getPathname().' harus memakai Category::getById()'
            );
        }
    }

    public function test_every_model_maps_to_an_existing_table_with_its_columns(): void
    {
        $models = $this->models();
        $this->assertGreaterThan(20, count($models), 'model terdeteksi');

        foreach ($models as $model) {
            $class = $model::class;
            $table = $model->getTable();

            $this->assertTrue(Schema::hasTable($table), "{$class}: tabel {$table} tidak ada");
            $this->assertTrue(Schema::hasColumn($table, $model->getKeyName()), "{$class}: primary key {$model->getKeyName()} tidak ada");

            $columns = array_unique(array_merge($model->getFillable(), array_keys($model->getCasts())));
            foreach ($columns as $column) {
                $this->assertTrue(Schema::hasColumn($table, $column), "{$class}: kolom {$table}.{$column} tidak ada");
            }

            if ($model->usesTimestamps()) {
                $this->assertTrue(Schema::hasColumn($table, $model->getCreatedAtColumn()), "{$class}: kolom created_at hilang");

                // Tabel append-only meniadakan updated_at (UPDATED_AT = null).
                if ($model->getUpdatedAtColumn() !== null) {
                    $this->assertTrue(Schema::hasColumn($table, $model->getUpdatedAtColumn()), "{$class}: kolom updated_at hilang");
                }
            }
        }
    }
}
