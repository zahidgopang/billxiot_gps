{{-- Inline phone mockups — no external image files (avoids broken assets on deploy) --}}
@php($step = (int) $step)
<div class="w-full max-w-[260px] mx-auto rounded-[2rem] border-[6px] border-slate-800 bg-slate-900 shadow-xl overflow-hidden ring-1 ring-slate-700" role="img" aria-label="Installation guide step {{ $step }}">
    <div class="h-6 bg-slate-900 flex items-center justify-center">
        <div class="w-16 h-1.5 rounded-full bg-slate-700"></div>
    </div>

    @if($step === 1)
        <div class="bg-gradient-to-b from-[#0A0F2D] to-[#121a3a] p-4 min-h-[320px]">
            <p class="text-center text-slate-400 text-xs mb-4">Downloads</p>
            <div class="rounded-2xl bg-slate-800 border border-slate-700 p-4 flex items-center gap-3 mb-4">
                <div class="w-11 h-11 rounded-full bg-green-500 flex items-center justify-center text-white">
                    <i class="fa-solid fa-download text-sm"></i>
                </div>
                <div>
                    <p class="text-white text-sm font-bold">FalconEyeGPS.apk</p>
                    <p class="text-slate-400 text-xs">Download complete</p>
                </div>
            </div>
            <div class="rounded-xl bg-sky-500 text-white text-center py-3 text-sm font-bold">Open</div>
        </div>
    @elseif($step === 2)
        <div class="bg-slate-100 p-4 min-h-[320px]">
            <p class="text-slate-900 font-bold text-sm mb-4">Files · Downloads</p>
            <div class="rounded-xl bg-white border-2 border-sky-500 p-4 flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center text-green-600">
                    <i class="fa-brands fa-android"></i>
                </div>
                <div>
                    <p class="text-slate-900 text-sm font-bold">FalconEyeGPS.apk</p>
                    <p class="text-slate-500 text-xs">Tap to open installer</p>
                </div>
            </div>
        </div>
    @elseif($step === 3)
        <div class="bg-indigo-50 p-4 min-h-[320px] flex items-center justify-center">
            <div class="w-full rounded-2xl bg-white border border-slate-200 p-5 text-center shadow-sm">
                <div class="w-12 h-12 mx-auto rounded-full bg-amber-100 text-amber-600 flex items-center justify-center mb-3">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <p class="text-slate-900 font-bold text-sm mb-1">This file may be harmful</p>
                <p class="text-slate-500 text-xs mb-4">Only install apps from sources you trust.</p>
                <div class="rounded-lg bg-slate-100 text-slate-600 py-2.5 text-sm mb-2">Cancel</div>
                <div class="rounded-lg bg-sky-500 text-white py-2.5 text-sm font-bold">Open file</div>
            </div>
        </div>
    @elseif($step === 4)
        <div class="bg-slate-50 p-4 min-h-[320px] flex items-center justify-center">
            <div class="w-full rounded-2xl bg-white border border-slate-200 p-5 text-center shadow-sm">
                <div class="w-12 h-12 mx-auto rounded-full bg-blue-100 text-blue-600 flex items-center justify-center mb-3">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <p class="text-slate-900 font-bold text-sm mb-1">App blocked to protect</p>
                <p class="text-slate-900 font-bold text-sm mb-2">your device</p>
                <p class="text-slate-500 text-xs mb-4">Google Play Protect</p>
                <div class="rounded-lg bg-slate-100 text-slate-600 py-2.5 text-sm mb-2">More details</div>
                <div class="rounded-lg bg-green-600 text-white py-2.5 text-sm font-bold">Install anyway</div>
            </div>
        </div>
    @elseif($step === 5)
        <div class="bg-gradient-to-b from-[#0A0F2D] to-[#121a3a] p-4 min-h-[320px] text-center">
            <div class="w-16 h-16 mx-auto rounded-full bg-green-500 text-white flex items-center justify-center text-2xl mt-8 mb-4">
                <i class="fa-solid fa-check"></i>
            </div>
            <p class="text-white font-bold mb-1">App installed</p>
            <p class="text-slate-400 text-xs mb-6">FalconEyeGPS is ready to use</p>
            <div class="rounded-xl bg-sky-500 text-white py-3 text-sm font-bold mb-4">Open</div>
            <div class="w-14 h-14 mx-auto rounded-2xl bg-slate-800 border border-slate-700 flex items-center justify-center text-sky-400">
                <i class="fa-solid fa-location-dot text-xl"></i>
            </div>
        </div>
    @endif

    <div class="bg-slate-900 py-2 text-center">
        <p class="text-[10px] text-slate-500">Step {{ $step }}</p>
    </div>
</div>
