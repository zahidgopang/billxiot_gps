<div class="filter-panel" id="filterPanel">
    <div class="filter-header d-flex align-items-center justify-content-between">
        <h5 class="mb-0"><i class="fa fa-filter text-primary me-2"></i>Tracking Filter</h5>
    </div>

    <label class="form-label mt-3">Date Range</label>
    <input id="dateRange" class="form-control mb-3" placeholder="Select date range">

    <button id="applyFilter" class="btn btn-primary w-100 mb-3">Search</button>

    <div class="card p-3">
        <h6 class="mb-2">Device Summary</h6>

        <p><strong>Name:</strong> {{ $device->name }}</p>
        <p><strong>IMEI:</strong> {{ $device->imei }}</p>
        <p><strong>Last Seen:</strong> <span id="lastSeen">-</span></p>
        <p><strong>Status:</strong> <span id="curStatus">-</span></p>

        <hr>

        <h6 class="mb-1">Route Summary</h6>
        <p style="margin:0;"><strong>Total Distance:</strong> <span id="totalDistance">-</span> km</p>
        <p style="margin:0;"><strong>Duration:</strong> <span id="routeDuration">-</span></p>
        <p style="margin:0;"><strong>Average Speed:</strong> <span id="avgSpeed">-</span> km/h</p>

        <hr>

        <h6 class="mb-1">Idle Summary</h6>
        <p style="margin:0;"><strong>Idle Events:</strong> <span id="idleCount">0</span></p>
        <p style="margin:0;"><strong>Total Idle Time:</strong> <span id="idleTotal">0m</span></p>

        <hr>

        <div id="geofenceList" style="font-size:13px;color:#444; max-height:200px; overflow:auto;">Loading...</div>

        <hr>

        <h6 class="mb-1">Manage Geofences</h6>
        <ul id="geofenceManageList" style="padding-left:18px; max-height:140px; overflow:auto;"></ul>

        <button id="reverseBtn" class="btn btn-outline-secondary btn-sm mt-2">Get Address</button>
        <div id="addressBox" class="mt-2 small text-muted"></div>
    </div>
</div>
