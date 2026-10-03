<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Services\VolunteerCv;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Branded, multi-page PDF CV generated from the volunteer's profile. */
class CvController extends Controller
{
    public function pdf(Request $request, Profile $profile, VolunteerCv $cv)
    {
        $viewer = $request->user();
        $isOwn = $viewer && $viewer->id === $profile->user_id;

        abort_if((! $profile->is_public || $profile->user?->isSuspended()) && ! $isOwn && ! $viewer?->isAdmin(), 404);

        // Section picker on the profile page; default is every section.
        $requested = array_intersect((array) $request->query('sections', []), array_keys(VolunteerCv::SECTIONS));
        $sections = $requested ?: array_keys(VolunteerCv::SECTIONS);

        $html = view('cv.pdf', [
            'cv' => $cv->build($profile->user),
            'sections' => $sections,
            // Only the owner (or an admin) sees the email on their own CV unless it's public.
            'showEmail' => $isOwn || $viewer?->isAdmin() || $profile->show_email,
        ])->render();

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'Helvetica');
        $options->setChroot([public_path(), storage_path('app/public')]);

        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        $filename = Str::slug($profile->user->name).'-ieee-volunteer-cv.pdf';
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
