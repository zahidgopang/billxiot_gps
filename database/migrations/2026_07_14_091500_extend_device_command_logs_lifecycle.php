<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('device_command_logs')) {
            return;
        }

        Schema::table('device_command_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('device_command_logs', 'wire_type')) {
                $table->string('wire_type', 64)->nullable()->after('type');
            }
            if (! Schema::hasColumn('device_command_logs', 'wire_data')) {
                $table->text('wire_data')->nullable()->after('wire_type');
            }
            if (! Schema::hasColumn('device_command_logs', 'protocol_profile')) {
                $table->string('protocol_profile', 64)->nullable()->after('wire_data');
            }
            if (! Schema::hasColumn('device_command_logs', 'stages')) {
                $table->json('stages')->nullable()->after('result');
            }
            if (! Schema::hasColumn('device_command_logs', 'last_event_id')) {
                $table->unsignedBigInteger('last_event_id')->nullable()->after('queue_id');
            }
            if (! Schema::hasColumn('device_command_logs', 'timeout_at')) {
                $table->timestamp('timeout_at')->nullable()->after('last_event_id');
            }
            if (! Schema::hasColumn('device_command_logs', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('timeout_at');
            }
            if (! Schema::hasColumn('device_command_logs', 'executed_at')) {
                $table->timestamp('executed_at')->nullable()->after('delivered_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('device_command_logs')) {
            return;
        }

        Schema::table('device_command_logs', function (Blueprint $table) {
            foreach ([
                'wire_type', 'wire_data', 'protocol_profile', 'stages',
                'last_event_id', 'timeout_at', 'delivered_at', 'executed_at',
            ] as $col) {
                if (Schema::hasColumn('device_command_logs', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
