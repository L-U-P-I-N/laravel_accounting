@extends('layouts.app')

@section('title', 'قنوات البيع')

@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-share-nodes"></i> قنوات البيع</h2>
        <p class="text-muted mt-2 mb-0">القنوات التي تسجَّل عليها عمليات البيع وتظهر في التقارير.</p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createSalesChannelModal">
        <i class="fas fa-plus ms-1"></i> إضافة قناة بيع
    </button>
</div>

<div class="row mb-4">
    <div class="col-md-4 mb-3 mb-md-0"><div class="stat-card"><div class="stat-icon blue"><i class="fas fa-share-nodes"></i></div><div class="stat-value">{{ $salesChannels->count() }}</div><div class="stat-label">إجمالي القنوات</div></div></div>
    <div class="col-md-4 mb-3 mb-md-0"><div class="stat-card"><div class="stat-icon green"><i class="fas fa-star"></i></div><div class="stat-value">{{ $salesChannels->where('is_default', true)->count() }}</div><div class="stat-label">قنوات افتراضية</div></div></div>
    <div class="col-md-4"><div class="stat-card"><div class="stat-icon orange"><i class="fas fa-file-invoice-dollar"></i></div><div class="stat-value">{{ $salesChannels->sum('invoices_count') }}</div><div class="stat-label">عمليات البيع المرتبطة</div></div></div>
</div>

<div class="card">
    <div class="card-header"><h5 class="mb-0">قائمة قنوات البيع</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>الاسم</th>
                        <th>الكود</th>
                        <th>عدد المبيعات</th>
                        <th>الافتراضي</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($salesChannels as $salesChannel)
                        <tr>
                            <td>{{ $salesChannel->name }}</td>
                            <td>{{ $salesChannel->code }}</td>
                            <td>{{ $salesChannel->invoices_count }}</td>
                            <td>{!! $salesChannel->is_default ? '<span class="badge bg-success">افتراضي</span>' : '<span class="text-muted">-</span>' !!}</td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editSalesChannelModal{{ $salesChannel->id }}"><i class="fas fa-edit"></i></button>
                                <form method="POST" action="{{ route('sales_channels.destroy', $salesChannel) }}" class="d-inline" onsubmit="return confirm('سيتم حذف القناة فقط إذا لم تكن مرتبطة بعمليات بيع. هل تريد المتابعة؟');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center py-4">لا توجد قنوات بيع بعد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@include('partials.sales_channel_modal', [
    'modalId' => 'createSalesChannelModal',
    'modalTitle' => 'إضافة قناة بيع',
    'action' => route('sales_channels.store'),
    'salesChannel' => null,
])

@foreach ($salesChannels as $salesChannel)
    @include('partials.sales_channel_modal', [
        'modalId' => 'editSalesChannelModal' . $salesChannel->id,
        'modalTitle' => 'تعديل قناة البيع: ' . $salesChannel->name,
        'action' => route('sales_channels.update', $salesChannel),
        'salesChannel' => $salesChannel,
    ])
@endforeach
@endsection

@push('scripts')
@if ($errors->any() && old('channel_modal'))
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalElement = document.getElementById(@json(old('channel_modal')));

    if (modalElement) {
        bootstrap.Modal.getOrCreateInstance(modalElement).show();
    }
});
</script>
@endif
@endpush
