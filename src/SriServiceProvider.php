<?php

namespace Elhebert\SubresourceIntegrity;

use Illuminate\Support\Str;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class SriServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(Sri::class, function () {
            return new Sri(config('subresource-integrity.algorithm'));
        });

        $this->app->alias(Sri::class, 'sri');

        $this->mergeConfigFrom(
            __DIR__.'/../config/subresource-integrity.php',
            'subresource-integrity'
        );
    }

    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/subresource-integrity.php' => config_path('subresource-integrity.php'),
        ]);

        Blade::directive('mixSri', function (string $path, bool $crossOrigin = false, string $execute = null) {
            $path = $this->removeQuotes($path);
            $execute = $this->removeQuotes($execute);

            if (Str::startsWith($path, ['http', 'https', '//'])) {
                $href = $path;
            } else {
                $href = mix($path);
            }

            return $this->parseAndGenerateUrl($path, $href, $crossOrigin, $execute);
        });

        Blade::directive('assetSri', function (string $path, bool $crossOrigin = false, string $execute = null) {
            $path = $this->removeQuotes($path);
            $execute = $this->removeQuotes($execute);

            if (Str::startsWith($path, ['http', 'https', '//'])) {
                $href = $path;
            } else {
                $href = asset($path);
            }

            return $this->parseAndGenerateUrl($path, $href, $crossOrigin, $execute);
        });
    }

    private function removeQuotes(string $path): string
    {
        $values = ['\'', '"'];

        return str_replace($values, '', $path);
    }

    private function parseAndGenerateUrl(string $path, string $href, bool $crossOrigin, string $execute): HtmlString
    {
        $integrity = SriFacade::html($href, $crossOrigin);

        if (Str::endsWith($path, 'css')) {
            return $this->generateCssUrl($href, $integrity);
        } elseif (Str::endsWith($path, 'js')) {
            return $this->generateJsUrl($href, $integrity, $execute);
        } else {
            throw new \Exception('Invalid file');
        }
    }

    private function generateJsUrl(string $href, string $integrity, string $execute): HtmlString
    {
        if (isNull($execute)) {
            return new HtmlString('<script src="{$href}" {$integrity}></script>');
        }

        return new HtmlString('<script src="{$href}" {$integrity} {$execute}></script>');
    }

    private function generateCssUrl(string $href, string $integrity): HtmlString
    {
        return new HtmlString('<link href="{$href}" rel="stylesheet" {$integrity}>');
    }
}
