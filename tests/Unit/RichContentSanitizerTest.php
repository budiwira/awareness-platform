<?php

use App\Support\RichContentSanitizer;

test('script tags dilucuti', function () {
    $out = RichContentSanitizer::clean('<p>Halo</p><script>alert(1)</script>');
    expect($out)->not->toContain('<script>');
    expect($out)->toContain('Halo');
});

test('event handler on* dilucuti', function () {
    $out = RichContentSanitizer::clean('<img src="https://example.com/x.png" onerror="alert(1)">');
    expect($out)->not->toContain('onerror');
});

test('javascript uri dilucuti', function () {
    $out = RichContentSanitizer::clean('<a href="javascript:alert(1)">klik</a>');
    expect($out)->not->toContain('javascript:');
});

test('iframe youtube diizinkan', function () {
    $out = RichContentSanitizer::clean('<iframe src="https://www.youtube.com/embed/abc123"></iframe>');
    expect($out)->toContain('iframe');
    expect($out)->toContain('youtube.com/embed/abc123');
});

test('iframe vimeo diizinkan', function () {
    $out = RichContentSanitizer::clean('<iframe src="https://player.vimeo.com/video/123456"></iframe>');
    expect($out)->toContain('player.vimeo.com/video/123456');
});

test('iframe domain liar ditolak', function () {
    $out = RichContentSanitizer::clean('<iframe src="https://evil.example.com/x"></iframe>');
    expect($out)->not->toContain('evil.example.com');
});

test('format word dasar dipertahankan', function () {
    $html = '<h2>Judul</h2><p>Teks <strong>tebal</strong> <em>miring</em> <u>garis</u></p><ul><li>item 1</li><li>item 2</li></ul><ol><li>pertama</li></ol>';
    $out = RichContentSanitizer::clean($html);
    expect($out)->toContain('<h2>')
        ->and($out)->toContain('<strong>')
        ->and($out)->toContain('<em>')
        ->and($out)->toContain('<li>')
        ->and($out)->toContain('<ol>');
});
