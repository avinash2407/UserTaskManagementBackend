<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTasksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->bigInteger('created_by');
            $table->bigInteger('assigned_to');
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
            $table->dateTime('due_date');
            $table->dateTime('completed_at')->nullable();
            $table->enum('status',['assigned','inProgress','completed'])->default('assigned');
            $table->dateTime('deleted_at')->nullable();
            //$table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tasks');
    }
}
