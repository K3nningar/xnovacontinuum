<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Response;

class AnnouncementController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): Response
    {
        return inertia('Announcements/Index', [
            'announcements' => DB::table('admin_announcements')
                ->join('admin_announcement_recipients', 'admin_announcement_recipients.announcement_id', '=', 'admin_announcements.id')
                ->where('admin_announcement_recipients.user_id', $request->user()->id)
                ->whereNull('admin_announcements.revoked_at')
                ->orderByDesc('admin_announcements.id')
                ->paginate(20, ['admin_announcements.id', 'admin_announcements.body', 'admin_announcements.created_at']),
        ]);
    }
}
