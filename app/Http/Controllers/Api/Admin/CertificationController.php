<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\TechnicianAsset;
use App\Notifications\CertificationReviewed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CertificationController extends Controller
{
    /**
     * List all certification assets, filterable by status, paginated.
     */
    public function index(Request $request)
    {
        $query = TechnicianAsset::certifications()
            ->with([
                'technician.user',
                'reviewer',
            ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $certifications = $query->latest()->paginate(15);

        return response()->json([
            'status' => 'success',
            'data'   => $certifications,
        ]);
    }

    /**
     * Show a single certification asset with full technician info.
     */
    public function show($id)
    {
        $asset = TechnicianAsset::with([
            'technician.user',
            'reviewer',
        ])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data'   => $asset,
        ]);
    }

    /**
     * Approve a certification asset.
     */
    public function approve(Request $request, $id)
    {
        $asset = TechnicianAsset::certifications()->findOrFail($id);

        $asset->update([
            'status'      => 'approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);

        // Notify the technician
        $technicianUser = optional($asset->technician)->user;
        if ($technicianUser) {
            $technicianUser->notify(new CertificationReviewed($asset));
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Certificación aprobada correctamente.',
            'data'    => $asset->fresh(['technician.user', 'reviewer']),
        ]);
    }

    /**
     * Reject a certification asset.
     */
    public function reject(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $asset = TechnicianAsset::certifications()->findOrFail($id);

        $asset->update([
            'status'           => 'rejected',
            'reviewed_by'      => $request->user()->id,
            'reviewed_at'      => now(),
            'rejection_reason' => $request->rejection_reason,
        ]);

        // Notify the technician
        $technicianUser = optional($asset->technician)->user;
        if ($technicianUser) {
            $technicianUser->notify(new CertificationReviewed($asset));
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Certificación rechazada correctamente.',
            'data'    => $asset->fresh(['technician.user', 'reviewer']),
        ]);
    }

    /* ─── Cédulas (id_document) ─────────────────────────────────── */

    /**
     * List all id_document assets (cédulas), filterable by status, paginated.
     */
    public function indexIdDocuments(Request $request)
    {
        $query = TechnicianAsset::idDocuments()
            ->with([
                'technician.user',
                'reviewer',
            ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $documents = $query->latest()->paginate(15);

        return response()->json([
            'status' => 'success',
            'data'   => $documents,
        ]);
    }

    /**
     * Approve a cédula (id_document asset).
     */
    public function approveIdDocument(Request $request, $id)
    {
        $asset = TechnicianAsset::idDocuments()->findOrFail($id);

        $asset->update([
            'status'           => 'approved',
            'reviewed_by'      => $request->user()->id,
            'reviewed_at'      => now(),
            'rejection_reason' => null,
        ]);

        // Notify the technician
        $technicianUser = optional($asset->technician)->user;
        if ($technicianUser) {
            $technicianUser->notify(new CertificationReviewed($asset));
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Cédula aprobada correctamente.',
            'data'    => $asset->fresh(['technician.user', 'reviewer']),
        ]);
    }

    /**
     * Reject a cédula (id_document asset).
     */
    public function rejectIdDocument(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $asset = TechnicianAsset::idDocuments()->findOrFail($id);

        $asset->update([
            'status'           => 'rejected',
            'reviewed_by'      => $request->user()->id,
            'reviewed_at'      => now(),
            'rejection_reason' => $request->rejection_reason,
        ]);

        // Notify the technician
        $technicianUser = optional($asset->technician)->user;
        if ($technicianUser) {
            $technicianUser->notify(new CertificationReviewed($asset));
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Cédula rechazada correctamente.',
            'data'    => $asset->fresh(['technician.user', 'reviewer']),
        ]);
    }
}
