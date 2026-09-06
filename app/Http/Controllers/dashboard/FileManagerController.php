<?php

namespace App\Http\Controllers\dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\dashboard\fileManager\FileManagerRequest;
use Illuminate\View\View;

class FileManagerController extends Controller
{
    //
    public function index() :View
    {
        return view('dashboard.file-manager');
    }

    public function store(FileManagerRequest $request)
    {

    }
}
