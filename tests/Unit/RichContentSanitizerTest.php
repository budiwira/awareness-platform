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

test('external image HTTPS dipertahankan untuk kompatibilitas', function () {
    $out = RichContentSanitizer::clean('<img src="https://cdn.example.com/lesson.png" alt="Materi">');

    expect($out)->toContain('https://cdn.example.com/lesson.png')
        ->toContain('alt="Materi"');
});

test('image URI executable dilucuti', function (string $uri) {
    $out = RichContentSanitizer::clean('<img src="'.$uri.'" alt="Tidak aman">');

    expect($out)->not->toContain($uri);
})->with([
    'javascript:alert(1)',
    'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==',
]);

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

test('iframe nocookie dipertahankan tanpa atribut berbahaya', function () {
    $url = 'https://www.youtube-nocookie.com/embed/abc123?rel=0';
    $out = RichContentSanitizer::clean('<iframe src="'.$url.'" width="640" height="360" allow="camera; microphone" allowfullscreen onload="alert(1)" srcdoc="unsafe"></iframe>');
    expect($out)->toContain($url)->toContain('width="640"')
        ->not->toContain('allow=')->not->toContain('allowfullscreen')
        ->not->toContain('onload')->not->toContain('srcdoc');
});

test('iframe hanya menerima URL embed HTTPS yang diizinkan', function ($url) {
    $out = RichContentSanitizer::clean('<iframe src="'.$url.'"></iframe>');
    expect($out)->not->toContain('<iframe');
})->with([
    'http://www.youtube.com/embed/abc123',
    '//www.youtube.com/embed/abc123',
    'https://www.youtube.com.evil.test/embed/abc123',
    'https://www.youtube.com@evil.test/embed/abc123',
    'https://www.youtube.com/watch?v=abc123',
    'https://www.youtube-nocookie.com/watch?v=abc123',
    'https://vimeo.com/123456',
    'https://player.vimeo.com/video/not-a-number',
    'https://www.youtube.com/embed/',
    'https://www.youtube.com/embed/abc123/extra',
    'javascript:alert(1)',
]);

test('paragraf kosong Quill berurutan dinormalisasi tanpa menghapus konten bermakna', function () {
    $out = RichContentSanitizer::clean('<p>Awal</p><p><br></p><p><br></p><p>Berikutnya</p>');

    expect($out)->toContain('<p>Awal</p>')
        ->toContain('<p>Berikutnya</p>')
        ->and(substr_count($out, '<p><br'))
        ->toBeLessThanOrEqual(1);
});

test('normalisasi paragraf kosong tidak merusak image atau iframe', function () {
    $out = RichContentSanitizer::clean(
        '<p><br></p><p><br></p><img src="/platform/media/lesson.png" alt="Materi">'.
        '<iframe src="https://www.youtube.com/embed/abc123"></iframe>'
    );

    expect($out)->toContain('/platform/media/lesson.png')
        ->toContain('https://www.youtube.com/embed/abc123');
});
