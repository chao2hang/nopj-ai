<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if ($schema->hasTable('nopj_ai_sessions')) {
            return;
        }

        $schema->create('nopj_ai_sessions', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('discussion_id')->unsigned();
            $table->integer('ai_user_id')->unsigned();
            $table->string('session_uuid', 36);
            $table->timestamps();

            $table->index(['discussion_id', 'ai_user_id']);
            $table->index('session_uuid');
        });
    },

    'down' => function (Builder $schema) {
        $schema->dropIfExists('nopj_ai_sessions');
    },
];
