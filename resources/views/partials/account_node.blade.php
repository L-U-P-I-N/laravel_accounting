@php
    $canManageAccounts = $canManageAccounts ?? auth()->user()->hasPermission('manage_accounts');
    $typeText = $account['display_account_type'] ?: match ($account['account_type']) {
        'asset' => 'أصول',
        'liability' => 'خصوم',
        'equity' => 'ملكية',
        'revenue' => 'إيرادات',
        'expense' => 'مصروفات',
        default => 'تكلفة',
    };
    $nodeId = 'account-node-' . $account['id'];
    $childrenId = 'account-children-' . $account['id'];
    
    // Get children from shared view data
    $flatAccounts = $chartAccountsLookup ?? [];
    $children = [];
    foreach ($flatAccounts as $id => $acc) {
        if ($acc['parent_id'] === $account['id']) {
            $children[] = $acc;
        }
    }
    // Sort children by code
    usort($children, fn($a, $b) => strcmp($a['code'], $b['code']));
    $hasChildren = !empty($children);
    
    $description = trim((string) ($account['description'] ?? ''));
    $displayBalance = $account['rolled_up_balance'] ?? (float) ($account['balance'] ?? 0);
    $isParentWithChildren = $hasChildren && $displayBalance != (float) ($account['balance'] ?? 0);
@endphp

@once
<style>
.coa-node {
    position: relative;
    padding-right: calc(var(--level, 0) * 28px);
}

.coa-node::before {
    content: '';
    position: absolute;
    top: 0;
    right: calc(12px + (var(--level, 0) * 28px));
    bottom: 0;
    width: 2px;
    background: linear-gradient(180deg, rgba(203, 213, 225, 0.8), rgba(203, 213, 225, 0.1));
}

.coa-node.level-0::before {
    display: none;
}

.coa-node-card {
    position: relative;
    margin-bottom: 16px;
    border: 1px solid rgba(15, 23, 42, 0.07);
    border-radius: 24px;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(252, 250, 246, 0.98) 100%);
    overflow: hidden;
    box-shadow: 0 14px 34px rgba(15, 23, 42, 0.05);
}

.coa-node-card::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 6px;
    height: 100%;
    background: var(--accent-color, #94a3b8);
}

.coa-node-card.asset { --accent-color: var(--coa-asset); }
.coa-node-card.liability { --accent-color: var(--coa-liability); }
.coa-node-card.equity { --accent-color: var(--coa-equity); }
.coa-node-card.revenue { --accent-color: var(--coa-revenue); }
.coa-node-card.expense { --accent-color: var(--coa-expense); }
.coa-node-card.cogs { --accent-color: var(--coa-cogs); }

.coa-node-main {
    padding: 22px 22px 18px;
}

.coa-node-row {
    display: grid;
    grid-template-columns: minmax(0, 2.2fr) minmax(120px, 0.8fr) minmax(110px, 0.8fr) minmax(160px, 1fr) auto;
    gap: 18px;
    align-items: center;
}

.coa-node-title-wrap {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.coa-node-toggle {
    width: 40px;
    height: 40px;
    border-radius: 14px;
    border: 1px solid rgba(59, 130, 246, 0.18);
    background: linear-gradient(180deg, #f4f8ff 0%, #ebf2ff 100%);
    color: #1d4ed8;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.coa-node-toggle[aria-expanded="true"] i {
    transform: rotate(180deg);
}

.coa-node-toggle i {
    transition: transform 0.25s ease;
}

.coa-node-bullet {
    width: 40px;
    height: 40px;
    border-radius: 14px;
    background: linear-gradient(180deg, #f9fafb 0%, #f3f4f6 100%);
    color: #6b7280;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.coa-node-title {
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--coa-text-main);
    margin: 0;
    line-height: 1.5;
}

.coa-node-subtitle {
    color: var(--coa-text-soft);
    margin-top: 6px;
    font-size: 0.93rem;
    line-height: 1.65;
}

.coa-node-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 12px;
}

.coa-node-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 11px;
    border-radius: 999px;
    background: rgba(248, 250, 252, 0.92);
    border: 1px solid rgba(148, 163, 184, 0.18);
    color: #475569;
    font-size: 0.81rem;
    font-weight: 700;
}

