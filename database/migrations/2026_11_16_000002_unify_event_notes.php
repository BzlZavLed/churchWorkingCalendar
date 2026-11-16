<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('event_notes', function (Blueprint $table) {
            $table->foreignId('event_history_id')->nullable()->unique()->constrained('event_histories')->nullOnDelete();
            $table->string('kind')->default('general');
            $table->string('author_role')->nullable();
        });
        DB::table('event_notes')->orderBy('id')->each(function ($note) {
            DB::table('event_notes')->where('id', $note->id)->update([
                'author_role' => DB::table('users')->where('id', $note->user_id)->value('role'),
            ]);
            if (!empty($note->reply)) {
                DB::table('event_notes')->insert([
                    'event_id' => $note->event_id, 'user_id' => $note->reply_user_id,
                    'note' => $note->reply, 'kind' => 'general',
                    'author_role' => DB::table('users')->where('id', $note->reply_user_id)->value('role'),
                    'seen_note' => true, 'created_at' => $note->replied_at ?? $note->created_at,
                    'updated_at' => $note->replied_at ?? $note->created_at,
                ]);
                DB::table('event_notes')->where('id', $note->id)->update(['reply' => null, 'reply_user_id' => null, 'replied_at' => null]);
            }
        });
        DB::table('event_histories')->whereNotNull('note')->orderBy('id')->each(function ($history) {
            if (trim($history->note) === '') return;
            DB::table('event_notes')->insert([
                'event_id' => $history->event_id, 'user_id' => $history->user_id,
                'event_history_id' => $history->id, 'note' => $history->note,
                'kind' => $history->action,
                'author_role' => DB::table('users')->where('id', $history->user_id)->value('role'),
                'seen_note' => false, 'created_at' => $history->created_at, 'updated_at' => $history->updated_at,
            ]);
            DB::table('event_histories')->where('id', $history->id)->update(['note' => null]);
        });
        DB::table('events')->whereNotNull('review_note')->orderBy('id')->each(function ($event) {
            if (trim($event->review_note) === '' || DB::table('event_notes')->where('event_id', $event->id)->where('note', $event->review_note)->exists()) return;
            DB::table('event_notes')->insert([
                'event_id' => $event->id, 'user_id' => $event->reviewed_by,
                'note' => $event->review_note, 'kind' => 'review', 'author_role' => 'secretary',
                'seen_note' => false, 'created_at' => $event->reviewed_at ?? $event->updated_at,
                'updated_at' => $event->updated_at,
            ]);
        });
    }

    public function down(): void
    {
        DB::table('event_notes')->whereNotNull('event_history_id')->orderBy('id')->each(function ($note) {
            DB::table('event_histories')->where('id', $note->event_history_id)->update(['note' => $note->note]);
        });
        DB::table('event_notes')->whereNotNull('event_history_id')->delete();
        Schema::table('event_notes', function (Blueprint $table) {
            $table->dropUnique(['event_history_id']);
            $table->dropConstrainedForeignId('event_history_id');
            $table->dropColumn(['kind', 'author_role']);
        });
    }
};
