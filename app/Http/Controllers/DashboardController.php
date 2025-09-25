<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        /** @var LengthAwarePaginator $documents */
        $documents = Document::query()
            ->where('user_id', $user->id)
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('dashboard', [
            'documents' => $documents,
        ]);
    }
}


