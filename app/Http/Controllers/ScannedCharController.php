<?php

namespace App\Http\Controllers;

use App\Models\ScannedChar;
use Illuminate\Http\Request;

class ScannedCharController extends Controller
{
    public function index(Request $request)
    {
        $query = ScannedChar::query();

        // Search by name
        if ($request->filled('q')) {
            $query->where('name', 'like', '%' . $request->q . '%');
        }

        $chars = $query->orderBy('name')->paginate(100)->withQueryString();

        return view('scanned-chars.index', compact('chars'));
    }
}
