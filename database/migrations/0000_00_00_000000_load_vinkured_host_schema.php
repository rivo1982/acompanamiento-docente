<?php

use App\Support\HostSchemaLoader;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Host-owned structure: loads the Vinkured schema snapshot into the current schema. Runs first so
 * module migrations can reference host tables. A no-op when the host is already visible in the
 * search_path (dev/integration); refuses when the search_path includes public.
 */
return new class extends Migration
{
    public function up(): void
    {
        HostSchemaLoader::fromDefaultSnapshot()->load(DB::connection($this->getConnection()));
    }

    /**
     * Intentionally a no-op: the host owns these objects. In tests they are removed by
     * dropAllTables (functions are recreated with CREATE OR REPLACE on the next load).
     */
    public function down(): void {}
};
