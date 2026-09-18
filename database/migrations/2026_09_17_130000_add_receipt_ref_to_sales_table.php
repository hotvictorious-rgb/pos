<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('sales') && !Schema::hasColumn('sales', 'receipt_ref')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->string('receipt_ref', 100)->nullable()->after('note')->index();
            });
        }

        // Backfill receipt_ref from existing sales.note field
        if (Schema::hasTable('sales') && Schema::hasColumn('sales', 'receipt_ref')) {
            try {
                $salesWithNotes = DB::table('sales')
                    ->whereNotNull('note')
                    ->where('note', 'like', '%RECEIPT REF%')
                    ->whereNull('receipt_ref')
                    ->select('id', 'note')
                    ->get();

                foreach ($salesWithNotes as $sale) {
                    if (preg_match('/\[RECEIPT REF:\s*#?([^\]]+)\]/i', $sale->note ?? '', $matches)) {
                        $extractedRef = trim($matches[1]);
                        if (!empty($extractedRef)) {
                            DB::table('sales')->where('id', $sale->id)->update([
                                'receipt_ref' => $extractedRef,
                            ]);
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Non-fatal fallback for historical data backfill
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('sales') && Schema::hasColumn('sales', 'receipt_ref')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropColumn('receipt_ref');
            });
        }
    }
};
