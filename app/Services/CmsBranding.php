<?php

namespace App\Services;

use App\System;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CmsBranding
{
    public const ASSETS = ['logo', 'compact_logo', 'favicon'];

    public function settings(): array
    {
        $stored = System::getProperty('cms_branding');

        return $stored === null ? [] : json_decode($stored, true, 512, JSON_THROW_ON_ERROR);
    }

    public function apply(): void
    {
        $settings = $this->settings();
        config([
            'app.name' => $settings['name'] ?? config('app.name'),
            'app.title' => $settings['title'] ?? config('app.title'),
            'branding.footer' => $settings['footer'] ?? null,
            'branding.logo' => isset($settings['logo']) ? asset($settings['logo']) : null,
            'branding.compact_logo' => isset($settings['compact_logo']) ? asset($settings['compact_logo']) : null,
            'branding.favicon' => isset($settings['favicon']) ? asset($settings['favicon']) : null,
        ]);
    }

    public function save(Request $request): void
    {
        $created = [];
        $replaced = [];

        try {
            DB::transaction(function () use ($request, &$created, &$replaced) {
                $row = System::where('key', 'cms_branding')->lockForUpdate()->first();
                $settings = $row ? json_decode($row->value, true, 512, JSON_THROW_ON_ERROR) : [];
                $settings['name'] = $request->input('name');
                $settings['title'] = $request->input('title') ?? '';
                $settings['footer'] = $request->input('footer') ?? '';

                foreach (self::ASSETS as $key) {
                    $previous = $settings[$key] ?? null;
                    if ($request->boolean('remove_'.$key)) {
                        unset($settings[$key]);
                    }
                    if ($request->hasFile($key)) {
                        $file = $request->file($key);
                        $directory = public_path('uploads/branding');
                        File::ensureDirectoryExists($directory);
                        $filename = Str::uuid().'.'.$file->extension();
                        $file->move($directory, $filename);
                        $settings[$key] = 'uploads/branding/'.$filename;
                        $created[] = $settings[$key];
                    }
                    if ($previous !== ($settings[$key] ?? null) && $previous !== null) {
                        $replaced[] = $previous;
                    }
                }

                System::updateOrCreate(['key' => 'cms_branding'], [
                    'value' => json_encode($settings, JSON_THROW_ON_ERROR),
                ]);
            });
        } catch (\Throwable $e) {
            $this->deleteAssets($created);
            throw $e;
        }

        $this->deleteAssets($replaced);
    }

    private function deleteAssets(array $paths): void
    {
        foreach ($paths as $path) {
            if (preg_match('~^uploads/branding/[a-f0-9-]+\.(png|jpe?g|webp|ico)$~D', $path)
                && File::exists(public_path($path)) && !File::delete(public_path($path))) {
                Log::warning('Unable to remove replaced CMS branding asset.', ['path' => $path]);
            }
        }
    }
}
