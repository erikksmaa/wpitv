<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up():void {
        Schema::create('inventory_unit_members',function(Blueprint $table){
            $table->foreignId('unit_id')->primary()->constrained('inventory_units')->restrictOnDelete();
            $table->foreignId('parent_id')->constrained('inventory_units')->restrictOnDelete();
            $table->index(['parent_id','unit_id']);
        });
        DB::statement('ALTER TABLE inventory_unit_members ADD CHECK (parent_id < unit_id)');
    }
    public function down():void {throw new RuntimeException('Rollback otomatis diblokir untuk menjaga histori kelompok unit.');}
};
