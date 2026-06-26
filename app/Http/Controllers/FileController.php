<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    //

    public function ViewFile(Request $request)
    {
        $request->validate([
            'drive' => 'required|string',
            'path' => 'required|string',
        ], [
            'drive.required' => 'drive is required to proceed',
            'path.required' => 'path is required to proceed',
        ]);

        try {

            $drives = [
                md5('profile') => 'JBLMGH_SIGN',
            ];

            if (!isset($drives[$request->drive])) {
                abort(404, 'Drive Not Found');
            }

            $SelectedDrive = $drives[$request->drive];
            $baseURL = config('app.profile_path');
            $fileURL = $baseURL . $SelectedDrive . '/' . ltrim($request->path, '/');

            $remoteResponse = Http::withOptions(['stream' => true])->get($fileURL);

            if ($remoteResponse->status() !== 200) {
                abort($remoteResponse->status(), 'File not found or inaccessible.');
            }

            return new StreamedResponse(function () use ($remoteResponse) {
                echo $remoteResponse->body();
            }, 200, [
                'Content-Type' => $remoteResponse->header('Content-Type') ?? 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="' . basename($fileURL) . '"',
            ]);
        } catch (\Exception $e) {
            abort(500, 'Error fetching file: ' . $e->getMessage());
        }
    }

    public function FetchImage(Request $request, $drive, $path)
    {

        try {

            if ($drive == null || $path == null) {
                return abort(404);
            }

            if (!Storage::disk($drive)->exists('')) {
                return abort(404, "Drive not found.");
            }

            $path = Crypt::decrypt($path);

            if (!Storage::disk($drive)->exists($path)) {
                return abort(404, 'File not Found');
            }

            // Get the file contents
            $fileContent = Storage::disk($drive)->get($path);

            // Determine the MIME type
            $mimeType = Storage::disk($drive)->mimeType($path);
            if (!$mimeType) {
                $mimeType = 'image/jpeg';
            }


            return response($fileContent, 200)
                ->header('Content-Type', $mimeType)
                ->header('Content-Disposition', 'inline; filename="' . basename($path) . '"');
        } catch (\Exception $e) {

            return abort(404, $e->getMessage());
        }
    }
}
