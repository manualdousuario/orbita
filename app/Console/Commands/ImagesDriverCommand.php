<?php

namespace App\Console\Commands;

use App\Support\ImageDriver;
use Illuminate\Console\Command;
use Intervention\Image\Drivers\Vips\Driver;

/**
 * Reports whether libvips is available and active, or if GD fallback is used.
 */
class ImagesDriverCommand extends Command
{
    protected $signature = 'orbita:images-driver';

    protected $description = 'Mostra se o libvips está disponível e em uso, ou se o fallback GD está ativo';

    public function handle(): int
    {
        $active = ImageDriver::active();
        $vipsAvailable = ImageDriver::vipsAvailable();

        $this->table(['Item', 'Valor'], [
            ['driver ativo', $active],
            ['vips utilizável', $vipsAvailable ? 'sim' : 'não'],
            ['  pacote do driver vips', class_exists(Driver::class) ? 'instalado' : 'ausente'],
            ['  ext-ffi carregada', extension_loaded('FFI') ? 'sim' : 'não'],
            ['  ffi.enable', (string) ini_get('ffi.enable')],
            ['  libvips (lib nativa)', ImageDriver::vipsVersion() ?? 'não carrega'],
            ['imagick disponível', ImageDriver::imagickAvailable() ? 'sim' : 'não'],
            ['GD com AVIF', function_exists('imageavif') ? 'sim' : 'não'],
            ['GD com WebP', function_exists('imagewebp') ? 'sim' : 'não'],
        ]);

        if (! $vipsAvailable) {
            $this->error('libvips não está utilizável — o fallback GD está ativo.');

            if (! class_exists(Driver::class)) {
                $this->line('  → instale intervention/image-driver-vips.');
            } elseif (! extension_loaded('FFI')) {
                $this->line('  → ext-ffi não está carregada.');
            } elseif (! filter_var(ini_get('ffi.enable'), FILTER_VALIDATE_BOOL)) {
                $this->line('  → ffi.enable está desligado (o padrão "preload" não vale para CLI/FPM). Defina ffi.enable=true.');
            } else {
                $this->line('  → a biblioteca nativa libvips não carrega (instale libvips42 / coloque a DLL no PATH).');
            }

            return self::FAILURE;
        }

        $this->info("Driver de imagem ativo: {$active}");

        return self::SUCCESS;
    }
}
