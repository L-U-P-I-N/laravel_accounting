@php
    $isEditingSalesChannel = (bool) $salesChannel;
    $activeChannelModalId = old('channel_modal');
    $useOldChannelInput = $activeChannelModalId === $modalId;
    $channelNameValue = $useOldChannelInput ? old('name', $salesChannel?->name) : $salesChannel?->name;
    $channelCodeValue = $useOldChannelInput ? old('code', $salesChannel?->code) : $salesChannel?->code;
    $channelDefaultValue = $useOldChannelInput ? old('is_default', $salesChannel?->is_default) : $salesChannel?->is_default;
@endphp

<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ $action }}">
                @csrf
                @if ($isEditingSalesChannel)
                    @method('PUT')
                @endif
                <input type="hidden" name="channel_modal" value="{{ $modalId }}">
                <div class="modal-header">
                    <h5 class="modal-title">{{ $modalTitle }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">اسم القناة</label>
                        <input type="text" name="name" class="form-control" value="{{ $channelNameValue }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">الكود</label>
                        <input type="text" name="code" class="form-control" value="{{ $channelCodeValue }}" required>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_default" value="1" id="channelDefault{{ $modalId }}" @checked($channelDefaultValue)>
                        <label class="form-check-label" for="channelDefault{{ $modalId }}">تعيين كقناة افتراضية</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">{{ $isEditingSalesChannel ? 'حفظ التعديلات' : 'إضافة القناة' }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
