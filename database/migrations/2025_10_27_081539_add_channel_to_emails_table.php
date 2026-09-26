<?php

use App\Enums\Core\EmailChannelEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('t_Emails', static function (Blueprint $table) {
            $table->char('Channel', 4)->default(EmailChannelEnum::SMTP->value)->after('EmailID');
        });
    }

    public function down(): void
    {
        Schema::table('t_Emails', static function (Blueprint $table) {
            $table->dropColumn('Channel');
        });
    }
};
