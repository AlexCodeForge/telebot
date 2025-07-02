<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PurchaseController extends Controller
{
    /**
     * Display a listing of all purchases.
     */
    public function index(Request $request)
    {
        $query = Purchase::with(['video', 'user'])
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->filled('purchase_status')) {
            $query->where('purchase_status', $request->purchase_status);
        }

        if ($request->filled('delivery_status')) {
            $query->where('delivery_status', $request->delivery_status);
        }

        if ($request->filled('verification_status')) {
            $query->where('verification_status', $request->verification_status);
        }

        // Search by username or email
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('telegram_username', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%")
                    ->orWhere('purchase_uuid', 'like', "%{$search}%");
            });
        }

        $purchases = $query->paginate(20);

        // Statistics
        $stats = [
            'total' => Purchase::count(),
            'completed' => Purchase::where('purchase_status', 'completed')->count(),
            'pending_verification' => Purchase::where('verification_status', 'pending')->count(),
            'pending_delivery' => Purchase::where('delivery_status', 'pending')->count(),
            'delivered' => Purchase::where('delivery_status', 'delivered')->count(),
            'failed_delivery' => Purchase::where('delivery_status', 'failed')->count(),
            'total_revenue' => Purchase::where('purchase_status', 'completed')->sum('amount'),
        ];

        return view('admin.purchases.index', compact('purchases', 'stats'));
    }

    /**
     * Show the details of a specific purchase.
     */
    public function show(Purchase $purchase)
    {
        $purchase->load(['video', 'user']);

        // If it's an AJAX request, return partial view for modal
        if (request()->ajax()) {
            return view('admin.purchases.show-modal', compact('purchase'));
        }

        // Otherwise return full page view
        return view('admin.purchases.show', compact('purchase'));
    }

    /**
     * Manually verify a purchase and link to telegram user.
     */
    public function verify(Request $request, Purchase $purchase)
    {
        $request->validate([
            'telegram_user_id' => 'required|string',
        ]);

        try {
            $purchase->verifyTelegramUser($request->telegram_user_id);

            Log::info('Purchase manually verified by admin', [
                'purchase_id' => $purchase->id,
                'purchase_uuid' => $purchase->purchase_uuid,
                'telegram_user_id' => $request->telegram_user_id,
                'admin_user' => Auth::user()->id ?? 'unknown',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Purchase verified successfully!',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to verify purchase', [
                'purchase_id' => $purchase->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to verify purchase: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Manually mark delivery as completed.
     */
    public function markDelivered(Request $request, Purchase $purchase)
    {
        $request->validate([
            'delivery_notes' => 'nullable|string|max:500',
        ]);

        try {
            $purchase->markAsDelivered([
                'manual_delivery' => true,
                'admin_user' => Auth::user()->id ?? 'unknown',
                'notes' => $request->delivery_notes,
            ]);

            Log::info('Purchase manually marked as delivered by admin', [
                'purchase_id' => $purchase->id,
                'purchase_uuid' => $purchase->purchase_uuid,
                'admin_user' => Auth::user()->id ?? 'unknown',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Purchase marked as delivered successfully!',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to mark purchase as delivered', [
                'purchase_id' => $purchase->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark as delivered: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Retry failed delivery.
     */
    public function retryDelivery(Purchase $purchase)
    {
        if (!$purchase->canRetryDelivery()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot retry delivery for this purchase.',
            ]);
        }

        try {
            $purchase->update([
                'delivery_status' => 'pending',
                'delivery_notes' => 'Retry requested by admin at ' . now(),
            ]);

            Log::info('Delivery retry initiated by admin', [
                'purchase_id' => $purchase->id,
                'purchase_uuid' => $purchase->purchase_uuid,
                'admin_user' => Auth::user()->id ?? 'unknown',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Delivery retry initiated successfully!',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retry delivery', [
                'purchase_id' => $purchase->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retry delivery: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Update purchase notes.
     */
    public function updateNotes(Request $request, Purchase $purchase)
    {
        $request->validate([
            'delivery_notes' => 'required|string|max:1000',
        ]);

        try {
            $purchase->update([
                'delivery_notes' => $request->delivery_notes,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notes updated successfully!',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update notes: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Update the telegram username for a purchase (admin access)
     */
    public function updateTelegramUsername(Request $request, Purchase $purchase)
    {
        $request->validate([
            'telegram_username' => 'required|string|max:255|regex:/^[a-zA-Z0-9_]+$/',
        ]);

        // Clean the username (remove @ if present)
        $username = ltrim($request->telegram_username, '@');
        $oldUsername = $purchase->telegram_username;

        try {
            $purchase->update([
                'telegram_username' => $username,
            ]);

            Log::info('Admin updated purchase telegram username', [
                'purchase_id' => $purchase->id,
                'purchase_uuid' => $purchase->purchase_uuid,
                'old_username' => $oldUsername,
                'new_username' => $username,
                'admin_user' => Auth::user()->id ?? 'unknown',
            ]);

            return response()->json([
                'success' => true,
                'username' => $username,
                'message' => 'Telegram username updated successfully!'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update telegram username via admin', [
                'purchase_id' => $purchase->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update username: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Get purchase statistics for dashboard.
     */
    public function stats()
    {
        $today = now()->startOfDay();
        $week = now()->subWeek();
        $month = now()->subMonth();

        $stats = [
            'today' => [
                'purchases' => Purchase::where('created_at', '>=', $today)->count(),
                'revenue' => Purchase::where('created_at', '>=', $today)
                    ->where('purchase_status', 'completed')->sum('amount'),
            ],
            'week' => [
                'purchases' => Purchase::where('created_at', '>=', $week)->count(),
                'revenue' => Purchase::where('created_at', '>=', $week)
                    ->where('purchase_status', 'completed')->sum('amount'),
            ],
            'month' => [
                'purchases' => Purchase::where('created_at', '>=', $month)->count(),
                'revenue' => Purchase::where('created_at', '>=', $month)
                    ->where('purchase_status', 'completed')->sum('amount'),
            ],
            'pending_actions' => [
                'verification' => Purchase::where('verification_status', 'pending')->count(),
                'delivery' => Purchase::where('delivery_status', 'failed')->count(),
            ]
        ];

        return response()->json($stats);
    }

    /**
     * Fix stuck deliveries (admin tool)
     */
    public function fixStuckDeliveries(Request $request)
    {
        try {
            // Find purchases that are verified but still showing as pending delivery
            $stuckPurchases = Purchase::where('verification_status', 'verified')
                ->where('delivery_status', 'pending')
                ->whereNotNull('telegram_user_id')
                ->where('purchase_status', 'completed')
                ->get();

            $fixed = 0;
            $errors = [];

            foreach ($stuckPurchases as $purchase) {
                try {
                    // If purchase is verified and has telegram_user_id, mark as delivered
                    if ($purchase->telegram_user_id && $purchase->verification_status === 'verified') {
                        $purchase->markAsDelivered([
                            'admin_fix' => true,
                            'fixed_by_admin' => Auth::user()->id,
                            'fix_timestamp' => now()->toISOString(),
                            'fix_reason' => 'Auto-fixed stuck delivery status',
                        ]);
                        $fixed++;
                    }
                } catch (\Exception $e) {
                    $errors[] = "Purchase {$purchase->id}: " . $e->getMessage();
                }
            }

            Log::info('Admin fixed stuck deliveries', [
                'admin_user' => Auth::user()->id,
                'fixed_count' => $fixed,
                'errors' => $errors,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Fixed {$fixed} stuck deliveries.",
                'fixed_count' => $fixed,
                'errors' => $errors,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fix stuck deliveries', [
                'admin_user' => Auth::user()->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fix deliveries: ' . $e->getMessage(),
            ]);
        }
    }
}
