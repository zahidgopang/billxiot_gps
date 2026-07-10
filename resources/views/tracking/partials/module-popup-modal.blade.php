{{-- Same module popup as /tracking (iframe embed) — used on standalone module pages --}}
@once('tc-module-popup-assets')
<style>
    .tc-module-loader {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 255, 255, 0.9);
        z-index: 2;
    }
    .tc-module-loader[hidden] { display: none !important; }
</style>
<div class="modal fade" id="tcModuleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" style="max-width:96vw;">
        <div class="modal-content" style="height:90vh;">
            <div class="modal-header py-2">
                <h6 class="modal-title mb-0" id="tcModuleTitle"><i class="fas fa-satellite-dish me-2"></i></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('app.tracking.hide') }}"></button>
            </div>
            <div class="modal-body p-0" style="position:relative;overflow:hidden;">
                <div id="tcModuleLoader" class="tc-module-loader" hidden><span class="spinner-border text-primary"></span></div>
                <iframe id="tcModuleFrame" title="" style="border:0;width:100%;height:100%;display:block;"></iframe>
            </div>
        </div>
    </div>
</div>
@endonce
