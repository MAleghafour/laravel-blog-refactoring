<?php

use App\Http\Controllers\Auth\SocialLoginController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\dashboard\ArticleController;
use App\Http\Controllers\dashboard\CategoryController;
use App\Http\Controllers\dashboard\FileManagerController;
use Illuminate\Support\Facades\Route;


//Front Routes
Route::group([],function ()
{
    Route::view('/','front.index')->name('home');
    Route::get('/single', function () {
        return view('front.single');
    });

    Route::get('/archive', function () {
        return view('front.archive');
    });
    Route::get('/author', function () {
        return view('front.author');
    });
    Route::get('/offline', function () {
        return view('front.offline');
    });
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

//Dashboard Routes
Route::prefix('/dashboard')->middleware('auth')->group(function(){
    Route::middleware('role:admin')->group(function (){
        Route::get('/article/category',[CategoryController::class,'index'])->name('category.index');
        Route::post('/article/category',[CategoryController::class,'store']);
        Route::get('/article/category/edit/{id}',[CategoryController::class,'edit'])->name('category.edit');
        Route::put('/article/category/edit/{id}',[CategoryController::class,'update'])->name('category.update');
        Route::delete('/article/category/{id}',[CategoryController::class,'destroy'])->name('category.destroy');

   /* Route::resource('article',ArticleController::class,[
        'parameters'=>['article'=>'id'],
        'only'=>['index','show']
    ]);*/
        Route::get('/news/comments',function (){
            return view ('dashboard.comments');
        });
        Route::get('/users',function (){
            return view ('dashboard.users');
        });

        // Article review queue
        Route::get('article/review-queue', [
            ArticleController::class, 'reviewQueue'
        ])->name('article.review-queue');

        // Review decisions
        Route::post('article/{article}/approve', [
            ArticleController::class, 'approve'
        ])->name('article.approve');

        Route::post('article/{article}/return', [
            ArticleController::class, 'returnForCorrection'
        ])->name('article.return');

        // Permanent deletion
        Route::delete('article/{article}/force-delete', [
            ArticleController::class, 'forceDelete'
        ])
            ->withTrashed()
            ->name('article.force-delete');

    });

    Route::middleware('role:author|admin')->group(function (){
        Route::get('/',function (){
            return view ('dashboard.index');
        });
        Route::get('/news',function (){
            return view ('dashboard.allNews');
        });

        // Article management lists
        Route::get('article/trash', [
            ArticleController::class, 'trash'
        ])->name('article.trash');

        // Author lifecycle actions
        Route::post('article/{article}/submit', [
            ArticleController::class, 'submitForReview'
        ])->name('article.submit');

        Route::post('article/{article}/withdraw', [
            ArticleController::class, 'withdraw'
        ])->name('article.withdraw');

        // Restore soft-deleted articles
        Route::post('article/{article}/restore', [
            ArticleController::class, 'restore'
        ])
            ->withTrashed()
            ->name('article.restore');

        // Standard Article CRUD
        Route::resource('article', ArticleController::class);

        Route::get('/news/edit',function (){
                return view ('dashboard.editNews');
            });
        Route::get('/profile',function (){
            return view ('dashboard.editNews');
        });
            Route::get('/file-manager',[FileManagerController::class,'index'])->name('file-manager.index');
            Route::post('/file-manager',[FileManagerController::class,'store'])->name('file-manager.store');
    });

});
//AUth Routes
//Front Auth Routes
Route::prefix('/auth')->middleware('guest')->group(function() {
    Route::view('/login', 'auth.login')->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::view('/register', 'auth.register')->middleware('guest')->name('register');
    Route::post('/register', [AuthController::class, 'register']);
//Reset Password
    Route::view('/forgot-password', 'auth.forgot-password')->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetPasswordLink']);
    Route::get('/reset-password/{token}', [AuthController::class, 'resetPasswordToken'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'passwordUpdate'])->name('password.update');

//Auth Social Login-Register

    Route::get('/{driver}/redirect', [SocialLoginController::class, 'redirect'])->name('auth.redirect');
    Route::get('/{driver}/callback', [SocialLoginController::class, 'callback'])->name('auth.callback');
});
