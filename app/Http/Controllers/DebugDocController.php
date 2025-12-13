<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DebugDocController extends Controller
{
    /**
     * Probe storage to verify investigators folder can be created and written to
     */
    public function probe(): JsonResponse
    {
        $disk = Storage::disk('public');
        
        // Get disk root path from config
        $rootPath = config('filesystems.disks.public.root');
        
        // Define paths
        $folderPath = 'investigators';
        $filePath = 'investigators/__probe_route.txt';
        
        // Create investigators folder if not exists
        if (!$disk->exists($folderPath)) {
            $disk->makeDirectory($folderPath);
        }
        
        // Write probe file with timestamp
        $timestamp = now()->toDateTimeString();
        $content = "Probe timestamp: {$timestamp}\n";
        $content .= "Route: GET /debug/doc-probe\n";
        $content .= "Disk: public\n";
        $content .= "Root: {$rootPath}\n";
        
        $disk->put($filePath, $content);
        
        // Check if file exists
        $exists = $disk->exists($filePath);
        
        // Get additional info
        $fullPath = $disk->path($filePath);
        $fileSize = $exists ? $disk->size($filePath) : null;
        
        return response()->json([
            'success' => true,
            'root' => $rootPath,
            'path' => $filePath,
            'full_path' => $fullPath,
            'exists' => $exists,
            'file_size' => $fileSize,
            'timestamp' => $timestamp,
            'folder_exists' => $disk->exists($folderPath),
            'message' => $exists 
                ? 'Probe file created successfully' 
                : 'Failed to create probe file',
        ]);
    }
}
