<?php

namespace App\Http\Controllers\dashboard;

use App\Http\Controllers\Controller;
use App\Models\dashboard\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;


class CategoryController extends Controller
{
    //
    public function index(): View
    {
        //$categories = Category::all();
        //$categories = Category::orderBy('id', 'desc')->get();
        //$categories = Category::withCount('articles')->orderBy('id', 'desc')->get();
        $categories = Category::withCount('articles')->orderBy('id', 'desc')->paginate(3);
        $categoryCount = Category::count();
        //$users = ['vahid','erfan','sara'];
        return view('dashboard.category', compact(['categories', 'categoryCount']));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:categories,name'],
            'slug' => ['required', 'string', 'max:50', 'unique:categories,slug'],
            'description' => ['nullable', 'string'],
            'icon' => ['required'],
        ], [
            'name.required' => 'برای دسته بندی نام انتخاب نمایید.',
            'name.max' => 'نام حداکثر می تواند شامل ۵۰ حرف باشد.',
            'name.unique' => 'این نام قبلا ثبت شده است نام دیگری انتخاب نمایید.',
            'slug.required' => 'برای دسته بندی شناسه لاتین انتخاب نمایید.',
            'slug.max' => 'نام حداکثر می تواند شامل ۵۰ حرف باشد.',
            'slug.unique' => 'شناسه ای با این نام قبلا ثبت شده است نام دیگری انتخاب نمایید.',
            'slug.slug' => 'فرمت شناسه صحیح نمی باشد اگر شناسه بیش از یک بخش دارد هر بخش را با ( - ) از هم جدا نمایید.',
            'icon.required' => 'برای دسته بندی آیکون انتخاب نمایید.'
        ]);
        //Category::create($fields);
        //$store = Category::create($request->all());

//        if($store){
//            return redirect()->back('/')->with('success','ok');
//        }
//        return redirect()->back('/')->witherrors('success','error');
        return Category::create($request->all()) ? redirect()->back()->with('success', 'دسته بندی مورد نظر شما با موفقیت ایجاد گردید') : redirect()->back()->with('error', 'خطایی در ثبت دسته بندی رخ داده است!');
    }

    public function edit(int $id): mixed
    {
        //$category = Category::findOrfail($id); //Fetch Category By ID
        $category = Category::find($id); //Fetch Category By ID
//     return view('dashboard.editCategory',compact(['category']));
        return $category ? view('dashboard.editCategory', compact(['category'])) : redirect()->route('category.index')->with('error', 'دسته بندی مورد نظر پیدا نشد!');
    }

    public function update(Request $request, $id) :RedirectResponse
    {
        $category = Category::findOrfail($id);
        $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'slug' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'icon' => ['required'],
        ], [
            'name.required' => 'برای دسته بندی نام انتخاب نمایید.',
            'name.max' => 'نام حداکثر می تواند شامل ۵۰ حرف باشد.',
            'slug.required' => 'برای دسته بندی شناسه لاتین انتخاب نمایید.',
            'slug.max' => 'نام حداکثر می تواند شامل ۵۰ حرف باشد.',
            'slug.slug' => 'فرمت شناسه صحیح نمی باشد اگر شناسه بیش از یک بخش دارد هر بخش را با ( - ) از هم جدا نمایید.',
            'icon.required' => 'برای دسته بندی آیکون انتخاب نمایید.'
        ]);
        $categoryUpdate = $category->update($request->all());
        return $categoryUpdate ? redirect()->route('category.index')->with('success', 'دسته بندی مورد نظر با موفقیت بروزرسانی گردید. ') :redirect()->route('category.edit')->with('error', 'خطایی در بروزرسانی دسته بندی مورد نظر رخ داده است! ');

    }

    public function destroy(int $id): RedirectResponse{
        $category = Category::findOrfail($id);
        //$category->articles()->delete();
        $categoryDestroy = $category->delete();
        return $categoryDestroy ? redirect()->back()->with('success', 'دسته بندی مورد نظر با موفقیت حذف  گردید. ') :redirect()->back()->with('error', 'خطایی در حذف دسته بندی مورد نظر رخ داده است! ');
    }

}
