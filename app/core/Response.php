<?php
declare(strict_types= 1);
namespace Martazi\core;
final class Response{
    public function html(string $content, int $status = 200): void{
        http_response_code($status);
        header('Content-Type: text/html; charset=UTF-8');
        echo $content;
    }

    public function json(array $data, int $status = 200): void{
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_THROW_ON_ERROR);
    }

    public function error(int $status, string $message = 'Page Not Found'): void{
        $this->html(
            '<h1>' . $status . '</h1><p>'
            .e($message) . 
            '</p>', $status
            );
    }
}