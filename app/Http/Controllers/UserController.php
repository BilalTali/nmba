<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Block;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('block')->latest()->paginate(50);
        return Inertia::render('Admin/BlockUsers/Index', ['users' => $users]);
    }

    public function create()
    {
        $blocks = Block::orderBy('name')->get(['id', 'name']);
        return Inertia::render('Admin/BlockUsers/Create', ['blocks' => $blocks]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'block_id' => 'required|exists:blocks,id',
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => 'Welcome@123', // Will be hashed via mutator
            'role' => 'block_worker',
            'block_id' => $request->block_id,
        ]);

        return redirect()->route('users.index')->with('success', 'Block user created.');
    }
}
