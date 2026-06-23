/* ===========================
   map.playback.js
=========================== */

window.playbackIndex = 0;
window.playbackTimer = null;

function initPlaybackModule() {
    console.log('▶ Playback module ready');
}

function startPlayback() {
    if (!historyData.length) return;

    stopPlayback();
    playbackIndex = 0;

    playbackTimer = setInterval(() => {
        if (playbackIndex >= historyData.length) {
            stopPlayback();
            return;
        }

        const point = historyData[playbackIndex];
        currentPositionMarker.setPosition({
            lat: +point.lat,
            lng: +point.lng
        });

        playbackIndex++;
    }, 800);
}

function stopPlayback() {
    clearInterval(playbackTimer);
}
