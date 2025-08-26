<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('document_request_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_request_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('comment');
            $table->string('attachment')->nullable();
            $table->timestamps();

            $table->index(['document_request_id', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('document_request_comments');
    }
};