<?php

namespace App\Http\Controllers;

use App\Http\Requests\TagRequest;
use App\Models\Tag;
use App\Services\TagService;
use Illuminate\Http\RedirectResponse;

class TagController extends Controller
{
    public function update(TagRequest $request, Tag $tag, TagService $tags): RedirectResponse
    {
        $tags->rename($tag, $request->validated('name'));

        return back();
    }

    public function destroy(Tag $tag, TagService $tags): RedirectResponse
    {
        $tags->delete($tag);

        return back();
    }
}
