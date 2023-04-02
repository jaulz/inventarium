<?php

namespace Jaulz\Inventarium\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tpetry\PostgresqlEnhanced\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

    $migration = include __DIR__ . '/../database/migrations/2013_01_09_141532_create_inventarium_extension.php.stub';
    $migration->up();
});

test('creates correct vectors', function () {
    Schema::create('posts', function (Blueprint $table) {
        $table->id();
        $table->text('title');
    });

    Schema::table('posts', function (Blueprint $table) {
        $table->inventarium('title', [
            'weight' => 'A',
        ]);
    });

    collect([
        'a fat cat sat on a mat and ate a fat rat' => "'a':1,6,10 'and':8 'ate':9 'cat':3 'fat':2,11 'mat':7 'on':5 'rat':12 'sat':4",
    ])->each(function ($value, $key) {
        $post = DB::table('posts')->insertReturning([
            'title' => $key
        ])->first();

        expect($post->vectors)->toBe($value);
    });
});

test('creates correct trigrams', function () {
    Schema::create('posts', function (Blueprint $table) {
        $table->id();
        $table->text('title');
    });

    Schema::table('posts', function (Blueprint $table) {
        $table->inventarium('title', [
            'weight' => 'A',
        ]);
    });

    collect([
        'a fat cat sat on a mat and ate a fat rat' => '{"  a","  c","  f","  m","  o","  r","  s"," a "," an"," at"," ca"," fa"," ma"," on"," ra"," sa",and,"at ",ate,cat,fat,mat,"nd ","on ",rat,sat,"te "}',
    ])->each(function ($value, $key) {
        $post = DB::table('posts')->insertReturning([
            'title' => $key
        ])->first();

        expect($post->trigrams)->toBe($value);
    });
});

test('can use different languages', function () {
    Schema::create('posts', function (Blueprint $table) {
        $table->id();
        $table->text('title');
        $table->text('language_code');
    });

    Schema::table('posts', function (Blueprint $table) {
        $table->inventarium('title', [
            'weight' => 'A',
            'language' => 'language_code',
        ]);
    });

    collect([
        'xx' => [
            'title' => 'The Fat Rats',
            'vectors' => "'fat':2 'rats':3 'the':1",
            'trigrams' => '{"  f","  r","  t"," fa"," ra"," th","at ",ats,fat,"he ",rat,the,"ts "}',
        ],
        'en' => [
            'title' => 'The Fat Rats',
            'vectors' => "'fat':2 'rat':3",
            'trigrams' => '{"  f","  r"," fa"," ra","at ",fat,rat}',
        ],
        'de' => [
            'title' => 'Die fetten Ratten',
            'vectors' => "'fett':2 'ratt':3",
            'trigrams' => '{"  f","  r"," fe"," ra",att,ett,fet,rat,"tt "}',
        ],
    ])->each(function ($value, $key) {
        $post = DB::table('posts')->insertReturning([
            'title' => $value['title'],
            'language_code' => $key,
        ])->first();

        expect($post->vectors)->toBe($value['vectors']);
        expect($post->trigrams)->toBe($value['trigrams']);
    });
});

test('can handle source expressions', function () {
    Schema::create('posts', function (Blueprint $table) {
        $table->id();
        $table->jsonb('title');
    });

    Schema::table('posts', function (Blueprint $table) {
        $table->inventarium("array_to_string(ARRAY(SELECT jsonb_array_elements_text(jsonb_path_query_array(title, '$.*'))), ' ')", [
            'weight' => 'A',
        ]);
    });

    $post = DB::table('posts')->insertReturning([
        'title' => json_encode([
            'en' => 'Germany',
            'de' => 'Deutschland',
            'fr' => 'Alemagne'
        ])
    ])->first();

    expect($post->vectors)->toBe("'alemagne':3 'deutschland':1 'germany':2");
    expect($post->trigrams)->toBe('{"  a","  d","  g"," al"," de"," ge",agn,ale,and,any,chl,deu,ema,erm,eut,ger,gne,hla,lan,lem,mag,man,"nd ","ne ","ny ",rma,sch,tsc,uts}');
});

test('enables full-text search', function () {
    Schema::create('posts', function (Blueprint $table) {
        $table->id();
        $table->text('title');
        $table->text('language_code');
    });

    Schema::table('posts', function (Blueprint $table) {
        $table->inventarium('title', [
            'weight' => 'A',
            'language' => 'language_code',
        ]);
    });

    collect([
        'xx' => 'The Fat Rats',
        'en' => 'The Fat Rats',
        'de' => 'Die fetten Ratten',
    ])->each(function ($value, $key) {
         DB::table('posts')->insertReturning([
            'title' => $value,
            'language_code' => $key,
        ])->first();
    });

    expect(
        collect(DB::select("SELECT * FROM posts WHERE vectors @@ to_tsquery('english', 'fat')"))->map(fn ($row) => $row->id)->toArray()
    )->toBe([1, 2]);
});

test('enables trigram search', function () {
    Schema::create('posts', function (Blueprint $table) {
        $table->id();
        $table->text('title');
        $table->text('language_code');
    });

    Schema::table('posts', function (Blueprint $table) {
        $table->inventarium('title', [
            'weight' => 'A',
            'language' => 'language_code',
        ]);
    });

    collect([
        'xx' => 'Lewis',
        'en' => 'Louis',
        'de' => 'Luis',
    ])->each(function ($value, $key) {
         DB::table('posts')->insertReturning([
            'title' => $value,
            'language_code' => $key,
        ])->first();
    });

    expect(
        collect(DB::select("SELECT * FROM posts WHERE trigrams % 'luis'"))->map(fn ($row) => $row->id)->toArray()
    )->toBe([1, 2, 3]);
});