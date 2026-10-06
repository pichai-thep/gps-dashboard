<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\EngineCutController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserEngineCutOptionsTest extends TestCase
{
    public function test_commands_use_authenticated_user_options_and_password(): void
    {
        config(['database.connections.engine_options_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]]);
        Schema::connection('engine_options_test')->create('user', function (Blueprint $table) {
            $table->string('login');
            $table->boolean('enable_engine_cut')->nullable();
            $table->string('engine_cut_pwd')->nullable();
        });
        $db = DB::connection('engine_options_test');
        $db->table('user')->insert([
            'login' => 'driver', 'enable_engine_cut' => 1, 'engine_cut_pwd' => '001234',
        ]);
        $method = new \ReflectionMethod(EngineCutController::class, 'authorizeEngineCut');
        foreach ([
            [1, '001234', '001234', true],
            [1, '001234', '1234', false],
            [0, '001234', '001234', false],
            [null, '001234', '001234', false],
            [1, null, '', true],
        ] as [$enabled, $stored, $supplied, $allowed]) {
            $db->table('user')->update(['enable_engine_cut' => $enabled, 'engine_cut_pwd' => $stored]);
            $request = Request::create('/', 'POST', ['username' => 'another-user', 'password' => $supplied]);
            $request->attributes->set('auth_user', (object) ['login' => 'driver']);
            $error = null;
            try {
                $method->invoke(new EngineCutController, $request, 'engine_options_test');
            } catch (\Exception $exception) {
                $error = $exception;
            }
            $this->assertSame($allowed, $error === null);
        }
        DB::purge('engine_options_test');
    }
}
