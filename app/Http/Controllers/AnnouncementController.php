<?php

/*
 * Author / Contributor : K3nningar
 * Code Version : 0.1.0-alpha.4
 * Revision : 34ddc84
 * Description : Handles HTTP requests for AnnouncementController operations.
 */

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Response;

class AnnouncementController extends Controller
{
    /**
     * Lists only non-revoked announcements addressed to the authenticated user, newest first.
     *
     * Pagination is scoped through the recipient join, so users cannot see announcements for other recipients.
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
