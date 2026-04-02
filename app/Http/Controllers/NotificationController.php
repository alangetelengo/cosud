<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    /**
     * API : dernières notifications (pour le dropdown header).
     */
    public function recent(Request $request)
    {
        $user = $request->user();
        $notifications = $user->unreadNotifications()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($n) {
                $msg = $n->data['message'] ?? $n->data['message_title'] ?? $n->data['subject'] ?? class_basename($n->type);
                return [
                    'id' => $n->id,
                    'message' => is_string($msg) ? $msg : json_encode($msg),
                    'read_at' => $n->read_at?->toIso8601String(),
                    'created_at' => $n->created_at->format('d/m/Y H:i'),
                    'url' => route('notifications.read', $n->id),
                ];
            });

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Liste des notifications de l'utilisateur connecté.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $notifications = $user->notifications()->paginate(20)->withQueryString();

        return view('notifications.index', compact('notifications'));
    }

    /**
     * Marquer une notification comme lue et rediriger (comme Progcaisse).
     * ?back=1 : redirige vers la liste des notifications.
     */
    public function read(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->find($id);
        if ($notification) {
            $notification->markAsRead();
        }

        if ($request->query('back')) {
            return redirect()->route('notifications.index');
        }

        return redirect($notification?->data['url'] ?? route('notifications.index'));
    }

    /**
     * Marquer toutes les notifications comme lues.
     */
    public function markAllAsRead(Request $request)
    {
        $count = $request->user()->unreadNotifications->count();
        $request->user()->unreadNotifications->markAsRead();
        Log::channel('eged')->info('Toutes les notifications marquées comme lues', ['user_id' => $request->user()->id, 'count' => $count]);

        return back()->with('success', 'Toutes les notifications ont été marquées comme lues.');
    }
}
