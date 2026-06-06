<?php
return [
    'http_get' => function(string $url): string {
        $ctx = stream_context_create(['http' => [
            'method' => 'GET',
            'timeout' => 10,
            'ignore_errors' => true,
        ]]);
        $result = @file_get_contents($url, false, $ctx);
        return $result === false ? '' : $result;
    },
    'http_post' => function(string $url, string $body, string $contentType = 'application/json'): string {
        $ctx = stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: {$contentType}\r\nContent-Length: " . strlen($body),
            'content' => $body,
            'timeout' => 10,
            'ignore_errors' => true,
        ]]);
        $result = @file_get_contents($url, false, $ctx);
        return $result === false ? '' : $result;
    },
];
