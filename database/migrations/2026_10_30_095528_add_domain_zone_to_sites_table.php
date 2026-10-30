<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('domain_zone', 16)->nullable()->after('domain');

            $table->index('domain_zone', 'sites_domain_zone_index');
        });

        DB::table('sites')
            ->select(['id', 'domain'])
            ->orderBy('id')
            ->chunkById(500, function ($sites): void {
                foreach ($sites as $site) {
                    $zone = $this->zoneFromDomain((string) $site->domain);

                    if ($zone === null) {
                        continue;
                    }

                    DB::table('sites')->where('id', $site->id)->update(['domain_zone' => $zone]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropIndex('sites_domain_zone_index');
            $table->dropColumn('domain_zone');
        });
    }

    /**
     * Последняя метка домена в нижнем регистре; без точки зоны нет.
     */
    private function zoneFromDomain(string $domain): ?string
    {
        $domain = Str::lower(trim($domain));

        if (! str_contains($domain, '.')) {
            return null;
        }

        $zone = Str::afterLast($domain, '.');

        return $zone === '' ? null : $zone;
    }
};
