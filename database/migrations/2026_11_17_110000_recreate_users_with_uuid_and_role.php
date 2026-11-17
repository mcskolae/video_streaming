<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Les identifiants des utilisateurs passent d'entiers à des UUID v7, comme toutes les autres
 * tables du projet, et chaque compte reçoit un rôle.
 *
 * Le squelette Laravel crée `users` avec un identifiant entier. Changer le type d'une clé
 * primaire référencée serait délicat, mais à ce stade la table est vide : on la recrée.
 * Si elle contient des données, la migration refuse de s'exécuter plutôt que de les perdre.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->refuseIfUsersExist();

        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestampTz('email_verified_at')->nullable();
            $table->string('password');
            $table->enum('role', ['user', 'admin'])->default('user');
            $table->rememberToken();
            $table->timestampsTz();
        });

        // L'adresse est toujours stockée en minuscules : deux comptes ne peuvent pas ne différer que par la casse.
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_email_lowercase CHECK (email = lower(email))');

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->uuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        $this->refuseIfUsersExist();

        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    private function refuseIfUsersExist(): void
    {
        if (Schema::hasTable('users') && DB::table('users')->exists()) {
            throw new RuntimeException('The users table is not empty: refusing to recreate it. Convert the identifiers by hand.');
        }
    }
};
