<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('t_Emails') && Schema::hasColumn('t_Emails', 'EmailConversationId')) {
            Schema::table('t_Emails', static function (Blueprint $table): void {
                $table->dropConstrainedForeignId('EmailConversationId');
            });
        }

        if (Schema::hasTable('t_Emails') && Schema::hasColumn('t_Emails', 'ReadBy')) {
            Schema::table('t_Emails', static function (Blueprint $table): void {
                $table->dropConstrainedForeignId('ReadBy');
            });
        }

        if (Schema::hasTable('t_Emails')) {
            $columns = collect(['ReferenceId', 'ReadOn'])
                ->filter(static fn (string $column): bool => Schema::hasColumn('t_Emails', $column))
                ->all();
            if ($columns !== []) {
                Schema::table('t_Emails', static function (Blueprint $table) use ($columns): void {
                    $table->dropColumn($columns);
                });
            }
        }

        Schema::dropIfExists('t_EmailConversationUsers');
        Schema::dropIfExists('t_EmailsConversations');
    }

    public function down(): void
    {
        // CRM email conversations are intentionally excluded from standalone DMS.
    }
};
