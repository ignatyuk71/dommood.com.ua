<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomeFaqItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeFaqController extends Controller
{
    public function index(): Response
    {
        $items = HomeFaqItem::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (HomeFaqItem $item): array => [
                'id' => $item->id,
                'question' => $item->question,
                'answer' => $item->answer,
                'sort_order' => $item->sort_order,
                'is_active' => $item->is_active,
            ]);

        return Inertia::render('Admin/Content/Faq/Index', [
            'items' => $items,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:500'],
            'answer' => ['required', 'string', 'max:3000'],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        HomeFaqItem::create($data);

        return back()->with('success', 'Питання додано.');
    }

    public function update(Request $request, HomeFaqItem $faq): RedirectResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:500'],
            'answer' => ['required', 'string', 'max:3000'],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $faq->update($data);

        return back()->with('success', 'Збережено.');
    }

    public function destroy(HomeFaqItem $faq): RedirectResponse
    {
        $faq->delete();

        return back()->with('success', 'Видалено.');
    }
}
