<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('colour', 7)->nullable()->after('remember_token');
        });

        $usedColours = [];

        DB::table('users')
            ->select('id')
            ->orderBy('id')
            ->each(function (object $user) use (&$usedColours): void {
                $colour = $this->uniqueColour($usedColours);
                $usedColours[] = $colour;

                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['colour' => $colour]);
            });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('colour');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['colour']);
            $table->dropColumn('colour');
        });
    }

    /**
     * @param  array<int, string>  $usedColours
     */
    private function uniqueColour(array $usedColours): string
    {
        do {
            $colour = sprintf('#%06X', random_int(0, 0xFFFFFF));
        } while (in_array($colour, $usedColours, true));

        return $colour;
    }
};
