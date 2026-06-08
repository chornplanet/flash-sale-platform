<?php

use Illuminate\Support\Facades\Route;
use League\CommonMark\GithubFlavoredMarkdownConverter;

Route::get('/', function () {
    $markdown = file_get_contents(base_path('README.md'));
    $markdown = str_replace('](.docs/', '](readme-assets/', $markdown);
    $toc = [];
    $usedSlugs = [];

    $markdown = preg_replace_callback('/^(#{1,3})\s+(.+)$/m', function (array $matches) use (&$toc, &$usedSlugs) {
        $level = strlen($matches[1]);
        $title = trim($matches[2]);
        $plainTitle = trim(strip_tags(preg_replace('/[`*_~\[\]\(\)]/', '', $title)));
        $baseSlug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $plainTitle));
        $baseSlug = trim($baseSlug, '-') ?: 'section';
        $slug = $baseSlug;
        $counter = 2;

        while (isset($usedSlugs[$slug])) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        $usedSlugs[$slug] = true;

        $toc[] = [
            'level' => $level,
            'title' => $plainTitle,
            'slug' => $slug,
        ];

        return sprintf('<h%d id="%s">%s</h%d>', $level, $slug, e($plainTitle), $level);
    }, $markdown);

    $converter = new GithubFlavoredMarkdownConverter([
        'html_input' => 'allow',
        'allow_unsafe_links' => false,
    ]);

    return view('welcome', [
        'readmeHtml' => $converter->convert($markdown)->getContent(),
        'toc' => $toc,
    ]);
})->name('home');

Route::redirect('/readme', '/')->name('readme');

Route::get('/welcome.css', function () {
    return response()->file(resource_path('css/welcome.css'), [
        'Content-Type' => 'text/css; charset=UTF-8',
    ]);
})->name('welcome.styles');

Route::get('/readme-assets/{path}', function (string $path) {
    return response()->file(base_path('.docs/'.$path));
})->where('path', '.*')->name('readme.assets');
