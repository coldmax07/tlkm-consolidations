<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransactionTemplateRequest;
use App\Http\Requests\UpdateTransactionTemplateRequest;
use App\Http\Resources\TransactionTemplateResource;
use App\Models\TransactionTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionTemplateController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', TransactionTemplate::class);

        $perPage = max(5, min((int) $request->input('per_page', 15), 100));
        $sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortBy = $request->input('sort_by', 'created_at');

        $query = TransactionTemplate::query()
            ->with([
                'financialStatement',
                'senderCompany',
                'receiverCompany',
                'senderCategory',
                'receiverCategory',
                'senderAccount',
                'receiverAccount',
            ])
            ->select('transaction_templates.*');

        if ($request->filled('financial_statement_id')) {
            $query->where('financial_statement_id', $request->integer('financial_statement_id'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('sender_company_id')) {
            $query->where('sender_company_id', $request->integer('sender_company_id'));
        }

        if ($request->filled('receiver_company_id')) {
            $query->where('receiver_company_id', $request->integer('receiver_company_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($inner) use ($search) {
                $inner->where('description', 'like', '%' . $search . '%')
                    ->orWhereHas('senderCompany', fn ($q) => $q->where('name', 'like', '%' . $search . '%'))
                    ->orWhereHas('receiverCompany', fn ($q) => $q->where('name', 'like', '%' . $search . '%'))
                    ->orWhereHas('senderAccount', fn ($q) => $q->where('name', 'like', '%' . $search . '%'))
                    ->orWhereHas('receiverAccount', fn ($q) => $q->where('name', 'like', '%' . $search . '%'));
            });
        }

        $this->applySorting($query, $sortBy, $sortDir);

        $templates = $query->paginate($perPage);

        return TransactionTemplateResource::collection($templates);
    }

    public function store(StoreTransactionTemplateRequest $request)
    {
        $template = TransactionTemplate::create($request->validated());

        return new TransactionTemplateResource(
            $template->load([
                'financialStatement',
                'senderCompany',
                'receiverCompany',
                'senderCategory',
                'receiverCategory',
                'senderAccount',
                'receiverAccount',
            ])
        );
    }

    public function show(TransactionTemplate $template)
    {
        $this->authorize('view', $template);

        return new TransactionTemplateResource(
            $template->load([
                'financialStatement',
                'senderCompany',
                'receiverCompany',
                'senderCategory',
                'receiverCategory',
                'senderAccount',
                'receiverAccount',
            ])
        );
    }

    public function update(UpdateTransactionTemplateRequest $request, TransactionTemplate $template)
    {
        $template->update($request->validated());

        return new TransactionTemplateResource(
            $template->load([
                'financialStatement',
                'senderCompany',
                'receiverCompany',
                'senderCategory',
                'receiverCategory',
                'senderAccount',
                'receiverAccount',
            ])
        );
    }

    public function destroy(TransactionTemplate $template)
    {
        $this->authorize('delete', $template);

        $template->delete();

        return response()->noContent();
    }

    protected function applySorting($query, string $sortBy, string $sortDir): void
    {
        $dir = $sortDir === 'asc' ? 'asc' : 'desc';

        $sorts = [
            'created_at' => fn () => $query->orderBy('created_at', $dir),
            'updated_at' => fn () => $query->orderBy('updated_at', $dir),
            'description' => fn () => $query->orderBy('description', $dir),
            'currency' => fn () => $query->orderBy('currency', $dir),
            'default_amount' => fn () => $query->orderBy('default_amount', $dir),
            'is_active' => fn () => $query->orderBy('is_active', $dir),
            'financial_statement' => fn () => $query->orderBy(
                DB::table('financial_statements')
                    ->select('display_label')
                    ->whereColumn('financial_statements.id', 'transaction_templates.financial_statement_id'),
                $dir
            ),
            'sender_company' => fn () => $query->orderBy(
                DB::table('companies')->select('name')->whereColumn('companies.id', 'transaction_templates.sender_company_id'),
                $dir
            ),
            'receiver_company' => fn () => $query->orderBy(
                DB::table('companies')->select('name')->whereColumn('companies.id', 'transaction_templates.receiver_company_id'),
                $dir
            ),
        ];

        ($sorts[$sortBy] ?? $sorts['created_at'])();
    }
}
