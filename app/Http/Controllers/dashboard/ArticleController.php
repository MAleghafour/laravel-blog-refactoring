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
    public function store(StoreUpdateArticle $request,FileUploadService $FileUploadService)
    {
//dd($request);
        $article = $request->validated();
        /*$article->merge([
            'author_id'=>1
        ]);*/
        //$article['author_id'] = auth()->id();
        if($request->hasFile('thumbnail')){
            //Upload With Move Method
            $image = $request->file('thumbnail');
//            //dd($file);
//            $fileName = time().'.'. $file->getClientOriginalName();
//            $file->move(public_path('uploads'),$fileName);

            //Upload With Store Method
            //$file = $request->file('thumbnail');
            /*$imagePath = $file->store('images/thumbnail','public');*/
            //Upload With storeAs Method
            /*$fileName = time().'.'. $file->getClientOriginalName();
            $imagePath = $file->storeAs('images/thumbnail',$fileName,'public');
            $article['thumbnail'] = $imagePath;*/
            $thumbnails = $this->FileUploadService->fileUpload($image,false,true);
            //dd($article);
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
