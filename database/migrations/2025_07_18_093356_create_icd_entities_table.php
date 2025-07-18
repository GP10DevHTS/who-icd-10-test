<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIcdEntitiesTable extends Migration
{
    public function up()
    {
        Schema::create('icd_entities', function (Blueprint $table) {
            $table->id(); // local ID

            $table->unsignedBigInteger('who_id')->unique(); // WHO ICD entity ID
            $table->unsignedBigInteger('parent_who_id')->nullable()->index(); // For hierarchy

            $table->string('code')->nullable(); // Optional ICD code (if available)
            $table->string('title')->nullable();
            $table->text('definition')->nullable();

            $table->string('release_id')->nullable();
            $table->date('release_date')->nullable();

            $table->json('raw_json')->nullable(); // Optional: full entity blob

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('icd_entities');
    }
}
