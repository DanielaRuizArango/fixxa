<?php

namespace App\Http\Controllers\Api\Technician;

use App\Http\Controllers\Controller;
use App\Models\TechnicianAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class TechnicianAssetController extends Controller
{
    /**
     * Display a listing of the assets for the authenticated technician.
     */
    public function index(Request $request)
    {
        $technician = $request->user()->technician;
        
        if (!$technician) {
            return response()->json(['message' => 'Profile not found'], 404);
        }

        $assets = $technician->assets;

        return response()->json([
            'status' => 'success',
            'data' => $assets
        ]);
    }

    /**
     * Store a newly created asset in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:tool,certification,work',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'description' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $technician = $request->user()->technician;

        if (!$technician) {
            return response()->json(['message' => 'Profile not found'], 404);
        }

        $path = $request->file('image')->store('technicians/assets', 'public');

        $asset = TechnicianAsset::create([
            'technician_id' => $technician->id,
            'type' => $request->type,
            'image_path' => $path,
            'description' => $request->description,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Asset uploaded successfully',
            'data' => $asset
        ], 201);
    }

    /**
     * Remove the specified asset from storage.
     */
    public function destroy(Request $request, $id)
    {
        $technician = $request->user()->technician;
        
        if (!$technician) {
            return response()->json(['message' => 'Profile not found'], 404);
        }

        $asset = TechnicianAsset::where('id', $id)
                                ->where('technician_id', $technician->id)
                                ->first();

        if (!$asset) {
            return response()->json(['message' => 'Asset not found or unauthorized'], 404);
        }

        Storage::disk('public')->delete($asset->image_path);
        $asset->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Asset deleted successfully'
        ]);
    }
}
