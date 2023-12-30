<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ResourcesController extends Controller
{
    public function ping(){
        return response()->json(true,200);
    }
}