.coa-node-code,
.coa-node-kind,
.coa-node-balance {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.coa-label {
    font-size: 0.79rem;
    color: var(--coa-text-soft);
    font-weight: 700;
    letter-spacing: 0.01em;
}

.coa-value {
    color: var(--coa-text-main);
    font-weight: 800;
}

.coa-node-balance .coa-value {
    font-size: 1.04rem;
}

.coa-node-actions {
    display: flex;
    justify-content: flex-end;
}

.coa-node-actions .btn {
    border-radius: 12px;
}

.coa-node-code code {
    background: #f7f5ef;
    color: #334155;
    padding: 0.35rem 0.55rem;
    border-radius: 10px;
    font-size: 0.9rem;
}

.coa-node-description {
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px dashed rgba(148, 163, 184, 0.28);
    color: var(--coa-text-soft);
    font-size: 0.94rem;
    line-height: 1.9;
}

.coa-node-children {
    margin-right: 22px;
}

.coa-node-children-inner {
    padding: 4px 0 6px;
}

@media (max-width: 991.98px) {
    .coa-node-row {
        grid-template-columns: 1fr 1fr;
    }

    .coa-node-actions {
        justify-content: flex-start;
        grid-column: 1 / -1;
    }
}

@media (max-width: 575.98px) {
    .coa-node {
        padding-right: calc(var(--level, 0) * 16px);
    }

    .coa-node::before {
        right: calc(7px + (var(--level, 0) * 16px));
    }

    .coa-node-row {
        grid-template-columns: 1fr;
    }

    .coa-node-main {
        padding: 18px 16px;
    }
}
</style>
@endonce

<div class="coa-node level-{{ $level }}" id="{{ $nodeId }}" style="--level: {{ $level }};">
    <div class="coa-node-card {{ $account['account_type'] }}">
        <div class="coa-node-main">
            <div class="coa-node-row">
                <div class="coa-node-title-wrap">
                    @if ($hasChildren)
                        <button type="button" class="coa-node-toggle" data-bs-toggle="collapse" data-bs-target="#{{ $childrenId }}" aria-expanded="true" aria-controls="{{ $childrenId }}">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    @else
                        <span class="coa-node-bullet"><i class="fas fa-circle" style="font-size: 8px;"></i></span>
                    @endif
                    <div class="flex-grow-1">
                        <h3 class="coa-node-title">{{ $account['name'] }}</h3>
                        @if (!empty($account['name_ar']) && $account['name_ar'] !== $account['name'])
                            <div class="coa-node-subtitle">{{ $account['name_ar'] }}</div>
                        @endif
                        <div class="coa-node-meta">
                            <span class="coa-node-chip">
                                <span class="coa-type-dot {{ $account['account_type'] }}"></span>
                                {{ $typeText }}
                            </span>
                            @if (!empty($account['is_system']))
                                <span class="coa-node-chip"><i class="fas fa-shield-halved"></i> حساب نظام</span>
                            @endif
                            @if (!empty($account['allows_direct_transactions']))
                                <span class="coa-node-chip"><i class="fas fa-money-bill-transfer"></i> دفع/تحصيل مباشر</span>
                            @endif
                            @if ($hasChildren)
                                <span class="coa-node-chip"><i class="fas fa-code-branch"></i> {{ count($children) }} فروع</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="coa-node-code">
                    <span class="coa-label">الكود</span>
                    <span class="coa-value"><code>{{ $account['code'] }}</code></span>
                </div>

                <div class="coa-node-kind">
                    <span class="coa-label">النوع</span>
                    <span class="coa-value">{{ $typeText }}</span>
                </div>

                <div class="coa-node-balance">
                    <span class="coa-label">الرصيد الحالي</span>
                    <span class="coa-value {{ (float) $displayBalance >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ number_format((float) $displayBalance, 2) }} {{ $company->currency }}
                    </span>
                    @if ($isParentWithChildren)
                        <small class="text-muted d-block" style="font-size: 0.75rem;">
                            <i class="fas fa-layer-group me-1"></i>مجمع من {{ count($children) }} حساب
                        </small>
                    @endif
                </div>

                <div class="coa-node-actions">
                    <a href="{{ route('chart_of_accounts.show', $account['id']) }}" class="btn btn-sm btn-outline-primary" title="عرض تفاصيل الحساب">
                        <i class="fas fa-eye ms-1"></i> عرض
                    </a>
                </div>
            </div>

            @if ($description !== '')
                <div class="coa-node-description">{{ $description }}</div>
            @endif
        </div>

        @if ($hasChildren)
            <div id="{{ $childrenId }}" class="coa-node-children collapse show">
                <div class="coa-node-children-inner">
                    @foreach ($children as $child)
                        @include('partials.account_node', ['account' => $child, 'company' => $company, 'level' => $level + 1, 'canManageAccounts' => $canManageAccounts])
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
