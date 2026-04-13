<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if ($schema->hasTable('nopj_ai_messages')) {
            return;
        }

        $schema->create('nopj_ai_messages', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('session_id')->unsigned();
            $table->integer('post_id')->unsigned()->nullable();
            $table->string('role', 20);
            $table->text('content');
            $table->timestamp('created_at');

            $table->index('session_id');
            $table->index('post_id');
        });
    },

    'down' => function (Builder $schema) {
        $schema->dropIfExists('nopj_ai_messages');
    },
];
