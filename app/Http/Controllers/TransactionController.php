<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $sortable = ['created_at', 'total'];
        if (in_array($request->query('sort'), $sortable, true)) {
            $sort = $request->query('sort');
            $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';
        } else {
            $sort = 'created_at';
            $direction = 'desc';
        }

        return TransactionResource::collection(
            Transaction::query()
                ->with('items.product', 'user')
                ->orderBy($sort, $direction)
                ->paginate(15)
                ->withQueryString()
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTransactionRequest $request)
    {
        $transaction = Transaction::createFromItems($request->validated('items'), $request->user());

        return TransactionResource::make($transaction)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     */
    public function show(Transaction $transaction)
    {
        return TransactionResource::make($transaction->load('items.product', 'user'));
    }
}
