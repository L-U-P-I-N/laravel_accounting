@php
    $margin = min($level * 30, 300);
    $canManageAccounts = $canManageAccounts ?? auth()->user()->hasPermission('manage_accounts');
    $typeText = match ($account->account_type) {
        'asset' => 'أصول',
        'liability' => 'خصوم',
        'equity' => 'ملكية',
        'revenue' => 'إيرادات',
        'expense' => 'مصروفات',
        default => 'تكلفة',
    };
@endphp

<div class="tree-node" style="margin-right: {{ $margin }}px;">
    <div class="account-item {{ $account->children->isNotEmpty() ? 'parent' : '' }}">
        <div class="row align-items-center">
            <div class="col-md-4 mb-3 mb-md-0">
                <div class="d-flex align-items-center">
                    @if ($account->children->isNotEmpty())
                        <i class="fas fa-chevron-down ms-2 text-primary"></i>
                    @else
                        <i class="fas fa-circle ms-2" style="font-size: 8px;"></i>
                    @endif
                    <div>
                        <strong>{{ $account->name }}</strong>
                        @if ($account->name_ar)
                            <br><small class="text-muted">{{ $account->name_ar }}</small>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3 mb-md-0">
                <span class="account-type-badge {{ $account->account_type }}">{{ $typeText }}</span>
            </div>
            <div class="col-md-2 mb-3 mb-md-0"><code>{{ $account->code }}</code></div>
            <div class="col-md-2 mb-3 mb-md-0">
                <div class="fw-bold {{ (float) $account->balance >= 0 ? 'text-success' : 'text-danger' }}">
                    {{ number_format((float) $account->balance, 2) }} {{ $company->currency }}
                </div>
            </div>
            <div class="col-md-2">
                @if ($canManageAccounts)
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></button>
                        @unless ($account->is_system)
                            <button type="button" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        @endunless
                    </div>
                @endif
            </div>
        </div>
        @if ($account->description)
            <div class="mt-2"><small class="text-muted">{{ $account->description }}</small></div>
        @endif
    </div>
</div>

@foreach ($account->children->sortBy('code') as $child)
    @include('partials.account_node', ['account' => $child, 'company' => $company, 'level' => $level + 1, 'canManageAccounts' => $canManageAccounts])
@endforeach
