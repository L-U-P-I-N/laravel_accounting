@php
    $isEditingBranch = (bool) $branch;
    $activeBranchModalId = old('branch_modal');
    $useOldBranchInput = $activeBranchModalId === $modalId;
    $branchNameValue = $useOldBranchInput ? old('name', $branch?->name) : $branch?->name;
    $branchCodeValue = $useOldBranchInput ? old('code', $branch?->code) : $branch?->code;
    $branchCityValue = $useOldBranchInput ? old('city', $branch?->city) : $branch?->city;
    $branchDefaultValue = $useOldBranchInput ? old('is_default', $branch?->is_default) : $branch?->is_default;
@endphp

<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ $action }}">
                @csrf
                @if ($isEditingBranch)
                    @method('PUT')
                @endif
                <input type="hidden" name="branch_modal" value="{{ $modalId }}">
                <div class="modal-header">
                    <h5 class="modal-title">{{ $modalTitle }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">اسم الفرع</label>
                        <input type="text" name="name" class="form-control" value="{{ $branchNameValue }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">الكود</label>
                        <input type="text" name="code" class="form-control" value="{{ $branchCodeValue }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">المدينة</label>
                        <input type="text" name="city" class="form-control" value="{{ $branchCityValue }}">
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_default" value="1" id="branchDefault{{ $modalId }}" @checked($branchDefaultValue)>
                        <label class="form-check-label" for="branchDefault{{ $modalId }}">تعيين كفرع افتراضي</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">{{ $isEditingBranch ? 'حفظ التعديلات' : 'إضافة الفرع' }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
