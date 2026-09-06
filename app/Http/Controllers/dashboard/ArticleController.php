<?php

namespace App\Http\Controllers\dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\dashboard\article\StoreUpdateArticle;
use App\Models\dashboard\Article;
use App\Models\dashboard\Category;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ArticleController extends Controller
{
    protected FileUploadService $FileUploadService;
    public function __construct(FileUploadService $FileUploadService){
         $this->FileUploadService = $FileUploadService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        echo 'Articles All';
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        //
        $categories = Category::all();
        return view('dashboard.create-article', compact('categories'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUpdateArticle $request)
    {
        $article = $request->validated();
        if($request->hasFile('thumbnail')){
            $image = $request->file('thumbnail');
            $thumbnails = $this->FileUploadService->fileUpload($image,false,true);
            $article = array_merge($article,$thumbnails);
        }

        $article = Article::create($article);
        return $article ? redirect()->route('article.index')->with('success','مقاله شما با موفقیت ثبت شد') : redirect()->route('article.create')->with('error','خطایی در ثبت مقاله رخ داده است!');

    }

    /**
     * Display the specified resource.
     */
    public function show(Article $article)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Article $article)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Article $article)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Article $article)
    {
        //
    }
}
