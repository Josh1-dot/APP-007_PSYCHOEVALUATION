<?php

namespace App\Http\Controllers;

use App\Services\Access;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BrandingController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->role === 'admin', 403);
        $request->validate(['logo' => 'required|file|mimes:jpg,jpeg|max:1024']);
        $file = $request->file('logo');
        $size = @getimagesize($file->getRealPath());
        abort_unless($size && $size[0] <= 2000 && $size[1] <= 2000, 422, 'Le logo doit être une image de 2 000 pixels maximum par côté.');

        $request->user()->tenant->update(['logo' => base64_encode(file_get_contents($file->getRealPath())), 'logo_mime' => $file->getMimeType()]);
        Access::audit('cabinet.logo_modifie', $request->user()->tenant);

        return back()->with('success', 'Logo enregistré pour vos documents PDF.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        abort_unless($request->user()->role === 'admin', 403);
        $request->user()->tenant->update(['logo' => null, 'logo_mime' => null]);
        Access::audit('cabinet.logo_retire', $request->user()->tenant);

        return back()->with('success', 'Logo retiré.');
    }
}
