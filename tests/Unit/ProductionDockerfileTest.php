<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ProductionDockerfileTest extends TestCase
{
    public function test_production_dockerfile_exists(): void
    {
        $this->assertFileExists($this->projectPath('Dockerfile'));
    }

    public function test_production_dockerfile_builds_optimized_php_fpm_image(): void
    {
        $dockerfile = $this->fileContents('Dockerfile');

        $this->assertStringContainsString('FROM php:8.5-fpm-bookworm', $dockerfile);
        $this->assertStringContainsString('nginx', $dockerfile);
        $this->assertStringContainsString('supervisor', $dockerfile);
        $this->assertStringContainsString('composer install', $dockerfile);
        $this->assertStringContainsString('--no-dev', $dockerfile);
        $this->assertStringContainsString('npm run build', $dockerfile);
        $this->assertStringContainsString('HEALTHCHECK', $dockerfile);
        $this->assertStringContainsString('/up', $dockerfile);
        $this->assertStringContainsString('install-php-extensions', $dockerfile);
        $this->assertStringContainsString('redis', $dockerfile);
        $this->assertStringNotContainsString('pecl install', $dockerfile);
        $this->assertStringNotContainsString('COPY .env', $dockerfile);
    }

    public function test_dockerignore_keeps_secrets_and_dev_artifacts_out_of_the_image(): void
    {
        $dockerignore = $this->fileContents('.dockerignore');

        $this->assertStringContainsString('.env', $dockerignore);
        $this->assertStringContainsString('vendor', $dockerignore);
        $this->assertStringContainsString('node_modules', $dockerignore);
        $this->assertStringContainsString('tests', $dockerignore);
    }

    public function test_nginx_serves_the_public_front_controller(): void
    {
        $nginx = $this->fileContents('docker/nginx/default.conf');

        $this->assertStringContainsString('root /var/www/html/public;', $nginx);
        $this->assertStringContainsString('fastcgi_pass 127.0.0.1:9000;', $nginx);
        $this->assertStringContainsString('try_files $uri $uri/ /index.php?$query_string;', $nginx);
        $this->assertStringContainsString('deny all;', $nginx);
    }

    public function test_supervisor_runs_http_queue_and_scheduler(): void
    {
        $supervisor = $this->fileContents('docker/supervisor/supervisord.conf');

        $this->assertStringContainsString('[program:php-fpm]', $supervisor);
        $this->assertStringContainsString('[program:nginx]', $supervisor);
        $this->assertStringContainsString('queue:work', $supervisor);
        $this->assertStringContainsString('schedule:work', $supervisor);
    }

    public function test_entrypoint_optimizes_laravel_without_baking_secrets(): void
    {
        $entrypoint = $this->fileContents('docker/entrypoint.sh');

        $this->assertStringContainsString('APP_KEY', $entrypoint);
        $this->assertStringContainsString('php artisan config:cache', $entrypoint);
        $this->assertStringContainsString('php artisan event:cache', $entrypoint);
        $this->assertStringContainsString('php artisan view:cache', $entrypoint);
        $this->assertStringNotContainsString('php artisan optimize', $entrypoint);
        $this->assertStringNotContainsString('route:cache', $entrypoint);
        $this->assertStringContainsString('RUN_MIGRATIONS', $entrypoint);
        $this->assertStringContainsString('supervisord', $entrypoint);
    }

    private function projectPath(string $relativePath): string
    {
        return dirname(__DIR__, 2).'/'.$relativePath;
    }

    private function fileContents(string $relativePath): string
    {
        $path = $this->projectPath($relativePath);

        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
