<?php

namespace App\Http\Controllers\dashboard;

use App\Http\Controllers\Controller;
use App\Models\dashboard\Article;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display dashboard metrics and the latest accessible articles.
     */
    public function index(): View
    {
        $query = Article::query()
            ->with(['author', 'category'])
            ->latest();

        if (auth()->user()->role === 'author') {
            $query->where('author_id', auth()->id());
        }

        $latestArticles = $query->limit(5)->get();

        return view('dashboard.index', compact('latestArticles'));
    }
}
