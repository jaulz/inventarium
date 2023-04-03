<?php

namespace Jaulz\Inventarium\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Jaulz\Inventarium\Facades\Inventarium;
use Tpetry\PostgresqlEnhanced\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

    $migration = include __DIR__ . '/../database/migrations/create_inventarium_extension.php.stub';
    $migration->up();
});

test('keeps searchables in sync', function () {
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
        // Create post
        $post = DB::table('posts')->insertReturning([
            'title' => $key
        ])->first();

        expect($post->search_vectors)->toBe($value);
        expect($post->search_text)->toBe($key);

        $searchablePost = DB::table(Inventarium::getSchema() . '.searchables')
        ->where('primary_key_value', $post->id)->first();

        expect($searchablePost->vectors)->toBe($post->search_vectors);
        expect($searchablePost->text)->toBe($post->search_text);

        // Update post
        $post = DB::table('posts')
        ->where('id', $post->id)
        ->updateReturning([
            'title' => 'or maybe not'
        ])->first();

        expect($post->search_vectors)->toBe("'maybe':2 'not':3 'or':1");
        expect($post->search_text)->toBe('or maybe not');

        $searchablePost = DB::table(Inventarium::getSchema() . '.searchables')
        ->where('primary_key_value', $post->id)->first();

        expect($searchablePost->vectors)->toBe($post->search_vectors);
        expect($searchablePost->text)->toBe($post->search_text);

        // Delete post
        DB::table('posts')
        ->where('id', $post->id)
        ->deleteReturning();

        $searchablePost = DB::table(Inventarium::getSchema() . '.searchables')
        ->where('primary_key_value', $post->id)->first();

        expect($searchablePost)->toBe(null);
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
        ],
        'en' => [
            'title' => 'The Fat Rats',
            'vectors' => "'fat':2 'rat':3",
        ],
        'de' => [
            'title' => 'Die fetten Ratten',
            'vectors' => "'fett':2 'ratt':3",
        ],
    ])->each(function ($value, $key) {
        $post = DB::table('posts')->insertReturning([
            'title' => $value['title'],
            'language_code' => $key,
        ])->first();

        expect($post->search_vectors)->toBe($value['vectors']);
    });
});

test('can handle JSON values', function () {
    Schema::create('posts', function (Blueprint $table) {
        $table->id();
        $table->jsonb('title');
    });

    Schema::table('posts', function (Blueprint $table) {
        // array_to_string(ARRAY(SELECT jsonb_array_elements_text(jsonb_path_query_array(title, '$.*'))), ' ')
        $table->inventarium("title", [
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

    expect($post->search_vectors)->toBe("'alemagne':6 'de':1 'deutschland':2 'en':3 'fr':5 'germany':4");
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
        collect(DB::select("SELECT * FROM posts WHERE search_vectors @@ to_tsquery('english', 'fat')"))->map(fn ($row) => $row->id)->toArray()
    )->toBe([1, 2]);
});

test('enables trigram search', function () {
    Schema::create('posts', function (Blueprint $table) {
        $table->id();
        $table->text('title');
        $table->text('language_code')->nullable();
    });

    Schema::table('posts', function (Blueprint $table) {
        $table->inventarium('title', [
            'weight' => 'A',
            'language' => 'language_code',
        ]);
    });

    collect([
        'Stephen',
        'Steve',
        'Seven',
    ])->each(function ($value, $key) {
         DB::table('posts')->insertReturning([
            'title' => $value,
            'language_code' => 'en',
        ])->first();
    });

    expect(
        collect(DB::select("SELECT * FROM posts WHERE title % 'steven'"))
        ->map(fn ($row) => $row->id)->toArray()
    )->toBe([1, 2, 3]);
});
