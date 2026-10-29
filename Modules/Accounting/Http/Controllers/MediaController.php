<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Accounting\Entities\Media;
use Modules\Accounting\Services\FlashService;

class MediaController extends Controller
{
    public function download(Request $request, $id)
    {
        $request->validate([
            'file_name' => ['required'],
        ]);
        
        $media = Media::findOrFail($id); 

        return response()->download($media->display_path, $request->file_name.'.'.$media->extension);
    }

    public function delete(Request $request, $media_id)
    {
        $request->validate([
            'business_id' => ['required'],
        ]);

        try {
            Media::deleteMedia($request->business_id, $media_id);
        } catch (\Exception $e) {
            (new FlashService())->onException($e);
            return back();
        }
        
        (new FlashService())->onDelete();
        return back();
    }
}
