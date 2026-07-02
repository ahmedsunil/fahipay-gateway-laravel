<div>
    <button type="button" class="btn btn-primary" wire:click="openModal">
        {{ $slot ?: 'Open Payment' }}
    </button>

    @if($showModal)
    <div class="modal d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ $modalTitle }}</h5>
                    <button type="button" class="btn-close" wire:click="closeModal"></button>
                </div>
                
                <div class="modal-body">
                    @if($showAmount)
                    <div class="mb-3">
                        <label class="form-label">Amount (MVR)</label>
                        <div class="form-control bg-light">{{ number_format($amount, 2) }}</div>
                        @error('amount') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                    @endif

                    @if($showDescription)
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" class="form-control" wire:model="description">
                    </div>
                    @endif

                    <input type="hidden" wire:model="transaction_id">

                    @if($errorMessage)
                    <div class="alert alert-danger">
                        {{ $errorMessage }}
                    </div>
                    @endif
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="closeModal">Cancel</button>
                    <button type="button" 
                            class="btn btn-primary" 
                            wire:click="initiatePayment"
                            wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="initiatePayment">{{ $submitText }}</span>
                        <span wire:loading wire:target="initiatePayment">Processing...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
