<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\SupportRequest;
use App\Services\SupportRequestChangeMarker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PublicClassroomDisplayController extends Controller
{
    public function show(string $slug, SupportRequestChangeMarker $changeMarker): View
    {
        $classroom = $this->publicClassroom($slug);
        $requests = $this->waitingRequests($classroom);

        return view('public-display.show', [
            'classroom' => $classroom,
            'requests' => $requests,
            'version' => $changeMarker->current($classroom),
        ]);
    }

    public function requests(string $slug, Request $request, SupportRequestChangeMarker $changeMarker): JsonResponse|Response
    {
        $classroom = $this->publicClassroom($slug);
        $currentVersion = $changeMarker->current($classroom);

        if ($request->integer('version') === $currentVersion) {
            return response()->noContent();
        }

        return response()->json([
            'version' => $currentVersion,
            'html' => view('public-display.partials.requests', [
                'requests' => $this->waitingRequests($classroom),
            ])->render(),
        ]);
    }

    private function publicClassroom(string $slug): Classroom
    {
        return Classroom::query()
            ->where('public_enabled', true)
            ->where('public_slug', $slug)
            ->firstOrFail();
    }

    private function waitingRequests(Classroom $classroom)
    {
        return SupportRequest::query()
            ->with('student:id,first_name,last_name')
            ->where('classroom_id', $classroom->id)
            ->where('status', SupportRequest::STATUS_WAITING)
            ->oldest('created_at')
            ->get();
    }
}
