<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class SettingsController extends Controller
{
    public function updateEnv(Request $request)
    {
        $request->validate([
            'portal_url' => 'required|url',
            'admin_id' => 'required|string',
            'admin_password' => 'required|string',
        ]);

        $baseUrl = rtrim((string) $request->portal_url, '/');

        $this->setEnvironmentValue([
            'PORTAL_URL' => $baseUrl,
            'PORTAL_EMAIL' => $request->admin_id,
            'PORTAL_PASSWORD' => '"' . str_replace('"', '\"', $request->admin_password) . '"',
        ]);

        \Illuminate\Support\Facades\Artisan::call('config:clear');

        \Illuminate\Support\Facades\Cache::forget('auto_sync_paused');
        \Illuminate\Support\Facades\Cache::forget('sre_consecutive_auth_failures');

        return redirect()->back()->with('success', 'Credentials updated successfully.');
    }

    private function setEnvironmentValue(array $values)
    {
        $envFile = app()->environmentFilePath();
        $str = File::get($envFile);

        // Normalize line endings
        $str = str_replace("\r\n", "\n", $str);
        $lines = explode("\n", $str);

        foreach ($values as $envKey => $envValue) {
            $found = false;
            foreach ($lines as &$line) {
                if (str_starts_with(trim($line), "{$envKey}=")) {
                    $line = "{$envKey}={$envValue}";
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $lines[] = "{$envKey}={$envValue}";
            }
        }

        File::put($envFile, implode("\n", $lines));
    }
}
