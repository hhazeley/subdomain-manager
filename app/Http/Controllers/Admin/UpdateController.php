<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ApplicationUpdater;
use App\Services\GitHubReleaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class UpdateController extends Controller
{
    public function index(GitHubReleaseService $releases): View
    {
        $latest = null;
        $error = null;

        try {
            $latest = $releases->latest();
        } catch (Throwable $exception) {
            report($exception);
            $error = $exception->getMessage();
        }

        return view('admin.updates.index', [
            'currentVersion' => (string) config('app.version'),
            'latest' => $latest,
            'error' => $error,
        ]);
    }

    public function install(
        Request $request,
        GitHubReleaseService $releases,
        ApplicationUpdater $updater,
    ): RedirectResponse {
        $validated = $request->validate([
            'version' => ['required', 'regex:/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/'],
        ]);

        try {
            $latest = $releases->latest();

            if ($latest->version !== $validated['version']) {
                return back()->withErrors([
                    'update' => 'The selected release is no longer the latest release. Review the update again.',
                ]);
            }

            $updater->install($latest);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['update' => $exception->getMessage()]);
        }

        return redirect()->route('admin.updates.index')
            ->with('success', "Subdomain Manager was updated to version {$latest->version}.");
    }
}
